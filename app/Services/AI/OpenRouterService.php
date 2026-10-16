<?php

declare(strict_types=1);

namespace App\Services\AI;

class OpenRouterService extends BaseAIProvider
{
    public function getName(): string
    {
        return 'openrouter';
    }

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail('OpenRouter API key is not configured.');
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';

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
            'HTTP-Referer' => $_ENV['APP_URL'] ?? 'http://localhost',
            'X-Title' => $_ENV['APP_NAME'] ?? 'AI Blogger',
        ], $body);

        if (!$result['success']) {
            $errorMsg = $result['raw']['error']['message']
                ?? $result['error']
                ?? 'OpenRouter API request failed.';
            return $this->fail($errorMsg, $result['http_status'], $result['raw']);
        }

        $content = $result['raw']['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            return $this->fail('Empty response from OpenRouter.', $result['http_status'], $result['raw']);
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
