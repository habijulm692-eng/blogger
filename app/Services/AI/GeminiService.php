<?php

declare(strict_types=1);

namespace App\Services\AI;

class GeminiService extends BaseAIProvider
{
    private string $providerName;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->providerName = $config['name'] ?? 'gemini';
    }

    public function getName(): string
    {
        return $this->providerName;
    }

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail('Gemini API key is not configured.');
        }

        $model = $options['model'] ?? $this->model;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? $this->temperature,
                'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
            ],
        ];

        $result = $this->httpRequest($url, [
            'Content-Type' => 'application/json',
        ], $body);

        if (!$result['success']) {
            $errorMsg = $result['raw']['error']['message']
                ?? $result['error']
                ?? 'Gemini API request failed.';
            return $this->fail($errorMsg, $result['http_status'], $result['raw']);
        }

        $content = $result['raw']['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($content === null || $content === '') {
            return $this->fail('Empty response from Gemini.', $result['http_status'], $result['raw']);
        }

        $tokens = null;
        if (isset($result['raw']['usageMetadata'])) {
            $tokens = [
                'prompt_tokens' => $result['raw']['usageMetadata']['promptTokenCount'] ?? null,
                'completion_tokens' => $result['raw']['usageMetadata']['candidatesTokenCount'] ?? null,
                'total_tokens' => $result['raw']['usageMetadata']['totalTokenCount'] ?? null,
            ];
        }

        return $this->success($content, $tokens, $result['raw'], $result['http_status']);
    }
}
