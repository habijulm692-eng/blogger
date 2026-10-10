<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Helpers\Logger;

abstract class BaseAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected float $temperature;
    protected int $maxTokens;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? '';
        $this->timeout = $config['timeout'] ?? 60;
        $this->temperature = $config['temperature'] ?? 0.7;
        $this->maxTokens = $config['max_tokens'] ?? 4096;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Perform HTTP request with cURL
     */
    protected function httpRequest(
        string $url,
        array $headers,
        ?array $body = null,
        string $method = 'POST'
    ): array {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            Logger::error("cURL error [{$this->getName()}]", [
                'errno' => $errno,
                'error' => $error,
                'url' => $url,
            ]);

            return [
                'success' => false,
                'content' => null,
                'error' => "Connection error: {$error}",
                'http_status' => null,
                'tokens' => null,
                'raw' => null,
            ];
        }

        $decoded = json_decode((string) $response, true);

        return [
            'success' => $httpStatus >= 200 && $httpStatus < 300,
            'content' => null,
            'error' => null,
            'http_status' => $httpStatus,
            'tokens' => null,
            'raw' => $decoded ?? $response,
        ];
    }

    protected function fail(string $error, ?int $httpStatus = null, mixed $raw = null): array
    {
        return [
            'success' => false,
            'content' => null,
            'error' => $error,
            'http_status' => $httpStatus,
            'tokens' => null,
            'raw' => $raw,
        ];
    }

    protected function success(string $content, ?array $tokens = null, mixed $raw = null, ?int $httpStatus = 200): array
    {
        return [
            'success' => true,
            'content' => $content,
            'error' => null,
            'http_status' => $httpStatus,
            'tokens' => $tokens,
            'raw' => $raw,
        ];
    }
}
