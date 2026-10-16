<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\SourceWebsite;
use App\Models\SourceItem;
use App\Models\TopicSuggestion;
use App\Models\BlueprintIdea;
use App\Models\Post;
use App\Models\ActivityLog;
use App\Services\SourceFetchService;
use App\Services\AIService;

class SourceController
{
    public function index(): void
    {
        $uid = (int) Session::getAdminId();
        Response::view('sources/index', [
            'sources'        => SourceWebsite::all(false, $uid),
            'items'          => SourceItem::recent(20, $uid),
            'suggestions'    => TopicSuggestion::listByStatus('pending', 30, $uid),
            'blueprint'      => SourceWebsite::getBlueprint($uid),
            'blueprintIdeas' => BlueprintIdea::listPending($uid, 10),
            'pageTitle'      => 'Content Sources',
        ]);
    }

    public function store(): void
    {
        $name     = trim((string) ($_POST['name']     ?? ''));
        $url      = trim((string) ($_POST['url']      ?? ''));
        $rss      = trim((string) ($_POST['rss_url']  ?? ''));
        $type     = in_array($_POST['site_type'] ?? '', ['blog', 'news', 'other'], true) ? $_POST['site_type'] : 'blog';
        $category = trim((string) ($_POST['category'] ?? ''));

        if ($name === '' || $url === '') {
            Session::flash('error', 'Name and URL are required.');
            Response::redirect('/sources');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if ($rss !== '' && !preg_match('#^https?://#i', $rss)) {
            $rss = 'https://' . $rss;
        }

        SourceWebsite::create([
            'name'       => $name,
            'url'        => $url,
            'rss_url'    => $rss !== '' ? $rss : null,
            'site_type'  => $type,
            'category'   => $category !== '' ? $category : null,
            'created_by' => Session::getAdminId(),
        ]);
        Session::flash('success', 'Source website added.');
        Response::redirect('/sources');
    }

    public function delete(int $id): void
    {
        SourceWebsite::delete($id, (int) Session::getAdminId());
        Session::flash('success', 'Source deleted.');
        Response::redirect('/sources');
    }

    public function fetch(): void
    {
        $uid = (int) Session::getAdminId();
        $svc = new SourceFetchService();
        if (!empty($_POST['source_id'])) {
            $src = SourceWebsite::find((int) $_POST['source_id']);
            if (!$src || (int) ($src['created_by'] ?? 0) !== $uid) {
                Session::flash('error', 'Source not found.');
                Response::redirect('/sources');
            }
            $n = $svc->fetchOne((int) $_POST['source_id']);
            Session::flash('success', "Fetched {$n} new items.");
        } else {
            $stats = $svc->fetchAll($uid);
            Session::flash('success', "Sources: {$stats['sources']}, new items: {$stats['items']}, errors: {$stats['errors']}.");
        }
        Response::redirect('/sources');
    }

    public function suggest(): void
    {
        $svc  = new SourceFetchService();
        $lang = in_array($_POST['suggest_language'] ?? '', ['bn', 'en'], true)
            ? $_POST['suggest_language']
            : 'bn';
        $result = $svc->generateSuggestions(10, Session::getAdminId(), $lang);
        if ($result['success']) {
            Session::flash('success', "Created {$result['count']} topic suggestions.");
        } else {
            Session::flash('error', $result['error'] ?? 'Suggestion failed.');
        }
        Response::redirect('/sources');
    }

    public function acceptSuggestion(int $id): void
    {
        $s   = TopicSuggestion::find($id);
        $uid = (int) Session::getAdminId();
        if (!$s || $s['status'] !== 'pending' || (int) ($s['created_by'] ?? 0) !== $uid) {
            Session::flash('error', 'Suggestion not found.');
            Response::redirect('/sources');
        }

        $lang     = in_array($_POST['language'] ?? '', ['bn', 'en'], true) ? $_POST['language'] : 'bn';
        $audience = $lang === 'en'
            ? 'Readers in the United States, United Kingdom, Canada, and Australia'
            : 'General Bangladesh Readers';

        $postId = Post::create([
            'topic'              => $s['title'],
            'primary_keyword'    => $s['primary_keyword'] ?: $s['title'],
            'secondary_keywords' => [],
            'category'           => $s['category'] ?? null,
            'language'           => $lang,
            'target_audience'    => $audience,
            'writing_tone'       => 'professional',
            'word_count'         => (int) ($_ENV['AI_DEFAULT_WORD_COUNT'] ?? 1500),
            'status'             => 'DRAFT',
            'created_by'         => Session::getAdminId(),
            'tags'               => [],
        ]);

        // Store angle in excerpt temporarily for generate prompt context
        Post::update($postId, ['excerpt' => $s['angle'] ?? null]);

        TopicSuggestion::updateStatus($id, 'accepted', $postId);
        ActivityLog::log('suggestion_accepted', Session::getAdminId(), 'post', $postId, $s['title']);

        Session::flash('success', 'Suggestion accepted. Generating post...');
        Response::redirect("/posts/{$postId}/generate");
    }

    public function rejectSuggestion(int $id): void
    {
        TopicSuggestion::updateStatus($id, 'rejected');
        Session::flash('success', 'Suggestion rejected.');
        Response::redirect('/sources');
    }

    // ─── Blueprint ───────────────────────────────────────────────────────────

    /**
     * Save / update the content-strategy blueprint text.
     */
    public function saveBlueprint(): void
    {
        $uid  = (int) Session::getAdminId();
        $text = trim((string) ($_POST['blueprint'] ?? ''));

        SourceWebsite::saveBlueprint($uid, $text);
        ActivityLog::log('blueprint_saved', $uid, 'source', null, 'Blueprint updated');

        if ($this->isAjax()) {
            Response::success(null, 'Blueprint saved.');
        }
        Session::flash('success', 'Blueprint saved successfully.');
        Response::redirect('/sources');
    }

    /**
     * Use the blueprint to generate 3–5 AI topic ideas and persist them.
     */
    public function generateBlueprintIdeas(): void
    {
        $uid  = (int) Session::getAdminId();
        $lang = in_array($_POST['language'] ?? '', ['bn', 'en'], true) ? $_POST['language'] : 'bn';

        $blueprint = SourceWebsite::getBlueprint($uid);
        if ($blueprint === '') {
            Session::flash('error', 'Please save your content blueprint first.');
            Response::redirect('/sources');
        }

        // Prune stale pending ideas before generating fresh ones
        BlueprintIdea::pruneOld($uid, 7);

        $langLabel = $lang === 'en' ? 'English (Tier-1)' : 'Bengali';

        $prompt = <<<PROMPT
You are a content strategist. Read the following blog content strategy blueprint carefully and generate 4–5 ORIGINAL, actionable blog post topic ideas.

BLUEPRINT:
{$blueprint}

TARGET LANGUAGE: {$langLabel}

For each idea return JSON with keys:
- "topic": the full blog post title
- "primary_keyword": main SEO keyword
- "tone": suggested writing tone (e.g. "Professional", "Conversational", "Listicle")
- "tags": array of 3–5 relevant tags
- "angle": 1–2 sentence description of the unique angle
- "category": blog category

Return ONLY a valid JSON array of objects. No markdown fences, no extra text.
PROMPT;

        $ai     = new AIService();
        $result = $ai->completeJson($prompt, 'blueprint_ideas');

        if (empty($result['success'])) {
            Session::flash('error', $result['error'] ?? 'AI failed to generate ideas.');
            Response::redirect('/sources');
        }

        $rows = $result['data'];
        // Unwrap if nested
        if (isset($rows['ideas']) && is_array($rows['ideas'])) {
            $rows = $rows['ideas'];
        } elseif (!isset($rows[0])) {
            $rows = array_values($rows);
        }

        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['topic'])) {
                continue;
            }
            BlueprintIdea::create([
                'admin_id'        => $uid,
                'topic'           => (string) ($row['topic']           ?? ''),
                'primary_keyword' => (string) ($row['primary_keyword'] ?? ''),
                'tone'            => (string) ($row['tone']            ?? ''),
                'tags'            => (array)  ($row['tags']            ?? []),
                'angle'           => (string) ($row['angle']           ?? ''),
                'category'        => (string) ($row['category']        ?? ''),
                'language'        => $lang,
            ]);
            $count++;
        }

        ActivityLog::log('blueprint_ideas_generated', $uid, 'source', null, "Generated {$count} blueprint ideas");

        if ($this->isAjax()) {
            Response::success(['count' => $count], "Generated {$count} topic ideas.");
        }
        Session::flash('success', "Generated {$count} topic ideas from your blueprint.");
        Response::redirect('/sources');
    }

    /**
     * Dismiss a blueprint idea.
     */
    public function dismissBlueprintIdea(int $id): void
    {
        $uid = (int) Session::getAdminId();
        BlueprintIdea::markDismissed($id, $uid);

        if ($this->isAjax()) {
            Response::success(null, 'Idea dismissed.');
        }
        Response::redirect('/sources');
    }

    /**
     * Return current blueprint ideas as JSON (AJAX).
     */
    public function getBlueprintIdeas(): void
    {
        $uid   = (int) Session::getAdminId();
        $ideas = BlueprintIdea::listPending($uid, 10);

        foreach ($ideas as &$idea) {
            $idea['tags_array'] = BlueprintIdea::decodeTags($idea);
        }
        unset($idea);

        Response::success($ideas, 'OK');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function isAjax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
