<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use App\Helpers\Session;
use App\Models\BloggerConnection;
use App\Models\Post;

class BloggerService
{
    private array $config;
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const BLOGGER_API = 'https://www.googleapis.com/blogger/v3';
    private const SCOPES = 'https://www.googleapis.com/auth/blogger';

    public function __construct()
    {
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $this->config = $appConfig['google'] ?? [];
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    public function getAuthUrl(string $state): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function handleCallback(string $code, int $adminId): array
    {
        $result = $this->httpPost(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        if (!$result['success'] || empty($result['data']['access_token'])) {
            Logger::error('Blogger OAuth token exchange failed', ['response' => $result]);
            return ['success' => false, 'error' => 'Failed to obtain access token from Google.'];
        }

        $data = $result['data'];
        $expiresAt = isset($data['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $data['expires_in'])
            : null;

        // Fetch user blogs
        $blogs = $this->listBlogs($data['access_token']);
        $blogId = $this->config['blogger_blog_id'] ?? '';
        $blogName = null;
        $blogUrl = null;

        if (!empty($blogs['items'])) {
            // Use configured blog ID or first blog
            $selected = null;
            foreach ($blogs['items'] as $blog) {
                if ($blogId !== '' && ($blog['id'] ?? '') === $blogId) {
                    $selected = $blog;
                    break;
                }
            }
            $selected = $selected ?? $blogs['items'][0];
            $blogId = $selected['id'] ?? $blogId;
            $blogName = $selected['name'] ?? null;
            $blogUrl = $selected['url'] ?? null;
        }

        BloggerConnection::save([
            'admin_id' => $adminId,
            'google_account_email' => null,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => $expiresAt,
            'blog_id' => $blogId,
            'blog_name' => $blogName,
            'blog_url' => $blogUrl,
        ]);

        return [
            'success' => true,
            'blog_id' => $blogId,
            'blog_name' => $blogName,
            'blog_url' => $blogUrl,
        ];
    }

    /**
     * Publish a post to Blogger
     */
    public function publish(array $post): array
    {
        $adminId = (int) (Session::getAdminId() ?? 0);
        $connection = BloggerConnection::getActive($adminId > 0 ? $adminId : null);

        if ($connection === null) {
            return ['success' => false, 'error' => 'Blogger is not connected.', 'error_code' => 'NOT_CONNECTED'];
        }

        // Prevent duplicate publish
        if (!empty($post['blogger_post_id'])) {
            return [
                'success' => true,
                'blogger_post_id' => $post['blogger_post_id'],
                'blogger_url' => $post['blogger_url'] ?? null,
                'already_published' => true,
            ];
        }

        $accessToken = $this->ensureValidToken($connection);
        if ($accessToken === null) {
            return ['success' => false, 'error' => 'Failed to refresh Blogger access token.', 'error_code' => 'TOKEN_REFRESH_FAILED'];
        }

        $blogId = $connection['blog_id'];
        if (empty($blogId)) {
            return ['success' => false, 'error' => 'No Blogger blog selected.', 'error_code' => 'NO_BLOG'];
        }

        // Build safe title (Blogger rejects empty / control chars)
        $title = trim((string) ($post['title'] ?? ''));
        $title = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $title) ?? $title;
        if ($title === '') {
            $title = trim((string) ($post['topic'] ?? 'Untitled Post'));
        }
        if ($title === '') {
            $title = 'Untitled Post';
        }
        // Blogger title practical limit
        if (mb_strlen($title) > 500) {
            $title = mb_substr($title, 0, 500);
        }

        $content = (string) ($post['content_html'] ?? '');
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $content) ?? $content;
        if (trim(strip_tags($content)) === '') {
            return [
                'success' => false,
                'error' => 'Post content is empty. Generate or edit content before publishing.',
                'error_code' => 'EMPTY_CONTENT',
            ];
        }

        // Labels: strings only, max 20, max 200 chars each (Blogger limits)
        $labels = [];
        $rawTags = $post['tags'] ?? [];
        if (is_string($rawTags)) {
            $decoded = json_decode($rawTags, true);
            $rawTags = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $rawTags) ?: []);
        }
        if (is_array($rawTags)) {
            foreach ($rawTags as $tag) {
                if (is_array($tag)) {
                    $tag = implode(' ', $tag);
                }
                $tag = trim((string) $tag);
                $tag = preg_replace('/[\x00-\x1F]/u', '', $tag) ?? $tag;
                if ($tag !== '' && mb_strlen($tag) <= 200) {
                    $labels[] = $tag;
                }
            }
        }
        if (!empty($post['category'])) {
            $cat = trim((string) $post['category']);
            $cat = preg_replace('/[\x00-\x1F]/u', '', $cat) ?? $cat;
            if ($cat !== '' && mb_strlen($cat) <= 200) {
                $labels[] = $cat;
            }
        }
        $labels = array_values(array_unique($labels));
        $labels = array_slice($labels, 0, 20);

        $body = [
            'title' => $title,
            'content' => $content,
        ];
        // Only send labels when non-empty (empty array can trigger invalid argument on some blogs)
        if ($labels !== []) {
            $body['labels'] = $labels;
        }

        // isDraft=true => save as Blogger draft; user manually publishes from Blogger dashboard
        $url = self::BLOGGER_API . "/blogs/{$blogId}/posts/?isDraft=true";

        $result = $this->httpJson($url, $body, $accessToken);

        if (!$result['success']) {
            $errorMsg = $result['data']['error']['message'] ?? 'Blogger publish failed.';
            $errorCode = (string) ($result['data']['error']['code'] ?? $result['http_status'] ?? 'UNKNOWN');
            $errorDetails = $result['data']['error']['errors'] ?? null;

            Logger::error('Blogger publish failed', [
                'post_id' => $post['id'] ?? null,
                'blog_id' => $blogId,
                'error' => $errorMsg,
                'code' => $errorCode,
                'details' => $errorDetails,
                'title_len' => mb_strlen($title),
                'content_len' => strlen($content),
                'labels' => $labels,
                'http_status' => $result['http_status'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode,
            ];
        }

        $bloggerPostId = $result['data']['id'] ?? null;
        $bloggerUrl = $result['data']['url'] ?? null;

        Logger::info('Blogger publish succeeded', [
            'post_id' => $post['id'] ?? null,
            'blogger_post_id' => $bloggerPostId,
            'url' => $bloggerUrl,
        ]);

        return [
            'success' => true,
            'blogger_post_id' => $bloggerPostId,
            'blogger_url' => $bloggerUrl,
            'already_published' => false,
        ];
    }

    public function testConnection(): array
    {
        $adminId = (int) (Session::getAdminId() ?? 0);
        $connection = BloggerConnection::getActive($adminId > 0 ? $adminId : null);
        if ($connection === null) {
            return ['success' => false, 'error' => 'Not connected.'];
        }

        $accessToken = $this->ensureValidToken($connection);
        if ($accessToken === null) {
            return ['success' => false, 'error' => 'Token refresh failed.'];
        }

        $blogs = $this->listBlogs($accessToken);
        if (empty($blogs['items'])) {
            return ['success' => false, 'error' => 'No blogs found for this account.'];
        }

        return [
            'success' => true,
            'blogs' => array_map(static fn($b) => [
                'id' => $b['id'] ?? '',
                'name' => $b['name'] ?? '',
                'url' => $b['url'] ?? '',
            ], $blogs['items']),
            'current_blog_id' => $connection['blog_id'],
            'current_blog_name' => $connection['blog_name'],
        ];
    }

    public function disconnect(int $adminId): void
    {
        BloggerConnection::disconnect($adminId);
    }

    private function ensureValidToken(array $connection): ?string
    {
        $expiresAt = $connection['token_expires_at'] ?? null;

        // Token still valid (with 5 min buffer)
        if ($expiresAt && strtotime($expiresAt) > time() + 300) {
            return $connection['access_token'];
        }

        if (empty($connection['refresh_token'])) {
            Logger::error('No refresh token available for Blogger');
            return null;
        }

        $result = $this->httpPost(self::TOKEN_URL, [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $connection['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        if (!$result['success'] || empty($result['data']['access_token'])) {
            Logger::error('Blogger token refresh failed', ['response' => $result]);
            return null;
        }

        $newToken = $result['data']['access_token'];
        $newExpires = isset($result['data']['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $result['data']['expires_in'])
            : null;

        BloggerConnection::updateTokens((int) $connection['id'], $newToken, $newExpires);

        return $newToken;
    }

    private function listBlogs(string $accessToken): array
    {
        $result = $this->httpGet(self::BLOGGER_API . '/users/self/blogs', $accessToken);
        return $result['success'] ? ($result['data'] ?? []) : [];
    }

    private function httpPost(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        return [
            'success' => $httpStatus >= 200 && $httpStatus < 300,
            'http_status' => $httpStatus,
            'data' => $data,
        ];
    }

    private function httpGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        return [
            'success' => $httpStatus >= 200 && $httpStatus < 300,
            'http_status' => $httpStatus,
            'data' => $data,
        ];
    }

    private function httpJson(string $url, array $body, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        return [
            'success' => $httpStatus >= 200 && $httpStatus < 300,
            'http_status' => $httpStatus,
            'data' => $data,
        ];
    }
}
