<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Sanitizer;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\PostReview;
use App\Models\ScheduledPost;
use App\Models\AiUsageLog;
use App\Services\SchedulerService;
use App\Services\BloggerService;
use App\Services\AIService;
use App\Services\ImageService;

class PostController
{
    public function index(): void
    {
        $adminId = (int) Session::getAdminId();
        $filters = [
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'created_by' => $adminId,
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Post::search($filters, $page, 20);

        Response::view('posts.index', [
            'posts' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'statuses' => Post::STATUSES,
        ]);
    }

    public function create(): void
    {
        Response::view('posts.create', [
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        $validator = Validator::make($_POST)
            ->required('topic', 'Topic')
            ->max('topic', 500, 'Topic');

        if ($validator->fails()) {
            if ($this->isAjax()) {
                Response::error($validator->firstError() ?? 'Validation failed', 422, $validator->errors());
            }
            Session::flash('error', $validator->firstError());
            Response::redirect('/posts/create');
        }

        $secondary = [];
        if (!empty($_POST['secondary_keywords'])) {
            $raw = is_array($_POST['secondary_keywords'])
                ? $_POST['secondary_keywords']
                : explode(',', (string) $_POST['secondary_keywords']);
            $secondary = array_values(array_filter(array_map('trim', $raw)));
        }

        $tags = [];
        if (!empty($_POST['tags'])) {
            $raw = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', (string) $_POST['tags']);
            $tags = array_values(array_filter(array_map('trim', $raw)));
        }

        $adminId = Session::getAdminId();

        $lang = in_array($_POST['language'] ?? '', ['bn', 'en'], true) ? $_POST['language'] : 'bn';
        $defaultAudience = $lang === 'en'
            ? 'Readers in the United States, United Kingdom, Canada, and Australia'
            : 'General Bangladesh Readers';

        $postId = Post::create([
            'topic' => trim((string) $_POST['topic']),
            'primary_keyword' => trim((string) ($_POST['primary_keyword'] ?? '')),
            'secondary_keywords' => $secondary,
            'category' => trim((string) ($_POST['category'] ?? '')),
            'language' => $lang,
            'word_count' => max(300, min(5000, (int) ($_POST['word_count'] ?? 1000))),
            'target_audience' => trim((string) ($_POST['target_audience'] ?? $defaultAudience)),
            'writing_tone' => trim((string) ($_POST['writing_tone'] ?? 'Professional + Easy')),
            'tags' => $tags,
            'status' => 'DRAFT',
            'created_by' => $adminId,
        ]);

        ActivityLog::log('post_created', $adminId, 'post', $postId, 'Created new post draft');

        if ($this->isAjax()) {
            Response::success(['id' => $postId], 'Post created successfully');
        }

        // Auto-start generation if requested
        if (!empty($_POST['generate_now'])) {
            Response::redirect("/posts/{$postId}/generate");
        }

        Response::redirect("/posts/{$postId}");
    }

    public function show(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Session::flash('error', 'Post not found.');
            Response::redirect('/posts');
        }

        $this->assertPostOwner($post);

        $review = PostReview::getLatestForPost($id);
        $scheduled = ScheduledPost::findByPostId($id);
        $aiAttempts = AiUsageLog::getByPostId($id, 15);

        Response::view('posts.show', [
            'post' => $post,
            'review' => $review,
            'scheduled' => $scheduled,
            'aiAttempts' => $aiAttempts,
            'csrf' => Csrf::token(),
        ]);
    }

    public function edit(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Session::flash('error', 'Post not found.');
            Response::redirect('/posts');
        }

        $this->assertPostOwner($post);

        $review = PostReview::getLatestForPost($id);

        Response::view('posts.edit', [
            'post' => $post,
            'review' => $review,
            'csrf' => Csrf::token(),
        ]);
    }

    public function update(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        $data = [];

        if (isset($_POST['title'])) {
            $data['title'] = Sanitizer::escape(trim((string) $_POST['title']));
            // Actually store unescaped for content, escape only on output
            $data['title'] = trim((string) $_POST['title']);
        }
        if (isset($_POST['meta_description'])) {
            $data['meta_description'] = trim((string) $_POST['meta_description']);
        }
        if (isset($_POST['content_html'])) {
            $data['content_html'] = Sanitizer::clean((string) $_POST['content_html']);
        }
        if (isset($_POST['excerpt'])) {
            $data['excerpt'] = trim((string) $_POST['excerpt']);
        }
        if (isset($_POST['category'])) {
            $data['category'] = trim((string) $_POST['category']);
        }
        if (isset($_POST['tags'])) {
            $raw = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', (string) $_POST['tags']);
            $data['tags'] = array_values(array_filter(array_map('trim', $raw)));
        }

        if ($data !== []) {
            Post::update($id, $data);
            ActivityLog::log('post_edited', Session::getAdminId(), 'post', $id, 'Content edited');
        }

        if ($this->isAjax()) {
            Response::success(null, 'Post saved successfully');
        }

        Session::flash('success', 'Post saved successfully.');
        Response::redirect("/posts/{$id}");
    }

    public function generate(int $id): void
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        $post = Post::findById($id);
        if (!$post) {
            if ($this->isAjax()) {
                Response::error('Post not found', 404);
            }
            Session::flash('error', 'Post not found.');
            Response::redirect('/posts');
        }

        $this->assertPostOwner($post);

        if (!in_array($post['status'], ['DRAFT', 'GENERATED', 'HUMAN_REVIEW', 'REJECTED', 'FAILED'], true)) {
            $msg = 'Post cannot be generated in current status: ' . $post['status'];
            if ($this->isAjax()) {
                Response::error($msg, 400);
            }
            Session::flash('error', $msg);
            Response::redirect("/posts/{$id}");
        }

        Post::updateStatus($id, 'GENERATING', Session::getAdminId());

        $ai = new AIService();
        $result = $ai->generateContent($post, $id);

        if (!$result['success']) {
            Post::updateStatus($id, 'FAILED', Session::getAdminId(), $result['error']);
            Post::update($id, ['error_message' => $result['error']]);

            if ($this->isAjax()) {
                Response::error($result['error'] ?? 'AI generation failed', 500);
            }
            Session::flash('error', $result['error'] ?? 'AI generation failed. Check API keys in .env');
            Response::redirect("/posts/{$id}");
        }

        $data = $result['data'];

        // Auto-inject stock images when configured
        try {
            $imgSvc = new ImageService();
            if ($imgSvc->isConfigured() && !empty($data['content_html'])) {
                $kw = (string) ($post['primary_keyword'] ?? $data['title'] ?? $post['topic'] ?? '');
                $data['content_html'] = $imgSvc->injectIntoHtml(
                    (string) $data['content_html'],
                    $kw,
                    (string) ($data['title'] ?? '')
                );
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        Post::update($id, [
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'content_html' => $data['content_html'],
            'tags' => $data['tags'],
            'excerpt' => $data['excerpt'],
            'ai_provider_used' => $result['provider'],
            'ai_model_used' => $result['model'],
            'generation_attempts' => ((int) $post['generation_attempts']) + 1,
            'status' => 'GENERATED',
            'error_message' => null,
        ]);
        Post::logStatusChange($id, 'GENERATING', 'GENERATED', Session::getAdminId());

        ActivityLog::log('content_generated', Session::getAdminId(), 'post', $id, 'AI content generated via ' . $result['provider']);

        // Auto AI review + improve until score >= 9.0 (max 2 fix rounds)
        Post::updateStatus($id, 'AI_REVIEWING', Session::getAdminId());

        $reviewPayload = [
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'content_html' => $data['content_html'],
            'primary_keyword' => $post['primary_keyword'] ?? $post['topic'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'tags' => $data['tags'] ?? [],
            'ai_provider_used' => $result['provider'],
        ];

        $reviewResult = $ai->reviewAndImprove($reviewPayload, $id, 9.0, 2, $result['provider']);

        if ($reviewResult['success'] && !empty($reviewResult['data'])) {
            $final = $reviewResult['data'];
            $rd = $final['_review'] ?? null;


            // Re-apply relevant stock images after improve
            try {
                $imgSvc = new ImageService();
                if ($imgSvc->isConfigured() && !empty($final['content_html'])) {
                    $final['content_html'] = $imgSvc->injectIntoHtml(
                        (string) $final['content_html'],
                        (string) ($post['primary_keyword'] ?? $post['topic'] ?? ''),
                        (string) ($final['title'] ?? '')
                    );
                }
            } catch (\Throwable $e) {
            }

            // Persist improved content if changed
            Post::update($id, [
                'title' => $final['title'] ?? $data['title'],
                'meta_description' => $final['meta_description'] ?? $data['meta_description'],
                'content_html' => $final['content_html'] ?? $data['content_html'],
                'excerpt' => $final['excerpt'] ?? ($data['excerpt'] ?? null),
                'tags' => $final['tags'] ?? $data['tags'],
            ]);

            if (is_array($rd)) {
                PostReview::create([
                    'post_id' => $id,
                    'provider' => $reviewResult['provider'],
                    'model' => $reviewResult['model'],
                    'score' => $rd['score'],
                    'grammar_score' => $rd['grammar_score'],
                    'seo_score' => $rd['seo_score'],
                    'readability_score' => $rd['readability_score'],
                    'structure_score' => $rd['structure_score'],
                    'problems' => $rd['problems'],
                    'suggestions' => $rd['suggestions'],
                    'required_changes' => $rd['required_changes'],
                    'verdict' => $rd['verdict'],
                    'raw_response' => json_encode([
                        'detailed_notes' => $rd['detailed_notes'] ?? '',
                        'rounds' => $reviewResult['rounds'] ?? 1,
                        'all_reviews' => $reviewResult['reviews'] ?? [],
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                Post::update($id, [
                    'ai_score' => $rd['score'],
                    'status' => 'HUMAN_REVIEW',
                ]);
                Post::logStatusChange($id, 'AI_REVIEWING', 'HUMAN_REVIEW', Session::getAdminId());
                ActivityLog::log(
                    'ai_review_completed',
                    Session::getAdminId(),
                    'post',
                    $id,
                    'AI score: ' . $rd['score'] . ' (rounds: ' . ($reviewResult['rounds'] ?? 1) . ')'
                );
            } else {
                Post::updateStatus($id, 'HUMAN_REVIEW', Session::getAdminId(), 'Review data missing');
            }
        } else {
            Post::updateStatus($id, 'HUMAN_REVIEW', Session::getAdminId(), 'AI review failed, proceeding to human review');
        }

        if ($this->isAjax()) {
            Response::success(['id' => $id, 'status' => 'HUMAN_REVIEW'], 'Content generated and reviewed successfully');
        }

        Session::flash('success', 'Content generated successfully.');
        Response::redirect("/posts/{$id}");
    }

    public function review(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        Post::updateStatus($id, 'AI_REVIEWING', Session::getAdminId());

        $ai = new AIService();
        $payload = [
            'title' => $post['title'] ?? '',
            'meta_description' => $post['meta_description'] ?? '',
            'content_html' => $post['content_html'] ?? '',
            'primary_keyword' => $post['primary_keyword'] ?? $post['topic'] ?? '',
            'excerpt' => $post['excerpt'] ?? '',
            'tags' => $post['tags'] ?? [],
            'ai_provider_used' => $post['ai_provider_used'] ?? null,
        ];

        $reviewResult = $ai->reviewAndImprove($payload, $id, 9.0, 2, $post['ai_provider_used'] ?? null);

        if (!$reviewResult['success'] || empty($reviewResult['data'])) {
            Post::updateStatus($id, 'HUMAN_REVIEW', Session::getAdminId(), $reviewResult['error'] ?? 'Review failed');
            if ($this->isAjax()) {
                Response::error($reviewResult['error'] ?? 'AI review failed', 500);
            }
            Session::flash('error', $reviewResult['error'] ?? 'AI review failed');
            Response::redirect("/posts/{$id}");
        }

        $final = $reviewResult['data'];
        $rd = $final['_review'] ?? null;

        Post::update($id, [
            'title' => $final['title'] ?? $post['title'],
            'meta_description' => $final['meta_description'] ?? $post['meta_description'],
            'content_html' => $final['content_html'] ?? $post['content_html'],
            'excerpt' => $final['excerpt'] ?? $post['excerpt'],
            'tags' => $final['tags'] ?? $post['tags'],
        ]);

        if (is_array($rd)) {
            PostReview::create([
                'post_id' => $id,
                'provider' => $reviewResult['provider'],
                'model' => $reviewResult['model'],
                'score' => $rd['score'],
                'grammar_score' => $rd['grammar_score'],
                'seo_score' => $rd['seo_score'],
                'readability_score' => $rd['readability_score'],
                'structure_score' => $rd['structure_score'],
                'problems' => $rd['problems'],
                'suggestions' => $rd['suggestions'],
                'required_changes' => $rd['required_changes'],
                'verdict' => $rd['verdict'],
                'raw_response' => json_encode([
                    'detailed_notes' => $rd['detailed_notes'] ?? '',
                    'rounds' => $reviewResult['rounds'] ?? 1,
                    'all_reviews' => $reviewResult['reviews'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
            ]);

            Post::update($id, [
                'ai_score' => $rd['score'],
                'status' => 'HUMAN_REVIEW',
            ]);
            Post::logStatusChange($id, 'AI_REVIEWING', 'HUMAN_REVIEW', Session::getAdminId());
            ActivityLog::log('ai_review_completed', Session::getAdminId(), 'post', $id, 'AI score: ' . $rd['score']);
        } else {
            Post::updateStatus($id, 'HUMAN_REVIEW', Session::getAdminId());
        }

        if ($this->isAjax()) {
            Response::success(['review' => $rd, 'rounds' => $reviewResult['rounds'] ?? 1], 'AI review completed');
        }

        Session::flash('success', 'AI review completed' . (isset($rd['score']) ? ' (score: ' . $rd['score'] . ')' : '') . '.');
        Response::redirect("/posts/{$id}");
    }

    public function approve(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        if (!in_array($post['status'], ['HUMAN_REVIEW', 'GENERATED', 'REJECTED'], true)) {
            Response::error('Post cannot be approved in current status', 400);
        }

        if (empty($post['title']) || empty($post['content_html'])) {
            Response::error('Post must have title and content before approval', 400);
        }

        $adminId = Session::getAdminId();
        Post::update($id, [
            'status' => 'APPROVED',
            'approved_by' => $adminId,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        Post::logStatusChange($id, $post['status'], 'APPROVED', $adminId);
        ActivityLog::log('post_approved', $adminId, 'post', $id, 'Post approved');

        if ($this->isAjax()) {
            Response::success(['id' => $id, 'status' => 'APPROVED'], 'Post approved');
        }

        Session::flash('success', 'Post approved. You can now schedule it.');
        Response::redirect("/posts/{$id}");
    }

    public function reject(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        $note = trim((string) ($_POST['note'] ?? 'Rejected by admin'));
        Post::updateStatus($id, 'REJECTED', Session::getAdminId(), $note);
        ActivityLog::log('post_rejected', Session::getAdminId(), 'post', $id, $note);

        if ($this->isAjax()) {
            Response::success(null, 'Post rejected');
        }

        Session::flash('success', 'Post rejected.');
        Response::redirect("/posts/{$id}");
    }

    public function schedule(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        if ($post['status'] !== 'APPROVED' && $post['status'] !== 'SCHEDULED') {
            Response::error('Only approved posts can be scheduled', 400);
        }

        $date = trim((string) ($_POST['publish_date'] ?? ''));
        $time = trim((string) ($_POST['publish_time'] ?? '10:00'));
        $timezone = trim((string) ($_POST['timezone'] ?? 'Asia/Dhaka'));

        if ($date === '') {
            Response::error('Publish date is required', 422);
        }

        $scheduledAt = $date . ' ' . $time . ':00';

        // Validate datetime
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt, new \DateTimeZone($timezone));
        if (!$dt) {
            Response::error('Invalid date/time format', 422);
        }

        // Convert to server time (UTC storage or Asia/Dhaka)
        $dt->setTimezone(new \DateTimeZone('Asia/Dhaka'));
        $scheduledAtDb = $dt->format('Y-m-d H:i:s');

        Post::update($id, [
            'status' => 'SCHEDULED',
            'scheduled_at' => $scheduledAtDb,
        ]);
        Post::logStatusChange($id, $post['status'], 'SCHEDULED', Session::getAdminId());

        ScheduledPost::create($id, $scheduledAtDb, $timezone);
        ActivityLog::log('post_scheduled', Session::getAdminId(), 'post', $id, "Scheduled for {$scheduledAtDb}");

        if ($this->isAjax()) {
            Response::success(['scheduled_at' => $scheduledAtDb], 'Post scheduled successfully');
        }

        Session::flash('success', 'Post scheduled for ' . $scheduledAtDb);
        Response::redirect("/posts/{$id}");
    }

    public function suggestTopics(): void
    {
        $keyword = trim((string) ($_POST['keyword'] ?? $_GET['keyword'] ?? ''));
        $language = in_array($_POST['language'] ?? '', ['bn', 'en'], true) ? $_POST['language'] : 'bn';

        if ($keyword === '') {
            Response::error('Keyword is required', 422);
        }

        $ai = new AIService();
        $result = $ai->suggestTopics($keyword, $language);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'Failed to generate topics', 500);
        }

        Response::success($result['data'], 'Topics generated');
    }

    public function optimizeSeo(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }

        $this->assertPostOwner($post);

        $ai = new AIService();
        $result = $ai->optimizeSeo($post);

        if (!$result['success']) {
            Response::error($result['error'] ?? 'SEO optimization failed', 500);
        }

        Response::success($result['data'], 'SEO suggestions generated');
    }


    /**
     * Publish immediately (no wait for cron).
     */


    public function publishNow(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            if ($this->isAjax()) {
                Response::error('Post not found', 404);
            }
            Session::flash('error', 'পোস্টটি খুঁজে পাওয়া যায়নি (Post not found)।');
            Response::redirect('/posts');
        }

        $this->assertPostOwner($post);

        if (!in_array($post['status'], ['APPROVED', 'SCHEDULED', 'FAILED'], true)) {
            $msg = 'পাবলিশ করার জন্য পোস্টটি Approved অথবা Scheduled হতে হবে (বর্তমান স্ট্যাটাস: ' . $post['status'] . ')।';
            if ($this->isAjax()) {
                Response::error($msg, 400);
            }
            Session::flash('error', $msg);
            Response::redirect("/posts/{$id}");
        }

        // Ensure a scheduled_posts row exists so SchedulerService can lock it
        $scheduled = ScheduledPost::findByPostId($id);
        if (!$scheduled) {
            $now = (new \DateTime('now', new \DateTimeZone('Asia/Dhaka')))->format('Y-m-d H:i:s');
            ScheduledPost::create($id, $now, 'Asia/Dhaka');
            Post::update($id, ['status' => 'SCHEDULED', 'scheduled_at' => $now]);
        } else {
            // Reset lock/status so publish can proceed
            ScheduledPost::resetToPending((int) $scheduled['id']);
            if ($post['status'] !== 'SCHEDULED') {
                Post::update($id, ['status' => 'SCHEDULED']);
            }
        }

        $scheduler = new SchedulerService();
        $outcome = $scheduler->publishPost($id);

        if ($outcome === 'success') {
            ActivityLog::log('post_published_now', Session::getAdminId(), 'post', $id, 'Published immediately by user');
            if ($this->isAjax()) {
                Response::success(['status' => 'PUBLISHED'], 'Post published successfully');
            }
            Session::flash('success', 'Post published successfully to Blogger.');
            Response::redirect("/posts/{$id}");
        }

        $fresh = Post::findById($id);
        $err = $fresh['error_message'] ?? 'Publish failed';
        if ($this->isAjax()) {
            Response::error($err, 500);
        }
        Session::flash('error', 'Publish failed: ' . $err);
        Response::redirect("/posts/{$id}");
    }

    /**
     * Upload an image to hosting and return public URL (for content image replace).
     */
    public function uploadImage(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }
        $this->assertPostOwner($post);

        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            Response::error('No image uploaded', 422);
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload error code: ' . $file['error'], 422);
        }

        $maxBytes = 3 * 1024 * 1024; // 3 MB
        if ($file['size'] > $maxBytes) {
            Response::error('Image too large (max 3 MB)', 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            Response::error('Only JPG, PNG, GIF, WEBP allowed', 422);
        }

        $ext = $allowed[$mime];
        $dir = dirname(__DIR__, 2) . '/uploads/posts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'post' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::error('Failed to save uploaded file', 500);
        }

        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        $publicUrl = $baseUrl . '/uploads/posts/' . $name;

        ActivityLog::log('image_uploaded', Session::getAdminId(), 'post', $id, 'Image uploaded: ' . $name);

        Response::success(['url' => $publicUrl], 'Image uploaded');
    }

    /**
     * Get list of indexed images currently in the post HTML
     */
    public function getImages(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }
        $this->assertPostOwner($post);

        $imgSvc = new ImageService();
        $images = $imgSvc->listImageSrcsIndexed((string) ($post['content_html'] ?? ''));

        Response::success([
            'post_id'       => $id,
            'is_configured' => $imgSvc->isConfigured(),
            'images'        => $images,
            'count'         => count($images),
        ]);
    }

    /**
     * Replace images via API (all, specific index, or multiple selected indexes)
     */
    public function replaceImages(int $id): void
    {
        $post = Post::findById($id);
        if (!$post) {
            Response::error('Post not found', 404);
        }
        $this->assertPostOwner($post);

        $imgSvc = new ImageService();
        if (!$imgSvc->isConfigured()) {
            Response::error('Image API is not configured. Please add UNSPLASH_ACCESS_KEY or PEXELS_API_KEY in .env', 400);
        }

        // Support JSON or POST body
        $input = $_POST;
        if (empty($input)) {
            $rawJson = file_get_contents('php://input');
            if ($rawJson) {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded)) {
                    $input = $decoded;
                }
            }
        }

        $html = (string) ($post['content_html'] ?? '');
        if ($html === '') {
            Response::error('This post has no content or images to replace.', 400);
        }

        $query = trim((string) ($input['query'] ?? ''));
        $isRandomQuery = !empty($input['random_query']) || strtoupper($query) === 'RANDOM';
        $mode = trim((string) ($input['mode'] ?? 'all')); // 'all' | 'index' | 'indexes'

        $index = isset($input['index']) && $input['index'] !== '' ? (int) $input['index'] : null;

        $indexes = [];
        if (!empty($input['indexes'])) {
            $rawIdx = is_array($input['indexes']) ? $input['indexes'] : explode(',', (string) $input['indexes']);
            $indexes = array_values(array_map('intval', array_filter($rawIdx, 'is_numeric')));
        }

        // If specific indexes provided, ensure mode is set to 'indexes'
        if ($indexes !== [] && $mode !== 'index') {
            $mode = 'indexes';
        }

        // If query is empty and not explicitly random, default to post keyword or topic
        if ($query === '' && !$isRandomQuery) {
            $query = (string) ($post['primary_keyword'] ?? $post['topic'] ?? '');
        }

        try {
            $result = $imgSvc->replaceImagesInHtml(
                $html,
                $query,
                $mode,
                $index,
                $indexes,
                $isRandomQuery
            );
        } catch (\Throwable $e) {
            Response::error('Image replacement failed: ' . $e->getMessage(), 500);
            return;
        }

        if ($result['changed'] > 0) {
            Post::update($id, [
                'content_html' => $result['html'],
            ]);

            $logMsg = sprintf(
                'Replaced %d image(s) using query "%s" (mode: %s)',
                $result['changed'],
                $result['query_used'],
                $mode
            );
            ActivityLog::log('images_replaced', Session::getAdminId(), 'post', $id, $logMsg);
        }

        $updatedImages = $imgSvc->listImageSrcsIndexed($result['html']);

        Response::success([
            'changed'      => $result['changed'],
            'query_used'   => $result['query_used'],
            'images'       => $result['images'],
            'all_images'   => $updatedImages,
            'content_html' => $result['html'],
        ], sprintf('%d টি ছবি সফলভাবে পরিবর্তন করা হয়েছে!', $result['changed']));
    }

    private function isAjax(): bool

    {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );
    }


    private function assertPostOwner(array $post): void
    {
        $adminId = (int) Session::getAdminId();
        if ((int) ($post['created_by'] ?? 0) !== $adminId) {
            Session::flash('error', 'You do not have access to this post.');
            Response::redirect('/posts');
        }
    }
}
