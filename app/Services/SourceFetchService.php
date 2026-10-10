<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use App\Models\SourceItem;
use App\Models\SourceWebsite;
use App\Models\TopicSuggestion;
use App\Services\AIService;

class SourceFetchService
{
    public function fetchAll(?int $createdBy = null): array
    {
        $stats = ['sources' => 0, 'items' => 0, 'errors' => 0];
        foreach (SourceWebsite::all(true, $createdBy) as $source) {
            $stats['sources']++;
            try {
                $n = $this->fetchOne((int) $source['id']);
                $stats['items'] += $n;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Logger::error('Source fetch failed', ['source_id' => $source['id'], 'error' => $e->getMessage()]);
            }
        }
        return $stats;
    }

    public function fetchOne(int $sourceId): int
    {
        $source = SourceWebsite::find($sourceId);
        if (!$source) {
            return 0;
        }

        $rssUrl = $source['rss_url'] ?: $this->guessRss($source['url']);
        $items = $rssUrl ? $this->parseRss($rssUrl) : [];

        // Fallback: homepage title links (very light)
        if ($items === []) {
            $items = $this->parseHomepage((string) $source['url']);
        }

        $count = 0;
        foreach (array_slice($items, 0, 15) as $item) {
            $hash = hash('sha256', $sourceId . '|' . ($item['url'] ?? '') . '|' . ($item['title'] ?? ''));
            $ok = SourceItem::createIgnoreDuplicate([
                'source_id' => $sourceId,
                'title' => mb_substr((string) ($item['title'] ?? 'Untitled'), 0, 500),
                'url' => mb_substr((string) ($item['url'] ?? $source['url']), 0, 700),
                'summary' => $item['summary'] ?? null,
                'published_at' => $item['published_at'] ?? null,
                'content_hash' => $hash,
            ]);
            if ($ok) {
                $count++;
            }
        }

        SourceWebsite::update($sourceId, ['last_fetched_at' => date('Y-m-d H:i:s')]);
        return $count;
    }

