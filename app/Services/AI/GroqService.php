<?php

declare(strict_types=1);

namespace App\Services\AI;

class GroqService extends BaseAIProvider
{
    public function getName(): string
    {
        return 'groq';
    }

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail('Groq API key is not configured.');
        }

        $url = 'https://api.groq.com/openai/v1/chat/completions';

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
                ?? $result['error']
                ?? 'Groq API request failed.';
            return $this->fail($errorMsg, $result['http_status'], $result['raw']);
        }

        $content = $result['raw']['choices'][0]['message']['content'] ?? null;

        if ($content === null || $content === '') {
            return $this->fail('Empty response from Groq.', $result['http_status'], $result['raw']);
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
