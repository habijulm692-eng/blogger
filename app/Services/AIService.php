<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Logger;
use App\Helpers\Sanitizer;
use App\Models\AiUsageLog;
use App\Models\AiProviderConfig;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\GeminiService;
use App\Services\AI\GroqService;
use App\Services\AI\OpenAICompatibleService;

class AIService
{
    /** @var AIProviderInterface[] All configured providers in priority order */
    private array $providers = [];

    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
        $this->initProviders();
    }

    private function initProviders(): void
    {
        $aiConfig    = $this->config['ai']['providers'] ?? [];
        $temperature = $this->config['ai']['temperature'] ?? 0.7;
        $maxTokens   = $this->config['ai']['max_tokens'] ?? 4096;

        // Try to get order from DB (ai_providers table)
        $order = $this->resolveProviderOrder();

        foreach ($order as $name) {
            $cfg = $aiConfig[$name] ?? [];
            $cfg['name']        = $name;
            $cfg['temperature'] = $temperature;
            $cfg['max_tokens']  = $maxTokens;

            $provider = match ($name) {
                'gemini', 'gemini_primary', 'gemini_secondary' => new GeminiService($cfg),
                'groq'      => new GroqService($cfg),
                'sambanova', 'xkero', 'apinex' => new OpenAICompatibleService(array_merge($cfg, [
                    'name' => $name,
                ])),
                default => null,
            };

            if ($provider !== null && $provider->isConfigured()) {
                $this->providers[] = $provider;
            }
        }
    }

    /**
     * Resolve provider order: DB first, fallback to hardcoded.
     */
    private function resolveProviderOrder(): array
    {
        try {
            $order = AiProviderConfig::getEnabledOrder();
            if (!empty($order)) {
                return $order;
            }
        } catch (\Throwable $e) {
            Logger::warning('AiProviderConfig::getEnabledOrder failed, using defaults', ['error' => $e->getMessage()]);
        }
        // Fallback order (both Gemini models prioritized)
        return ['gemini_primary', 'gemini_secondary', 'sambanova', 'xkero', 'apinex', 'groq'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate content — uses the FIRST available provider.
     */
    public function generateContent(array $postData, ?int $postId = null, array $options = []): array
    {
        $prompt = $this->buildGenerationPrompt($postData);
        $options = array_merge(['topic' => $postData['topic'] ?? $postData['title'] ?? ''], $options);
        return $this->executeWithFallback('generate', $prompt, $postId, function (string $c) use ($postData) {
            return $this->parseGenerationResponse($c, $postData);
        }, $options);
    }

    /**
     * Find index of a provider by name in the configured $this->providers list.
     */
    public function findProviderIndex(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        foreach ($this->providers as $idx => $p) {
            if (strcasecmp($p->getName(), $name) === 0) {
                return $idx;
            }
        }
        // If searching for legacy 'gemini', match 'gemini_primary'
        if (strcasecmp($name, 'gemini') === 0) {
            foreach ($this->providers as $idx => $p) {
                if (strcasecmp($p->getName(), 'gemini_primary') === 0) {
                    return $idx;
                }
            }
        }
        return null;
    }

    /**
     * Build an execution sequence prioritizing other providers in rotation,
     * placing excluded / previous providers at the very end as last-resort fallback.
     *
     * Rules:
     * - One model completes a step -> next step is handed to another model.
     * - If that model fails -> falls back to the third/subsequent models in rotation.
     * - Only if all other active models fail does it fall back to the creator / previous model.
     *
     * @param int   $preferredStartIdx Start trying from this index
     * @param int[] $excludeIdxs       Indices that just performed a step (try last)
     * @return int[] Ordered provider indices to try
     */
    public function buildHandoffSequence(int $preferredStartIdx, array $excludeIdxs = []): array
    {
        $total = count($this->providers);
        if ($total <= 1) {
            return $total === 1 ? [0] : [];
        }

        $nonExcluded = [];
        $fallback = [];

        for ($i = 0; $i < $total; $i++) {
            $idx = ($preferredStartIdx + $i) % $total;
            if (in_array($idx, $excludeIdxs, true)) {
                if (!in_array($idx, $fallback, true)) {
                    $fallback[] = $idx;
                }
            } else {
                if (!in_array($idx, $nonExcluded, true)) {
                    $nonExcluded[] = $idx;
                }
            }
        }

        return array_merge($nonExcluded, $fallback);
    }

    /**
     * Review + Improve with DIFFERENT model sequential handoff pipeline:
     * - Model A generates the content.
     * - Model B (the next enabled model) reviews it (Model A is excluded initially).
     * - If Model B fails during review -> fallback to Model C (the 3rd model), etc.
     * - If ALL other models fail during review -> only then fallback to Model A (creator).
     * - If score < 9.0: Model C improves the content (Model B reviewer excluded).
     * - Round 2 review hands off to the next model in rotation.
     */
    public function reviewAndImprove(
        array $postData,
        ?int $postId = null,
        float $minScore = 8.0,
        int $maxRounds = 2,
        ?string $generatorProvider = null,
        float $targetScore = 9.0
    ): array {
        $reviews      = [];
        $current      = $postData;
        $lastProvider = null;
        $lastModel    = null;

        $totalProviders = count($this->providers);

        // Identify creator index from argument or post data
        $creatorName = $generatorProvider ?? ($postData['ai_provider_used'] ?? null);
        $creatorIdx = $this->findProviderIndex($creatorName) ?? 0;

        // The model that just performed the previous step (starts with creator)
        $previousPerformerIdx = $creatorIdx;

        for ($round = 1; $round <= $maxRounds + 1; $round++) {
            // STEP 1: REVIEW
            // Rule: Creator / previous model must NOT review if other models are available.
            // Start from the next model in rotation:
            $preferredReviewer = ($previousPerformerIdx + 1) % max(1, $totalProviders);
            $reviewSequence = $this->buildHandoffSequence($preferredReviewer, [$previousPerformerIdx, $creatorIdx]);

            $review = $this->executeWithSequence(
                'review',
                $this->buildReviewPrompt($current),
                $postId,
                fn(string $c) => $this->parseReviewResponse($c),
                $reviewSequence
            );

            if (!$review['success'] || empty($review['data'])) {
                return [
                    'success'  => false,
                    'data'     => $current,
                    'provider' => $review['provider'] ?? $lastProvider,
                    'model'    => $review['model'] ?? $lastModel,
                    'error'    => $review['error'] ?? 'Review failed',
                    'rounds'   => $round,
                    'reviews'  => $reviews,
                ];
            }

            $rd              = $review['data'];
            $reviewerIdx     = $review['_provider_index'] ?? $preferredReviewer;
            $lastProvider    = $review['provider'];
            $lastModel       = $review['model'];
            $score           = (float) ($rd['score'] ?? 0);
            $rd['_round']    = $round;
            $rd['_provider'] = $lastProvider;
            $rd['_model']    = $lastModel;
            $reviews[]       = $rd;

            // Pass check: 8.0 or above passes immediately
            if ($score >= $minScore || strtoupper((string) ($rd['verdict'] ?? '')) === 'APPROVE') {
                return [
                    'success'       => true,
                    'data'          => array_merge($current, ['_review' => $rd]),
                    'provider'      => $lastProvider,
                    'model'         => $lastModel,
                    'error'         => null,
                    'rounds'        => $round,
                    'reviews'       => $reviews,
                    'initial_score' => $reviews[0]['score'] ?? $score,
                    'passed'        => true,
                ];
            }

            if ($round > $maxRounds) {
                break;
            }

            // STEP 2: IMPROVE (when score < 8.0, aim for 9.0+)
            // Rule: Reviewer model must NOT improve! Prefer a 3rd model (or next in line):
            $preferredImprover = ($reviewerIdx + 1) % max(1, $totalProviders);
            $improveSequence = $this->buildHandoffSequence($preferredImprover, [$reviewerIdx, $creatorIdx]);

            $fix = $this->executeWithSequence(
                'improve',
                $this->buildImprovePrompt($current, $rd, $minScore, $targetScore),
                $postId,
                fn(string $c) => $this->parseImproveResponse($c, $current),
                $improveSequence
            );

            if (!$fix['success'] || empty($fix['data'])) {
                break;
            }

            $fixed                       = $fix['data'];
            $improverIdx                 = $fix['_provider_index'] ?? $preferredImprover;
            $current['title']            = $fixed['title']            ?? $current['title'];
            $current['meta_description'] = $fixed['meta_description'] ?? $current['meta_description'];
            $current['content_html']     = $fixed['content_html']     ?? $current['content_html'];
            $current['excerpt']          = $fixed['excerpt']          ?? ($current['excerpt'] ?? '');
            if (!empty($fixed['tags']) && is_array($fixed['tags'])) {
                $current['tags'] = $fixed['tags'];
            }
            $lastProvider = $fix['provider'] ?? $lastProvider;
            $lastModel    = $fix['model']    ?? $lastModel;

            // Next round review will hand off from this improver
            $previousPerformerIdx = $improverIdx;
        }

        $finalReview = $reviews[array_key_last($reviews)] ?? null;
        return [
            'success'       => true,
            'data'          => array_merge($current, ['_review' => $finalReview]),
            'provider'      => $lastProvider,
            'model'         => $lastModel,
            'error'         => null,
            'rounds'        => count($reviews),
            'reviews'       => $reviews,
            'initial_score' => $reviews[0]['score'] ?? null,
            'passed'        => ((float) ($finalReview['score'] ?? 0)) >= $minScore,
        ];
    }

    /**
     * Review content with automatic handoff to next model.
     *
     * @return array{success: bool, data: ?array, provider: ?string, model: ?string, error: ?string}
     */
    public function reviewContent(array $postData, ?int $postId = null, ?string $generatorProvider = null): array
    {
        $creatorName = $generatorProvider ?? ($postData['ai_provider_used'] ?? null);
        $creatorIdx = $this->findProviderIndex($creatorName) ?? 0;
        $total = count($this->providers);
        $sequence = $total > 1
            ? $this->buildHandoffSequence(($creatorIdx + 1) % $total, [$creatorIdx])
            : [0];

        $prompt = $this->buildReviewPrompt($postData);
        return $this->executeWithSequence('review', $prompt, $postId, fn(string $c) => $this->parseReviewResponse($c), $sequence);
    }

    public function improveContent(array $postData, array $reviewData, ?int $postId = null, ?string $reviewerProvider = null): array
    {
        $creatorName = $postData['ai_provider_used'] ?? null;
        $creatorIdx = $this->findProviderIndex($creatorName) ?? 0;
        $reviewerIdx = $this->findProviderIndex($reviewerProvider);
        $total = count($this->providers);

        $exclude = array_values(array_filter([$reviewerIdx, $creatorIdx], static fn($v) => $v !== null));
        $startIdx = ($reviewerIdx !== null) ? (($reviewerIdx + 1) % max(1, $total)) : (($creatorIdx + 1) % max(1, $total));
        $sequence = $this->buildHandoffSequence($startIdx, $exclude);

        $prompt = $this->buildImprovePrompt($postData, $reviewData);
        return $this->executeWithSequence('improve', $prompt, $postId, fn(string $c) => $this->parseImproveResponse($c, $postData), $sequence);
    }

    /**
     * Suggest topic ideas.
     *
     * @return array{success: bool, data: ?array, provider: ?string, model: ?string, error: ?string}
     */
    public function suggestTopics(string $keyword, string $language = 'bn', int $count = 5): array
    {
        $prompt = $this->buildTopicSuggestionPrompt($keyword, $language, $count);
        return $this->executeWithFallback('topic_suggest', $prompt, null, function (string $content) {
            return $this->parseTopicSuggestions($content);
        });
    }

    /**
     * SEO optimization suggestions.
     *
     * @return array{success: bool, data: ?array, provider: ?string, model: ?string, error: ?string}
     */
    public function optimizeSeo(array $postData): array
    {
        $prompt = $this->buildSeoPrompt($postData);
        return $this->executeWithFallback('seo_optimize', $prompt, $postData['id'] ?? null, function (string $content) {
            return $this->parseSeoResponse($content);
        });
    }

    /**
     * Chat assistant for content strategy Q&A.
     */
    public function chat(string $userMessage, array $history = []): array
    {
        $lines = [];
        foreach (array_slice($history, -12) as $h) {
            $role    = ($h['role'] ?? '') === 'assistant' ? 'Assistant' : 'User';
            $lines[] = $role . ': ' . ($h['message'] ?? '');
        }
        $hist   = $lines ? implode("\n", $lines) . "\n" : '';
        $prompt = <<<PROMPT
You are the AI Blogger assistant. Help the admin with content ideas, SEO, scheduling, Blogger publishing, and improving posts.
Be concise and practical. Reply in the same language the user uses (Bengali or English).

Conversation:
{$hist}User: {$userMessage}

A:
PROMPT;
        return $this->executeWithFallback('chat', $prompt, null, function (string $content) {
            $content = trim($content);
            // strip accidental json fences
            if (str_starts_with($content, '```')) {
                $content = preg_replace('/^```(?:json|text)?\s*/i', '', $content) ?? $content;
                $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            }
            return ['reply' => trim($content)];
        });
    }

    /**
     * Generic JSON completion for internal tools (source suggestions etc.)
     */
    public function completeJson(string $prompt, string $operation = 'json'): array
    {
        return $this->executeWithFallback($operation, $prompt, null, function (string $content) {
            $content  = trim($content);
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $m)) {
                $content = trim($m[1]);
            }
            $start    = strpos($content, '[');
            $startObj = strpos($content, '{');
            if ($start === false && $startObj === false) {
                return null;
            }
            if ($start === false || ($startObj !== false && $startObj < $start)) {
                $start = $startObj;
                $end   = strrpos($content, '}');
            } else {
                $end = strrpos($content, ']');
            }
            if ($end === false) {
                return null;
            }
            $json    = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : null;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE EXECUTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Standard fallback: try all providers in order.
     */
    private function executeWithFallback(
        string $operation,
        string $prompt,
        ?int $postId,
        callable $parser,
        array $options = []
    ): array {
        $indices = range(0, max(0, count($this->providers) - 1));
        return $this->executeWithSequence($operation, $prompt, $postId, $parser, $indices, $options);
    }

    /**
     * Execute starting from a given provider index, optionally skipping some.
     * Falls back to skipped providers if all non-skipped fail.
     *
     * @param int   $startIdx  Start scanning from this index
     * @param int[] $skipIdxs  Provider indices to skip first (try them last as fallback)
     */
    private function executeFromIndex(
        string $operation,
        string $prompt,
        ?int $postId,
        callable $parser,
        int $startIdx = 0,
        array $skipIdxs = [],
        array $options = []
    ): array {
        $sequence = $this->buildHandoffSequence($startIdx, $skipIdxs);
        return $this->executeWithSequence($operation, $prompt, $postId, $parser, $sequence, $options);
    }

    /**
     * Core execution: Try providers in exact passed sequence, with auto-retry and logging.
     *
     * @param int[] $orderedIdxs
     */
    /**
     * Check if an error message represents high demand / overloaded condition.
     * Specifically handles:
     * - "This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later."
     * - Overloaded, temporary demand spikes, resource exhaustion due to high traffic.
     */
    public function isHighDemandError(?string $error): bool
    {
        if ($error === null || trim($error) === '') {
            return false;
        }

        $lower = strtolower($error);

        if (str_contains($lower, 'experiencing high demand')) {
            return true;
        }
        if (str_contains($lower, 'spikes in demand')) {
            return true;
        }
        if (str_contains($lower, 'high demand')) {
            return true;
        }
        if (str_contains($lower, 'model is overloaded') || str_contains($lower, 'overloaded')) {
            return true;
        }
        if (str_contains($lower, 'temporarily unavailable') || str_contains($lower, 'service unavailable')) {
            return true;
        }
        if (str_contains($lower, 'resource_exhausted') && (str_contains($lower, 'quota') || str_contains($lower, 'rate') || str_contains($lower, 'demand') || str_contains($lower, 'temporary'))) {
            return true;
        }

        return false;
    }

    /**
     * Call provider with automatic retry for high-demand errors only.
     * Retries up to 2 times, waiting 3 seconds before each retry attempt.
     */
    private function callProviderWithRetry(AIProviderInterface $provider, string $prompt, array $options = []): array
    {
        $result = $provider->generate($prompt, $options);
        $maxRetries = 2;
        $attempt = 0;

        while (!$result['success'] && $this->isHighDemandError($result['error'] ?? '') && $attempt < $maxRetries) {
            $attempt++;
            Logger::warning("AI provider high demand detected [{$provider->getName()}]. Waiting 3 seconds before retry (Attempt {$attempt}/{$maxRetries})...", [
                'provider' => $provider->getName(),
                'error'    => $result['error'] ?? '',
                'attempt'  => $attempt,
            ]);

            sleep(3);

            $result = $provider->generate($prompt, $options);

            if ($result['success']) {
                Logger::info("AI provider high demand retry succeeded on attempt {$attempt} [{$provider->getName()}]", [
                    'provider' => $provider->getName(),
                    'attempt'  => $attempt,
                ]);
            }
        }

        return $result;
    }

    /**
     * Core execution: Try providers in exact passed sequence, with auto-retry and logging.
     *
     * @param int[] $orderedIdxs
     */
    public function executeWithSequence(
        string $operation,
        string $prompt,
        ?int $postId,
        callable $parser,
        array $orderedIdxs,
        array $options = []
    ): array {
        if ($this->providers === []) {
            return [
                'success'  => false,
                'data'     => null,
                'provider' => null,
                'model'    => null,
                'error'    => 'No AI providers are configured. Please add API keys in Settings.',
            ];
        }

        $lastError = 'All AI providers failed.';
        $attempts  = [];

        foreach ($orderedIdxs as $providerIdx) {
            $provider    = $this->providers[$providerIdx];
            $requestTime = date('Y-m-d H:i:s');
            $startMs     = (int) (microtime(true) * 1000);

            Logger::info('AI request started', [
                'provider'  => $provider->getName(),
                'operation' => $operation,
                'post_id'   => $postId,
                'index'     => $providerIdx,
            ]);

            $result = $this->callProviderWithRetry($provider, $prompt, $options);

            $endMs        = (int) (microtime(true) * 1000);
            $responseTime = date('Y-m-d H:i:s');
            $duration     = $endMs - $startMs;

            $logData = [
                'provider'          => $provider->getName(),
                'model'             => $provider->getModel(),
                'operation'         => $operation,
                'post_id'           => $postId,
                'request_time'      => $requestTime,
                'response_time'     => $responseTime,
                'duration_ms'       => $duration,
                'http_status'       => $result['http_status'],
                'prompt_tokens'     => $result['tokens']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['tokens']['completion_tokens'] ?? null,
                'total_tokens'      => $result['tokens']['total_tokens'] ?? null,
            ];

            if (!$result['success']) {
                $logData['status']        = 'FAILED';
                $logData['error_message'] = $result['error'];
                AiUsageLog::create($logData);

                $attempts[]  = [
                    'provider' => $provider->getName(),
                    'model'    => $provider->getModel(),
                    'status'   => 'FAILED',
                    'error'    => $result['error'] ?? 'Unknown error',
                ];
                $lastError = $result['error'] ?? 'Unknown error';
                Logger::warning('AI provider failed, trying next', [
                    'provider' => $provider->getName(),
                    'error'    => $result['error'],
                ]);
                continue;
            }

            // Parse JSON from response
            $parsed = $parser($result['content']);

            if ($parsed === null) {
                // If operation is generate or improve and content is present, treat raw response as article content directly
                if (in_array($operation, ['generate', 'improve'], true) && trim((string)$result['content']) !== '') {
                    $rawHtml = $this->cleanRawArticleContent($result['content']);
                    if (strlen(trim($rawHtml)) > 20) {
                        Logger::info("AI JSON parsing fallback for {$operation} operation, using raw content", ['provider' => $provider->getName()]);
                        $fallbackTitle = $options['topic'] ?? '';
                        $parsed = [
                            'title'            => $this->extractTitleFromRaw($result['content'], $rawHtml, $fallbackTitle),
                            'meta_description' => $this->extractMetaFromRaw($result['content']),
                            'content_html'     => $this->applyArticleStyling(Sanitizer::clean($rawHtml)),
                            'tags'             => $this->extractTagsFromRaw($result['content']),
                            'excerpt'          => $this->extractExcerptFromRaw($result['content']),
                        ];
                    }
                }

                if ($parsed === null) {
                    // Invalid JSON — retry once with same provider by asking for valid JSON
                    Logger::warning('Invalid JSON from AI, retrying', ['provider' => $provider->getName()]);

                    $retryPrompt = $prompt . "\n\nIMPORTANT: Your previous response was not valid JSON. Return ONLY valid JSON, no markdown, no explanation.";
                    $retryResult = $this->callProviderWithRetry($provider, $retryPrompt, $options);

                    $retryEndMs              = (int) (microtime(true) * 1000);
                    $retryLog                = $logData;
                    $retryLog['request_time']  = $responseTime;
                    $retryLog['response_time'] = date('Y-m-d H:i:s');
                    $retryLog['duration_ms']   = $retryEndMs - $endMs;

                    if ($retryResult['success']) {
                        $parsed = $parser($retryResult['content']);
                        if ($parsed === null && in_array($operation, ['generate', 'improve'], true) && trim((string)$retryResult['content']) !== '') {
                            $rawHtml = $this->cleanRawArticleContent($retryResult['content']);
                            if (strlen(trim($rawHtml)) > 20) {
                                Logger::info("AI JSON parsing fallback for {$operation} retry, using raw content", ['provider' => $provider->getName()]);
                                $fallbackTitle = $options['topic'] ?? '';
                                $parsed = [
                                    'title'            => $this->extractTitleFromRaw($retryResult['content'], $rawHtml, $fallbackTitle),
                                    'meta_description' => $this->extractMetaFromRaw($retryResult['content']),
                                    'content_html'     => $this->applyArticleStyling(Sanitizer::clean($rawHtml)),
                                    'tags'             => $this->extractTagsFromRaw($retryResult['content']),
                                    'excerpt'          => $this->extractExcerptFromRaw($retryResult['content']),
                                ];
                            }
                        }

                        if ($parsed !== null) {
                            $retryLog['status']             = 'SUCCESS';
                            if ($operation === 'review' && is_array($parsed) && isset($parsed['score'])) {
                                $sc = (float) $parsed['score'];
                                if ($sc >= 8.0) {
                                    $retryLog['error_message'] = sprintf('Review Pass: %.1f/10 (≥ 8.0 পাস মার্ক অর্জিত)', $sc);
                                } else {
                                    $diff = round(8.0 - $sc, 1);
                                    $retryLog['error_message'] = sprintf('Review Fail: %.1f/10 (৮.০ পাস মার্কের চেয়ে %.1f কম — ৯.০+ টার্গেট নিয়ে Improve করা হচ্ছে)', $sc, $diff);
                                }
                            } elseif ($operation === 'improve') {
                                $retryLog['error_message'] = 'Content Improved (টার্গেট ৯.০+ অর্জনের জন্য ফিডব্যাক অনুযায়ী সংশোধন করা হয়েছে)';
                            }
                            $retryLog['prompt_tokens']      = $retryResult['tokens']['prompt_tokens'] ?? null;
                            $retryLog['completion_tokens']  = $retryResult['tokens']['completion_tokens'] ?? null;
                            $retryLog['total_tokens']       = $retryResult['tokens']['total_tokens'] ?? null;
                            AiUsageLog::create($retryLog);
                            $attempts[] = [
                                'provider' => $provider->getName(),
                                'model'    => $provider->getModel(),
                                'status'   => 'SUCCESS',
                                'error'    => null,
                            ];
                            return [
                                'success'         => true,
                                'data'            => $parsed,
                                'provider'        => $provider->getName(),
                                'model'           => $provider->getModel(),
                                'error'           => null,
                                'attempts'        => $attempts,
                                '_provider_index' => $providerIdx,
                            ];
                        }
                    }

                    // Fallback to first attempt's raw content if retry failed/malformed but initial response had substantive text
                    if ($parsed === null && in_array($operation, ['generate', 'improve'], true) && trim((string)$result['content']) !== '') {
                        $rawHtml = $this->cleanRawArticleContent($result['content']);
                        if (strlen(trim($rawHtml)) > 20) {
                            Logger::info("AI recovering raw content from initial {$operation} attempt", ['provider' => $provider->getName()]);
                            $fallbackTitle = $options['topic'] ?? '';
                            $parsed = [
                                'title'            => $this->extractTitleFromRaw($result['content'], $rawHtml, $fallbackTitle),
                                'meta_description' => $this->extractMetaFromRaw($result['content']),
                                'content_html'     => $this->applyArticleStyling(Sanitizer::clean($rawHtml)),
                                'tags'             => $this->extractTagsFromRaw($result['content']),
                                'excerpt'          => $this->extractExcerptFromRaw($result['content']),
                            ];
                            $retryLog['status'] = 'SUCCESS';
                            if ($operation === 'improve') {
                                $retryLog['error_message'] = 'Content Improved (টার্গেট ৯.০+ অর্জনের জন্য ফিডব্যাক অনুযায়ী সংশোধন করা হয়েছে)';
                            }
                            AiUsageLog::create($retryLog);
                            $attempts[] = [
                                'provider' => $provider->getName(),
                                'model'    => $provider->getModel(),
                                'status'   => 'SUCCESS',
                                'error'    => null,
                            ];
                            return [
                                'success'         => true,
                                'data'            => $parsed,
                                'provider'        => $provider->getName(),
                                'model'           => $provider->getModel(),
                                'error'           => null,
                                'attempts'        => $attempts,
                                '_provider_index' => $providerIdx,
                            ];
                        }
                    }

                    $retryLog['status']        = 'INVALID_RESPONSE';
                    $retryLog['error_message'] = 'Invalid JSON response after retry';
                    AiUsageLog::create($retryLog);

                    $attempts[]  = [
                        'provider' => $provider->getName(),
                        'model'    => $provider->getModel(),
                        'status'   => 'INVALID_RESPONSE',
                        'error'    => 'Invalid JSON after retry',
                    ];
                    $lastError = 'Invalid JSON response from ' . $provider->getName();
                    continue;
                }
            }

            $logData['status'] = 'SUCCESS';
            if ($operation === 'review' && is_array($parsed) && isset($parsed['score'])) {
                $sc = (float) $parsed['score'];
                if ($sc >= 8.0) {
                    $logData['error_message'] = sprintf('Review Pass: %.1f/10 (≥ 8.0 পাস মার্ক অর্জিত)', $sc);
                } else {
                    $diff = round(8.0 - $sc, 1);
                    $logData['error_message'] = sprintf('Review Fail: %.1f/10 (৮.০ পাস মার্কের চেয়ে %.1f কম — ৯.০+ টার্গেট নিয়ে Improve করা হচ্ছে)', $sc, $diff);
                }
            } elseif ($operation === 'improve') {
                $logData['error_message'] = 'Content Improved (টার্গেট ৯.০+ অর্জনের জন্য ফিডব্যাক অনুযায়ী সংশোধন করা হয়েছে)';
            }
            AiUsageLog::create($logData);

            Logger::info('AI request succeeded', [
                'provider'    => $provider->getName(),
                'operation'   => $operation,
                'duration_ms' => $duration,
            ]);

            $attempts[] = [
                'provider' => $provider->getName(),
                'model'    => $provider->getModel(),
                'status'   => 'SUCCESS',
                'error'    => null,
            ];
            return [
                'success'         => true,
                'data'            => $parsed,
                'provider'        => $provider->getName(),
                'model'           => $provider->getModel(),
                'error'           => null,
                'attempts'        => $attempts,
                '_provider_index' => $providerIdx,
            ];
        }

        Logger::error('All AI providers failed', ['operation' => $operation, 'last_error' => $lastError]);

        $config  = $this->config;
        $debug   = (bool) ($config['debug'] ?? false);
        $userMsg = 'AI generation failed. The system attempted available backup providers. Please try again.';
        if ($debug && $lastError !== '') {
            $userMsg .= ' Details: ' . $lastError;
        }

        return ['success' => false, 'data' => null, 'provider' => null, 'model' => null, 'error' => $userMsg];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROMPT BUILDERS
    // ─────────────────────────────────────────────────────────────────────────

    private function buildGenerationPrompt(array $data): string
    {
        $topic     = $data['topic'] ?? '';
        $primary   = $data['primary_keyword'] ?? '';
        $secondary = is_array($data['secondary_keywords'] ?? null)
            ? implode(', ', $data['secondary_keywords'])
            : ($data['secondary_keywords'] ?? '');
        $audience  = $data['target_audience'] ?? 'General Bangladesh Readers';
        $isBn      = ($data['language'] ?? 'bn') === 'bn';
        $language  = $isBn ? 'Bengali (বাংলা)' : 'English';
        $wordCount = $data['word_count'] ?? 1000;
        $tone      = $data['writing_tone'] ?? 'Professional + Easy';

        $personaHeader = $isBn
            ? "তুমি একজন অভিজ্ঞ প্রযুক্তি বিশেষজ্ঞ এবং প্র্যাকটিক্যাল কন্টেন্ট রাইটার। বাস্তব অভিজ্ঞতা এবং হ্যান্ডস-অন টেস্টিং ছাড়া তুমি কোনো জেনেরিক রিভিউ লেখো না।\n\nনিচের তথ্যের উপর একটি সম্পূর্ণ, মানসম্মত এবং তথ্যপূর্ণ ব্লগ পোস্ট লিখো।"
            : "You are an expert hands-on tech researcher and specialist content writer. You prioritize real-world experience, practical testing data, and empirical benchmarks over generic summaries.\n\nWrite a comprehensive, publication-ready, and insightful blog post based on the following details.";

        $titleRule = $isBn
            ? '- আকর্ষণীয় কিন্তু clickbait নয় এমন Title তৈরি করো।'
            : '- Craft an engaging, high-CTR title that avoids clickbait and matches user search intent.';

        return <<<PROMPT
{$personaHeader}

Topic: {$topic}
Primary Keyword: {$primary}
Secondary Keywords: {$secondary}
Target Audience: {$audience}
Language: {$language}
Word Count: approximately {$wordCount} words
Writing Tone: {$tone}

Rules:
{$titleRule}

1. Initial Layout & Introduction:
   - Do NOT insert any image, figure, or image placeholder right after the title/author or before the introduction text.
   - Every post must start directly with at least 2 substantive introductory paragraphs (<p>...</p>) before the first image appears.

2. Structure & Headings:
   - H2 এবং H3 heading ব্যবহার করো।
   - ছোট paragraph ব্যবহার করো।
   - প্রয়োজন অনুযায়ী bullet list (<ul>, <li>) ব্যবহার করো।
   - Write an in-depth, research-style article with practical examples, pros/cons, step-by-step sections, and actionable tips. Avoid shallow one-liners.
   - Conclusion সেকশন থাকবে।

3. Balanced Image Distribution & Contextual Image Notes:
   - Do NOT output actual <img> tags or external image URLs.
   - Instead, place short bracketed Image Notes indicating where images must appear:
     * First image note MUST appear directly after at least 2 substantive introductory paragraphs (Hero Image).
     * 2 to 3 additional image notes MUST be distributed across the body under different H2 headings.
   - Format:
     <p style="font-size:12px;color:#64748b;font-style:italic;margin:20px 0 6px 0;font-weight:500;">[Image Note: concise visual requirement describing what kind of image fits this specific section according to the article, e.g. Developer debugging code on IDE / Dashboard UI interface]</p>
   - The Image Note text MUST be context-specific to the article content at that section (describing what screenshot, diagram, or UI should be shown).

4. Context-Aware Links & Optional Buttons (Strict Condition):
   - Default Behavior: By default, ALL links must be regular inline HTML hyperlinks (<a href="..." target="_blank" rel="noopener noreferrer">anchor text</a>).
   - Conditional Buttons: Only use a styled CTA button IF the article naturally contains a direct, high-value action (e.g., trying/testing a specific software tool, downloading a template, or an explicit action step in the conclusion).
   - If the article is purely informational, conceptual, or educational without a direct commercial/tool action, DO NOT generate any buttons—keep standard hyperlinks only.
   - Max Button Limit: Never include more than 1 (or at most 2) styled buttons in the entire article.
   - When a button is appropriate, format it as:
     <div style="text-align: center; margin: 28px 0;">
       <a href="URL" target="_blank" rel="noopener noreferrer" style="display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 26px; border-radius: 8px;">
         Button Label &rarr;
       </a>
     </div>

5. Quality & SEO:
   - SEO keyword স্বাভাবিকভাবে ব্যবহার করো (অপ্রয়োজনীয় keyword stuffing করবে না)।
   - Spammy language ব্যবহার করবে না।
   - ভুল বা বানানো তথ্য তৈরি করবে না।
   - Content HTML format-এ দিতে হবে (use <h2>, <h3>, <p>, <ul>, <li>, <strong>, <a> tags).

6. Hands-on Testing & Real-world Benchmarks (E-E-A-T Focus):
   - যদি আর্টিকেলে কোনো সফটওয়্যার, টুল, গ্যাজেট, প্ল্যাটফর্ম বা পদ্ধতির রিভিউ বা টিপস থাকে, তবে প্রতিটির ক্ষেত্রে শুধুমাত্র সাধারণ ভাসাভাসা কথা না লিখে একটি বাস্তবসম্মত টেস্টিং ও বেঞ্চমার্ক ফ্রেমওয়ার্ক (Hands-on Test / Benchmark) তৈরি করতে হবে।
   - লেখার ভয়েস হবে ফার্স্ট-পারসন বাস্তব অভিজ্ঞতা সম্পন্ন (যেমন: "In our hands-on test", "During my evaluation run", "আমরা ৫০০ শব্দের ড্রাফট দিয়ে পরীক্ষা করার সময় দেখেছি" ইত্যাদি)।
   - প্রতিটি টুলের বা বিষয়ের জন্য বাস্তবসম্মত ডেমো মেট্রিক্স (Realistic Demo Test Data) প্রদান করতে হবে (যেমন: টেস্ট স্যাম্পল সাইজ, প্রসেসিং টাইম, বিভিন্ন ডিটেক্টরে পাওয়া স্কোর বা পারফরম্যান্স রেট)। এটি একটি সুন্দর HTML কার্ড বা বক্সে সাজিয়ে দিতে হবে, যাতে পরবর্তীতে ইউজার সরাসরি তার আসল টেস্ট ডাটা দিয়ে মানগুলো এডিট করতে পারে:
     <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #2563eb; border-radius: 8px; padding: 16px 20px; margin: 24px 0;">
       <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 15px; font-weight: 600;">📊 Hands-on Test Benchmark: [টুল বা বিষয়ের নাম]</h4>
       <ul style="margin: 0; padding-left: 18px; color: #475569; font-size: 14px; line-height: 1.6;">
         <li><strong>Test Sample:</strong> [টেস্টের স্যাম্পল সাইজ বা ইনপুট]</li>
         <li><strong>Processing Time / Speed:</strong> [প্রসেসিং সময় বা স্পিড]</li>
         <li><strong>Accuracy / Performance Score:</strong> [স্কোর বা পারফরম্যান্স রেট]</li>
         <li><strong>Key Observation:</strong> [বাস্তব পরীক্ষায় পাওয়া বিশেষ পর্যবেক্ষণ]</li>
       </ul>
     </div>
   - টুলের ভালো দিকের পাশাপাশি বাস্তব পরীক্ষায় ধরা পড়া খুঁত বা লিমিটেশন (Real Testing Friction) পরিষ্কারভাবে উল্লেখ করতে হবে।

7. Human Writing Style & Zero AI Cliches:
   - লেখার মধ্যে অতি-ব্যবহৃত রোবোটিক শব্দ বা এআই ক্লিশে (যেমন: "delve into", "tapestry", "in this digital era", "revolutionize", "testament", "beacon", "game-changer", "let's dive in", "a testament to") কঠোরভাবে পরিহার করতে হবে।
   - কৃত্রিম মার্কেটিং অতিরঞ্জন বাদ দিয়ে স্বাভাবিক, ছোট-বড় বাক্যের মিশ্রণে মানুষের লেখার মতো স্বতঃস্ফূর্ত ছন্দ ও প্রবাহ (natural rhythm & burstiness) বজায় রাখতে হবে।

Output অবশ্যই structured JSON format-এ দিতে হবে। Return ONLY valid JSON, no markdown code blocks, no explanation:

{
  "title": "",
  "meta_description": "",
  "content_html": "",
  "tags": [],
  "excerpt": ""
}
PROMPT;
    }

    private function buildReviewPrompt(array $data): string
    {
        $title   = $data['title'] ?? '';
        $meta    = $data['meta_description'] ?? '';
        $content = $data['content_html'] ?? '';
        $keyword = $data['primary_keyword'] ?? '';

        if (mb_strlen($content) > 10000) {
            $content = mb_substr($content, 0, 10000) . '...';
        }

        return <<<PROMPT
You are a strict senior content editor, SEO specialist, and fact-checker for professional blogs.

Analyze this blog post in detail.

Primary keyword: {$keyword}
Title: {$title}
Meta description: {$meta}
Content (HTML):
{$content}

Evaluate thoroughly:
1) Grammar & spelling (language quality)
2) SEO (title, meta, headings, keyword placement without stuffing)
3) Readability (short paragraphs, clarity, flow)
4) Structure (H2/H3 hierarchy, intro, steps, conclusion)
5) Initial Layout & Introduction: Verify that the post starts directly with at least 2 substantive introductory paragraphs before any image appears (no image right after title or before intro text).
6) Balanced Image Distribution & Image Notes: Verify that image notes [Image Note: ...] are evenly distributed across the article (first after at least 2 intro paragraphs, others under H2 sections) and that each note accurately reflects the specific article section content rather than being generic.
7) Context-Aware Links & Optional Buttons: Verify all links use target="_blank" rel="noopener noreferrer". Verify that styled CTA buttons are strictly conditional: only 1 or at most 2 buttons, used ONLY when there is a direct high-value action (testing a tool, downloading a template, action in conclusion), and NEVER on purely informational articles. Verify button formatting matches <div style="text-align: center; margin: 28px 0;"><a ... style="display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); ...">... &rarr;</a></div>.
8) Usefulness (actionable steps, examples, completeness)
9) Originality / non-spam
10) Internal consistency of claims