    /**
     * Use AI to turn recent source items into original topic suggestions.
     */
    public function generateSuggestions(int $limit = 8, ?int $adminId = null, string $lang = 'bn'): array
    {
        $items = SourceItem::recent($limit, $adminId);
        if ($items === []) {
            return ['success' => false, 'error' => 'No source items yet. Add websites and fetch first.', 'count' => 0];
        }

        $lines = [];
        foreach ($items as $i => $it) {
            $lines[] = ($i + 1) . '. [' . ($it['source_name'] ?? '') . '] ' . ($it['title'] ?? '')
                . (empty($it['summary']) ? '' : ' — ' . mb_substr(strip_tags((string) $it['summary']), 0, 120));
        }
        $list = implode("\n", $lines);

        if ($lang === 'en') {
            $prompt = <<<PROMPT
You are a senior content strategist for an English-language blog targeting readers in Tier-1 countries: United States, United Kingdom, Canada, and Australia.

Based on the following recent headlines from competitor/news/blog sites, suggest ORIGINAL blog post ideas.

Headlines:
{$list}

Return ONLY valid JSON array:
[
  {
    "title": "original blog title in English",
    "angle": "unique angle in 1-2 sentences (relevant to US/UK/Canada/Australia readers)",
    "primary_keyword": "main SEO keyword",
    "category": "category",
    "source_index": 1
  }
]

Rules:
- Suggest 5-8 ideas
- Write ONLY in English — no Bengali words, no references to Bangladesh
- Target audience: English speakers in US, UK, Canada, Australia
- Ideas must be original, helpful, SEO-friendly for Tier-1 markets
- Prefer evergreen angles and practical how-to content
- Do NOT mention Bangladesh, Bangladeshi context, or any South Asian specific references
PROMPT;
        } else {
            $prompt = <<<PROMPT
You are a content strategist for a Bengali/English blog. Based on the following recent headlines from competitor/news/blog sites, suggest ORIGINAL blog post ideas (do NOT copy titles).

Headlines:
{$list}

Return ONLY valid JSON array:
[
  {
    "title": "original blog title",
    "angle": "unique angle in 1-2 sentences",
    "primary_keyword": "main keyword",
    "category": "category",
    "source_index": 1
  }
]

Rules:
- Suggest 5-8 ideas
- Ideas must be original, helpful, SEO-friendly
- Prefer evergreen angles over pure news copy
- Language of title can be Bengali or English matching the sources
PROMPT;
        }

        $ai = new AIService();
        // Use internal generate via reflection-free path: call generateContent-like raw
        $result = $this->aiSuggest($ai, $prompt);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'AI failed', 'count' => 0];
        }

        $created = 0;
        foreach ($result['items'] as $row) {
            $srcIndex = (int) ($row['source_index'] ?? 0) - 1;
            $sourceItemId = null;
            if ($srcIndex >= 0 && isset($items[$srcIndex])) {
                $sourceItemId = (int) $items[$srcIndex]['id'];
            }
            TopicSuggestion::create([
                'source_item_id' => $sourceItemId,
                'title' => (string) ($row['title'] ?? 'Untitled'),
                'angle' => (string) ($row['angle'] ?? ''),
                'primary_keyword' => (string) ($row['primary_keyword'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'status' => 'pending',
                'created_by' => $adminId,
            ]);
            $created++;
        }

        return ['success' => true, 'count' => $created];
    }


    private function aiSuggest(AIService $ai, string $prompt): array
    {
        $out = $ai->completeJson($prompt, 'source_suggest');
        if (empty($out['success'])) {
            return ['success' => false, 'error' => $out['error'] ?? 'AI error'];
        }
        $data = $out['data'];
        if (isset($data[0]) && is_array($data[0])) {
            return ['success' => true, 'items' => $data];
        }
        if (isset($data['suggestions']) && is_array($data['suggestions'])) {
            return ['success' => true, 'items' => $data['suggestions']];
        }
        if (is_array($data)) {
            return ['success' => true, 'items' => array_values($data)];
        }
        return ['success' => false, 'error' => 'Unexpected AI format'];
    }

    private function guessRss(string $url): ?string
    {
        $base = rtrim($url, '/');
        $candidates = [
            $base . '/feeds/posts/default?alt=rss',
            $base . '/rss',
            $base . '/feed',
            $base . '/rss.xml',
            $base . '/atom.xml',
            $base . '/index.xml',
        ];
        foreach ($candidates as $c) {
            if ($this->urlExists($c)) {
                return $c;
            }
        }
        return null;
    }

    private function urlExists(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AI-Blogger/1.0',
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 400;
    }

    private function parseRss(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AI-Blogger/1.0',
        ]);
        $xml = curl_exec($ch);
        curl_close($ch);
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        if ($feed === false) {
            return [];
        }

        $items = [];
        // RSS 2.0
        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = [
                    'title' => (string) $item->title,
                    'url' => (string) ($item->link ?? ''),
                    'summary' => strip_tags((string) ($item->description ?? '')),
                    'published_at' => $this->parseDate((string) ($item->pubDate ?? '')),
                ];
            }
        }
        // Atom
        if ($items === [] && isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $l) {
                        $attrs = $l->attributes();
                        if (isset($attrs['href'])) {
                            $link = (string) $attrs['href'];
                            break;
                        }
                    }
                }
                $items[] = [
                    'title' => (string) $entry->title,
                    'url' => $link,
                    'summary' => strip_tags((string) ($entry->summary ?? $entry->content ?? '')),
                    'published_at' => $this->parseDate((string) ($entry->updated ?? $entry->published ?? '')),
                ];
            }
        }
        return $items;
    }

    private function parseHomepage(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AI-Blogger/1.0',
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
        if (!is_string($html)) {
            return [];
        }
        $items = [];
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $href = html_entity_decode($match[1]);
                $title = trim(strip_tags($match[2]));
                if (mb_strlen($title) < 25 || mb_strlen($title) > 180) {
                    continue;
                }
                if (str_starts_with($href, '/')) {
                    $parts = parse_url($url);
                    $href = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $href;
                }
                if (!str_starts_with($href, 'http')) {
                    continue;
                }
                $items[] = ['title' => $title, 'url' => $href, 'summary' => null, 'published_at' => null];
                if (count($items) >= 10) {
                    break;
                }
            }
        }
        return $items;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
