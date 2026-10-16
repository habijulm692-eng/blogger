<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Generic OpenAI-compatible chat completions provider.
 * Used for SambaNova, Xkero, Apinex, and similar APIs.
 */
class OpenAICompatibleService extends BaseAIProvider
{
    private string $providerName;
    private string $baseUrl;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerName = $config['name'] ?? 'openai_compatible';
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->baseUrl !== '' && $this->model !== '';
    }

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail(ucfirst($this->providerName) . ' is not configured (API key / base URL / model missing).');
        }

        $url = $this->baseUrl . '/chat/completions';

        $body = [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? $this->temperature,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
        ];

        $result = $this->httpRequest($url, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ], $body);

        if (!$result['success']) {
            $errorMsg = $result['raw']['error']['message']
                ?? (is_string($result['raw']['error'] ?? null) ? $result['raw']['error'] : null)
                ?? $result['error']
                ?? ucfirst($this->providerName) . ' API request failed.';
            return $this->fail($errorMsg, $result['http_status'], $result['raw']);
        }

        $content = $result['raw']['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            return $this->fail('Empty response from ' . $this->providerName . '.', $result['http_status'], $result['raw']);
        }

        $tokens = null;
        if (isset($result['raw']['usage'])) {
            $tokens = [
                'prompt_tokens' => $result['raw']['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['raw']['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['raw']['usage']['total_tokens'] ?? null,
            ];
        }

        return $this->success($content, $tokens, $result['raw'], $result['http_status']);
    }
}
