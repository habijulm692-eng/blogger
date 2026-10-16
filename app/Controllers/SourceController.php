<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\SourceWebsite;
use App\Models\SourceItem;
use App\Models\TopicSuggestion;
use App\Models\Post;
use App\Models\ActivityLog;
use App\Services\SourceFetchService;

class SourceController
{
    public function index(): void
    {
        $uid = (int) Session::getAdminId();
        Response::view('sources/index', [
            'sources' => SourceWebsite::all(false, $uid),
            'items' => SourceItem::recent(20, $uid),
            'suggestions' => TopicSuggestion::listByStatus('pending', 30, $uid),
            'pageTitle' => 'Content Sources',
        ]);
    }

    public function store(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $rss = trim((string) ($_POST['rss_url'] ?? ''));
        $type = in_array($_POST['site_type'] ?? '', ['blog', 'news', 'other'], true) ? $_POST['site_type'] : 'blog';
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
            'name' => $name,
            'url' => $url,
            'rss_url' => $rss !== '' ? $rss : null,
            'site_type' => $type,
            'category' => $category !== '' ? $category : null,
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
        $svc = new SourceFetchService();
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
        $s = TopicSuggestion::find($id);
        $uid = (int) Session::getAdminId();
        if (!$s || $s['status'] !== 'pending' || (int) ($s['created_by'] ?? 0) !== $uid) {
            Session::flash('error', 'Suggestion not found.');
            Response::redirect('/sources');
        }

        $lang = in_array($_POST['language'] ?? '', ['bn', 'en'], true) ? $_POST['language'] : 'bn';
        $audience = $lang === 'en'
            ? 'Readers in the United States, United Kingdom, Canada, and Australia'
            : 'General Bangladesh Readers';

        $postId = Post::create([
            'topic' => $s['title'],
            'primary_keyword' => $s['primary_keyword'] ?: $s['title'],
            'secondary_keywords' => [],
            'category' => $s['category'] ?? null,
            'language' => $lang,
            'target_audience' => $audience,
            'writing_tone' => 'professional',
            'word_count' => (int) ($_ENV['AI_DEFAULT_WORD_COUNT'] ?? 1500),
            'status' => 'DRAFT',
            'created_by' => Session::getAdminId(),
            'tags' => [],
        ]);

        // Store angle in excerpt temporarily for generate prompt context
        Post::update($postId, [
            'excerpt' => $s['angle'] ?? null,
        ]);

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
}
