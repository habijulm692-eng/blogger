<?php

declare(strict_types=1);

namespace App\Services\AI;

class CloudflareService extends BaseAIProvider
{
    private string $accountId;
    private string $apiToken;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->accountId = $config['account_id'] ?? '';
        $this->apiToken = $config['api_token'] ?? '';
        // Override apiKey check
        $this->apiKey = $this->apiToken;
    }

    public function getName(): string
    {
        return 'cloudflare';
    }

    public function isConfigured(): bool
    {
        return $this->accountId !== '' && $this->apiToken !== '';
    }

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fail('Cloudflare Account ID or API Token is not configured.');
        }

        $model = $options['model'] ?? $this->model;
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$model}";

        $body = [
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $result = $this->httpRequest($url, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken,
        ], $body);

        if (!$result['success']) {
            $errorMsg = $result['raw']['errors'][0]['message']
                ?? $result['error']
                ?? 'Cloudflare AI request failed.';
            return $this->fail($errorMsg, $result['http_status'], $result['raw']);
        }

        // Cloudflare Workers AI response formats vary
        $content = $result['raw']['result']['response']
            ?? $result['raw']['result']
            ?? null;

        if (is_array($content)) {
            $content = $content['response'] ?? json_encode($content);
        }

        if ($content === null || $content === '') {
            return $this->fail('Empty response from Cloudflare AI.', $result['http_status'], $result['raw']);
        }

        return $this->success((string) $content, null, $result['raw'], $result['http_status']);
    }
}