Scoring rules:
- Each sub-score 0-10
- overall "score" = average of grammar_score, seo_score, readability_score, structure_score (one decimal allowed)
- Pass mark threshold is 8.0:
  * Score >= 8.0 is PASS / publication-ready.
  * Score < 8.0 is FAIL and requires correction.
  * 9.0+ represents publication-ready excellence (the ideal target).
- When below 8.0, list concrete problems and required_changes so the improvement phase can elevate the score to 9.0+.

problems / suggestions / required_changes must be specific bullet strings (not vague).
Include detailed_notes: 3-8 sentences summarizing strengths and weaknesses.

Return ONLY valid JSON (no markdown fences):
{
  "score": 0,
  "grammar_score": 0,
  "seo_score": 0,
  "readability_score": 0,
  "structure_score": 0,
  "problems": ["..."],
  "suggestions": ["..."],
  "required_changes": ["..."],
  "detailed_notes": "...",
  "verdict": "APPROVE or NEEDS_EDIT"
}
PROMPT;
    }

    private function buildImprovePrompt(array $data, array $review, float $passScore = 8.0, float $targetScore = 9.0): string
    {
        $title       = $data['title'] ?? '';
        $meta        = $data['meta_description'] ?? '';
        $content     = $data['content_html'] ?? '';
        $keyword     = $data['primary_keyword'] ?? '';
        $problems    = json_encode($review['problems'] ?? [], JSON_UNESCAPED_UNICODE);
        $suggestions = json_encode($review['suggestions'] ?? [], JSON_UNESCAPED_UNICODE);
        $required    = json_encode($review['required_changes'] ?? [], JSON_UNESCAPED_UNICODE);
        $notes       = $review['detailed_notes'] ?? '';
        $score       = (float) ($review['score'] ?? 0);
        $deficit     = round(max(0.0, $passScore - $score), 1);

        if (mb_strlen($content) > 12000) {
            $content = mb_substr($content, 0, 12000);
        }

        return <<<PROMPT
You are an expert senior blog editor.
The previous review scored {$score}/10, which failed the minimum pass mark of {$passScore}/10 (short by {$deficit} points).
Your goal is to thoroughly improve the post through editorial feedback so that on re-review it achieves a score of {$targetScore}+ (at least 9.0+).

Primary keyword: {$keyword}
Current title: {$title}
Current meta: {$meta}
Current HTML content:
{$content}

Previous Review Score: {$score} / 10 (Pass mark is {$passScore}, aiming for {$targetScore}+)
Problems: {$problems}
Suggestions: {$suggestions}
Required changes: {$required}
Notes: {$notes}

Rules:
- Fix ALL listed problems and apply required_changes.
- Keep language of the original post (Bengali or English).
- Initial Layout: Ensure the post starts directly with at least 2 substantive introductory paragraphs (<p>...</p>) before any image appears. Do NOT put any image or image placeholder right after the title or before the intro text.
- Balanced Image Distribution & Image Notes: Ensure [Image Note: ...] paragraphs are preserved and distributed evenly (first after at least 2 intro paragraphs, others under H2 sections), with notes reflecting the specific article section.
- Context-Aware Links & Optional Buttons:
  * By default, keep all links as regular inline HTML hyperlinks (<a href="..." target="_blank" rel="noopener noreferrer">anchor text</a>).
  * Only use a styled CTA button IF the article naturally contains a direct, high-value action (e.g. testing software/tools, downloading a template, explicit action in conclusion).
  * If the article is purely informational, conceptual, or educational without a direct commercial/tool action, DO NOT generate any buttons—keep standard hyperlinks only.
  * Maximum 1 (or at most 2) styled buttons for the whole article.
  * When a button is appropriate, format it strictly as:
    <div style="text-align: center; margin: 28px 0;">
      <a href="URL" target="_blank" rel="noopener noreferrer" style="display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 26px; border-radius: 8px;">
        Button Label &rarr;
      </a>
    </div>
- Keep valid HTML (h2, h3, p, ul, li, strong, figure/img if present).
- Strengthen intro, steps, examples, conclusion, SEO title/meta.
- Do NOT invent fake statistics.
- Do NOT remove useful structure.
- Return ONLY valid JSON:
{
  "title": "",
  "meta_description": "",
  "content_html": "",
  "tags": [],
  "excerpt": ""
}
PROMPT;
    }

    private function buildTopicSuggestionPrompt(string $keyword, string $language, int $count): string
    {
        $langLabel = $language === 'bn' ? 'Bengali (বাংলা)' : 'English';

        return <<<PROMPT
তুমি একজন expert content strategist।

Keyword/Category: {$keyword}
Language: {$langLabel}

{$count}টি আকর্ষণীয়, SEO-friendly ব্লগ টপিক সাজেস্ট করো।

প্রতিটি টপিক বাংলাদেশের পাঠকদের জন্য প্রাসঙ্গিক হতে হবে।

Return ONLY valid JSON array (no markdown):

[
  {"topic": "...", "primary_keyword": "..."},
  {"topic": "...", "primary_keyword": "..."}
]
PROMPT;
    }

    private function buildSeoPrompt(array $data): string
    {
        $title          = $data['title'] ?? '';
        $meta           = $data['meta_description'] ?? '';
        $keyword        = $data['primary_keyword'] ?? '';
        $contentPreview = Sanitizer::truncate(strip_tags($data['content_html'] ?? ''), 500);

        return <<<PROMPT
তুমি একজন SEO expert। নিচের কনটেন্টের জন্য SEO optimization সাজেস্ট করো।

Current Title: {$title}
Current Meta: {$meta}
Focus Keyword: {$keyword}
Content Preview: {$contentPreview}

Return ONLY valid JSON (no markdown):

{
  "alternative_titles": ["", "", ""],
  "meta_description": "",
  "focus_keyword": "",
  "related_keywords": [],
  "suggested_headings": []
}

Title max 60 characters. Meta description 150-160 characters.
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARSERS
    // ─────────────────────────────────────────────────────────────────────────

    private function parseGenerationResponse(string $content, array $fallbackData = []): ?array
    {
        $json = $this->extractJson($content);

        if (is_array($json) && !empty($json)) {
            $contentHtml = $json['content_html'] ?? $json['content'] ?? $json['body'] ?? null;
            if (isset($json['title']) && $contentHtml !== null && trim((string)$contentHtml) !== '') {
                $cleaned = Sanitizer::clean((string) $contentHtml);
                $styled  = $this->applyArticleStyling($cleaned);

                return [
                    'title'            => trim((string) $json['title']),
                    'meta_description' => trim((string) ($json['meta_description'] ?? '')),
                    'content_html'     => $styled,
                    'tags'             => is_array($json['tags'] ?? null) ? $json['tags'] : [],
                    'excerpt'          => trim((string) ($json['excerpt'] ?? '')),
                ];
            }
        }

        // Resilient Fallback for Generate: If JSON decode fails or content_html is missing,
        // recover article from the raw response string instead of failing the request.
        $rawHtml = $this->cleanRawArticleContent($content);
        if ($rawHtml === '') {
            $rawHtml = (string) ($fallbackData['content_html'] ?? '');
        }

        if (strlen(trim($rawHtml)) < 20) {
            return null;
        }

        Logger::info('AIService: JSON decode failed in generate process, using resilient fallback from raw content', [
            'raw_length' => strlen($content),
        ]);

        $cleaned = Sanitizer::clean($rawHtml);
        $styled  = $this->applyArticleStyling($cleaned);

        $fallbackTitle = $fallbackData['title'] ?? $fallbackData['topic'] ?? '';
        $title   = $this->extractTitleFromRaw($content, $styled, $fallbackTitle);
        $meta    = $this->extractMetaFromRaw($content, (string) ($fallbackData['meta_description'] ?? ''));
        $tags    = $this->extractTagsFromRaw($content, (array) ($fallbackData['tags'] ?? []));
        $excerpt = $this->extractExcerptFromRaw($content, (string) ($fallbackData['excerpt'] ?? ''));

        return [
            'title'            => $title,
            'meta_description' => $meta,
            'content_html'     => $styled,
            'tags'             => $tags,
            'excerpt'          => $excerpt,
        ];
    }

    /**
     * Parse Improve response with automatic fallback to raw response string if JSON decode fails.
     */
    private function parseImproveResponse(string $content, array $fallbackData = []): array
    {
        $json = $this->extractJson($content);

        if (is_array($json) && !empty($json)) {
            $contentHtml = $json['content_html'] ?? $json['content'] ?? $json['body'] ?? null;
            if ($contentHtml !== null && trim((string)$contentHtml) !== '') {
                $cleaned = Sanitizer::clean((string) $contentHtml);
                $styled  = $this->applyArticleStyling($cleaned);
                return [
                    'title'            => trim((string)($json['title'] ?? $fallbackData['title'] ?? '')),
                    'meta_description' => trim((string)($json['meta_description'] ?? $fallbackData['meta_description'] ?? '')),
                    'content_html'     => $styled,
                    'tags'             => is_array($json['tags'] ?? null) ? $json['tags'] : ($fallbackData['tags'] ?? []),
                    'excerpt'          => trim((string)($json['excerpt'] ?? $fallbackData['excerpt'] ?? '')),
                ];
            }
        }

        // Fallback: If json_decode still fails on the improved text, do not fail the request—
        // instead, treat the raw response string as the improved article content directly.
        Logger::info('AIService: JSON decode failed in improve process, falling back to raw response string as content', [
            'raw_length' => strlen($content),
        ]);

        $cleanedContent = $this->cleanRawArticleContent($content);
        if ($cleanedContent === '') {
            $cleanedContent = (string)($fallbackData['content_html'] ?? '');
        }

        $cleaned = Sanitizer::clean($cleanedContent);
        $styled  = $this->applyArticleStyling($cleaned);

        return [
            'title'            => $fallbackData['title'] ?? '',
            'meta_description' => $fallbackData['meta_description'] ?? '',
            'content_html'     => $styled,
            'tags'             => $fallbackData['tags'] ?? [],
            'excerpt'          => $fallbackData['excerpt'] ?? '',
        ];
    }

    /**
     * Clean raw AI response to extract article content when JSON decoding fails.
     */
    private function cleanRawArticleContent(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // If it looks like broken/malformed JSON with "content_html", try to extract its content
        if (preg_match('/"content_html"\s*:\s*"([\s\S]*?)(?:",\s*"(?:tags|excerpt|title|meta_description)|"\s*\}|\s*\}\s*$)/i', $raw, $m)) {
            $extracted = $this->unescapeJsonRawString($m[1]);
            if (strlen(trim($extracted)) > 20) {
                return trim($this->stripStrayN($extracted));
            }
        }

        // If content_html had unescaped quotes or was truncated
        if (preg_match('/"content_html"\s*:\s*"(.*)/s', $raw, $m)) {
            $extracted = $this->unescapeJsonRawString(preg_replace('/"\s*\}\s*$/', '', $m[1]));
            if (strlen(trim($extracted)) > 20) {
                return trim($this->stripStrayN($extracted));
            }
        }

        // Strip markdown code block wrappers (e.g. ```html ... ``` or ``` ...)
        $content = preg_replace('/^```(?:html|json|markdown|text)?\s*/i', '', $raw);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = str_replace(['```html', '```json', '```'], '', $content);

        // Strip conversational AI prefixes (e.g. "Here is the improved article:\n")
        $content = preg_replace('/^(?:Here is|Sure|Below is|Here\'s)[^\n<]*\n+/i', '', trim($content));

        return trim($this->stripStrayN($content));
    }

    private function parseReviewResponse(string $content): ?array
    {
        $json = $this->extractJson($content);
        if ($json === null) {
            return null;
        }

        $verdict = strtoupper((string) ($json['verdict'] ?? 'NEEDS_EDIT'));
        if (!in_array($verdict, ['APPROVE', 'NEEDS_EDIT'], true)) {
            $verdict = 'NEEDS_EDIT';
        }

        return [
            'score'             => $this->clampScore($json['score'] ?? 0),
            'grammar_score'     => $this->clampScore($json['grammar_score'] ?? 0),
            'seo_score'         => $this->clampScore($json['seo_score'] ?? 0),
            'readability_score' => $this->clampScore($json['readability_score'] ?? 0),
            'structure_score'   => $this->clampScore($json['structure_score'] ?? 0),
            'problems'          => is_array($json['problems'] ?? null) ? $json['problems'] : [],
            'suggestions'       => is_array($json['suggestions'] ?? null) ? $json['suggestions'] : [],
            'required_changes'  => is_array($json['required_changes'] ?? null) ? $json['required_changes'] : [],
            'detailed_notes'    => trim((string) ($json['detailed_notes'] ?? '')),
            'verdict'           => $verdict,
        ];
    }

    private function parseTopicSuggestions(string $content): ?array
    {
        $json = $this->extractJson($content);
        if ($json === null || !is_array($json)) {
            return null;
        }

        // Handle both array of objects and wrapped format
        $items = isset($json[0]) ? $json : ($json['topics'] ?? $json['suggestions'] ?? null);
        if (!is_array($items)) {
            return null;
        }

        $result = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $result[] = ['topic' => $item, 'primary_keyword' => ''];
            } elseif (is_array($item) && isset($item['topic'])) {
                $result[] = [
                    'topic'           => (string) $item['topic'],
                    'primary_keyword' => (string) ($item['primary_keyword'] ?? ''),
                ];
            }
        }

        return $result !== [] ? $result : null;
    }

    private function parseSeoResponse(string $content): ?array
    {
        $json = $this->extractJson($content);
        if ($json === null) {
            return null;
        }

        return [
            'alternative_titles' => is_array($json['alternative_titles'] ?? null) ? $json['alternative_titles'] : [],
            'meta_description'   => (string) ($json['meta_description'] ?? ''),
            'focus_keyword'      => (string) ($json['focus_keyword'] ?? ''),
            'related_keywords'   => is_array($json['related_keywords'] ?? null) ? $json['related_keywords'] : [],
            'suggested_headings' => is_array($json['suggested_headings'] ?? null) ? $json['suggested_headings'] : [],
        ];
    }

    /**
     * Extract JSON from AI response (uses regex to strip markdown code blocks and extract valid JSON between first "{" and last "}").
     */
    private function extractJson(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        // 1. Strip markdown code blocks (like ```json ... ``` or ``` ...) using Regex
        $clean = preg_replace('/```(?:json|javascript|text)?\s*/i', '', $content);
        $clean = preg_replace('/\s*```/', '', $clean);
        $clean = str_replace('```', '', $clean);
        $clean = trim($clean);

        // 2. Direct decode check if clean string is already valid JSON
        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 3. Extract the valid JSON object between the first "{" and the last "}" using Regex
        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $jsonStr = trim($matches[0]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Fix unescaped newlines/tabs inside JSON string literals (common when LLMs emit raw HTML inside JSON)
            $fixed = preg_replace_callback('/"((?:[^"\\\\]|\\\\.)*)"/s', function ($m) {
                $str = $m[1];
                $str = str_replace(["\r\n", "\r", "\n"], "\\n", $str);
                $str = str_replace("\t", "\\t", $str);
                return '"' . $str . '"';
            }, $jsonStr);

            $decoded = json_decode($fixed, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Also try stripping unescaped control characters
            $cleanCtrl = preg_replace('/[\x00-\x1F\x7F]/', ' ', $jsonStr);
            $decoded = json_decode($cleanCtrl, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 4. Also support JSON array between first "[" and last "]" (e.g. topic suggestions)
        if (preg_match('/\[[\s\S]*\]/', $clean, $matches)) {
            $jsonArr = trim($matches[0]);
            $decoded = json_decode($jsonArr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function clampScore(mixed $score): float
    {
        $val = (float) $score;
        return max(0, min(10, round($val, 1)));
    }

    public function getConfiguredProviders(): array
    {
        return array_map(static fn(AIProviderInterface $p) => [
            'name'  => $p->getName(),
            'model' => $p->getModel(),
        ], $this->providers);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ARTICLE STYLING & EDITORIAL LAYOUT LOGIC
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Apply editorial layout and styling standards to generated/improved HTML:
     * 1. Strip literal 'n' characters erroneously output before/between HTML tags.
     * 2. Initial Layout: Ensure at least 2 substantive introductory paragraphs before any image/figure appears.
     * 3. Context-Aware Links: Ensure all <a> tags have target="_blank" rel="noopener noreferrer".
     * 4. Button Standards: Format conditional CTA buttons with exact styled container and gradient anchor,
     *    and strictly limit to max 1 or at most 2 buttons across the entire article.
     */
    public function applyArticleStyling(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // 1. Strip any stray literal 'n' characters output before/between HTML tags
        $html = $this->stripStrayN($html);

        // 2. Initial Layout: Ensure at least 2 substantive introductory paragraphs before any image
        $html = $this->enforceIntroLayout($html);

        // 3. Context-Aware Links & Optional Buttons: Standardize target/rel & format CTA buttons
        $html = $this->standardizeLinksAndButtons($html);

        // 4. Final pass to ensure clean output without stray 'n's
        $html = $this->stripStrayN($html);

        return trim($html);
    }

    /**
     * Enforces that every post starts directly with at least 2 substantive introductory paragraphs
     * before any image, figure, or image placeholder appears.
     */
    private function enforceIntroLayout(string $html): string
    {
        // Clean raw image placeholders at the beginning (e.g., [Image: ...], ![...](...))
        $html = preg_replace('/^\s*(\[image:[^\]]*\]|!\[[^\]]*\]\([^\)]*\))\s*/iu', '', $html) ?? $html;

        // Match all <p> tags with their content and offsets
        if (!preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $pMatches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        // Find substantive paragraphs (paragraphs with actual textual content, not just images/empty)
        $substantiveEndOffsets = [];
        foreach ($pMatches[0] as $idx => $match) {
            $pFull = $match[0];
            $pContent = $pMatches[1][$idx][0];
            $textOnly = trim(strip_tags($pContent));
            if (mb_strlen($textOnly) > 20 && !preg_match('/^\s*<img[^>]*>\s*$/i', trim($pContent))) {
                $endOffset = $match[1] + strlen($pFull);
                $substantiveEndOffsets[] = $endOffset;
                if (count($substantiveEndOffsets) >= 2) {
                    break;
                }
            }
        }

        if (count($substantiveEndOffsets) < 2) {
            return $html;
        }

        $secondPOffset = $substantiveEndOffsets[1];

        // Check if there are any <figure> or <img> before this offset
        $prefix = substr($html, 0, $secondPOffset);
        $suffix = substr($html, $secondPOffset);

        $imagesToMove = [];

        // Check for <figure>...</figure> in prefix
        if (preg_match_all('/<figure[^>]*>.*?<\/figure>/is', $prefix, $figMatches)) {
            foreach ($figMatches[0] as $fig) {
                $imagesToMove[] = $fig;
                $prefix = str_replace($fig, '', $prefix);
            }
        }

        // Check for <p>\s*<img[^>]+>\s*<\/p> in prefix
        if (preg_match_all('/<p[^>]*>\s*<img[^>]+>\s*<\/p>/is', $prefix, $pImgMatches)) {
            foreach ($pImgMatches[0] as $pImg) {
                $imagesToMove[] = $pImg;
                $prefix = str_replace($pImg, '', $prefix);
            }
        }

        // Check for standalone <img> in prefix
        if (preg_match_all('/<img[^>]+>/is', $prefix, $imgMatches)) {
            foreach ($imgMatches[0] as $img) {
                $imagesToMove[] = $img;
                $prefix = str_replace($img, '', $prefix);
            }
        }

        if ($imagesToMove === []) {
            return $html;
        }

        $movedBlock = "\n" . implode("\n", $imagesToMove) . "\n";
        $prefix = trim($prefix);

        return $prefix . $movedBlock . ltrim($suffix);
    }

    /**
     * Standardizes links to target="_blank" rel="noopener noreferrer",
     * formats conditional CTA buttons (limit: max 1 or 2) with exact styling,
     * and demotes excess buttons to regular links.
     */
    private function standardizeLinksAndButtons(string $html): string
    {
        // 1. Ensure all <a> tags have target="_blank" and rel="noopener noreferrer"
        $html = preg_replace_callback('/<a\s+([^>]+)>/i', function ($matches) {
            $attrs = $matches[1];

            if (preg_match('/target=["\'][^"\']*["\']/i', $attrs)) {
                $attrs = preg_replace('/target=["\'][^"\']*["\']/i', 'target="_blank"', $attrs);
            } else {
                $attrs .= ' target="_blank"';
            }

            if (preg_match('/rel=["\'][^"\']*["\']/i', $attrs)) {
                $attrs = preg_replace('/rel=["\'][^"\']*["\']/i', 'rel="noopener noreferrer"', $attrs);
            } else {
                $attrs .= ' rel="noopener noreferrer"';
            }

            return '<a ' . trim($attrs) . '>';
        }, $html);

        // 2. Identify and format CTA buttons (max 2)
        $buttonContainerStyle = 'text-align: center; margin: 28px 0;';
        $buttonLinkStyle = 'display: inline-block; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 12px 26px; border-radius: 8px;';
        $maxButtons = 2;
        $buttonCount = 0;

        $pattern = '/<(div|p)[^>]*style=["\'][^"\']*text-align:\s*center[^"\']*["\'][^>]*>\s*(<a\s+[^>]+>[\s\S]*?<\/a>)\s*<\/\1>|<a\s+([^>]*style=["\'][^"\']*(?:display:\s*inline-block|background:[^"\']*|background-color:[^"\']*)[^"\']*[^>]*)>([\s\S]*?)<\/a>/i';

        $html = preg_replace_callback($pattern, function ($matches) use (&$buttonCount, $maxButtons, $buttonContainerStyle, $buttonLinkStyle) {
            $buttonCount++;

            if (!empty($matches[2])) {
                $aHtml = $matches[2];
                preg_match('/<a\s+([^>]+)>([\s\S]*?)<\/a>/i', $aHtml, $aMatch);
                $attrs = $aMatch[1] ?? '';
                $label = trim($aMatch[2] ?? '');
                $isBlockWrapper = true;
            } else {
                $attrs = $matches[3] ?? '';
                $label = trim($matches[4] ?? '');
                $isBlockWrapper = false;
            }

            $href = '#';
            if (preg_match('/href=["\']([^"\']*)["\']/i', $attrs, $hMatch)) {
                $href = $hMatch[1];
            }

            $cleanLabel = preg_replace('/(\s*&rarr;?|\s*→)+$/iu', '', $label);
            $cleanLabel = trim($cleanLabel);

            if ($buttonCount <= $maxButtons) {
                $btnLabel = $cleanLabel . ' &rarr;';
                return "<div style=\"{$buttonContainerStyle}\">\n"
                    . "  <a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\" style=\"{$buttonLinkStyle}\">\n"
                    . "    {$btnLabel}\n"
                    . "  </a>\n"
                    . "</div>";
            }

            // Exceeds max button count (strict limit of 1 or at most 2) -> demote to standard link
            if ($isBlockWrapper) {
                return "<p><a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\">{$cleanLabel}</a></p>";
            }
            return "<a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\">{$cleanLabel}</a>";
        }, $html);

        return $html;
    }

    /**
     * Remove stray literal 'n' characters erroneously output before/between HTML tags (e.g. "nnnn", "n <p>", "n <ul>").
     */
    public function stripStrayN(string $html): string
    {
        // Clean any unescaped literal \n or escaped slashes that might still exist
        $html = str_replace(['\r\n', '\n', '\r'], "\n", $html);

        // 1. Remove leading stray 'n's or whitespace at document start before first tag
        $html = preg_replace('/^(?:[\s\r\n]|n)+/iu', '', $html) ?? $html;

        // 2. Remove trailing stray 'n's or whitespace at document end after last tag
        $html = preg_replace('/(?:[\s\r\n]|n)+$/iu', '', $html) ?? $html;

        // 3. Remove text nodes between tags that consist ONLY of 'n's and whitespace (e.g. "> n \n n <", "> nnnn <")
        $html = preg_replace('/>(?:[\s\r\n]|n)+</iu', ">\n<", $html) ?? $html;

        // 4. Remove empty tags containing only 'n' and whitespace: <p>n</p>, <div>nn</div>
        $html = preg_replace('/<(p|div|span|h[1-6]|li)[^>]*>(?:[\s\r\n]|n)+<\/\1>/iu', '', $html) ?? $html;

        // 5. Clean up excessive empty lines
        $html = preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;

        return trim($html);
    }

    /**
     * Unescape raw JSON string value without stripping \n into literal 'n'.
     */
    private function unescapeJsonRawString(string $val): string
    {
        // Try json_decode as a JSON string literal first
        $jsonWrapped = '"' . preg_replace('/(?<!\\\\)"/', '\"', $val) . '"';
        $decoded = json_decode($jsonWrapped);
        if (is_string($decoded)) {
            return $decoded;
        }

        // Direct translation of JSON escape sequences to real characters
        $val = str_replace(
            ['\r\n', '\n', '\r', '\t', '\"', '\\\\', '\/'],
            ["\n",   "\n", "\n", "\t", '"',  "\\",   '/'],
            $val
        );

        // Decode \uXXXX if present
        $val = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/i', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $val) ?? $val;

        return $val;
    }

    /**
     * Extract title from raw AI output when JSON decoding fails.
     */
    private function extractTitleFromRaw(string $raw, string $html, string $fallback = ''): string
    {
        if (preg_match('/"title"\s*:\s*"([^"\r\n]+)"/i', $raw, $m)) {
            $t = trim($this->unescapeJsonRawString($m[1]));
            if ($t !== '') {
                return $t;
            }
        }

        if (preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/i', $html, $m)) {
            $t = trim(strip_tags($m[1]));
            if ($t !== '') {
                return $t;
            }
        }

        return $fallback !== '' ? $fallback : 'Untitled Article';
    }

    /**
     * Extract meta description from raw AI output when JSON decoding fails.
     */
    private function extractMetaFromRaw(string $raw, string $fallback = ''): string
    {
        if (preg_match('/"meta_description"\s*:\s*"([^"\r\n]+)"/i', $raw, $m)) {
            $meta = trim($this->unescapeJsonRawString($m[1]));
            if ($meta !== '') {
                return $meta;
            }
        }
        return $fallback;
    }

    /**
     * Extract tags from raw AI output when JSON decoding fails.
     */
    private function extractTagsFromRaw(string $raw, array $fallback = []): array
    {
        if (preg_match('/"tags"\s*:\s*\[(.*?)\]/s', $raw, $m)) {
            if (preg_match_all('/"([^"]+)"/', $m[1], $tagMatches)) {
                return $tagMatches[1];
            }
        }
        return $fallback;
    }

    /**
     * Extract excerpt from raw AI output when JSON decoding fails.
     */
    private function extractExcerptFromRaw(string $raw, string $fallback = ''): string
    {
        if (preg_match('/"excerpt"\s*:\s*"([^"\r\n]+)"/i', $raw, $m)) {
            $excerpt = trim($this->unescapeJsonRawString($m[1]));
            if ($excerpt !== '') {
                return $excerpt;
            }
        }
        return $fallback;
    }
}
