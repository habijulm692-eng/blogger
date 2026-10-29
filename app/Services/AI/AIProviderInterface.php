<?php

declare(strict_types=1);

namespace App\Services\AI;

interface AIProviderInterface
{
    public function getName(): string;

    public function getModel(): string;

    public function isConfigured(): bool;

    /**
     * Send a prompt and return the text response.
     *
     * @return array{success: bool, content: ?string, error: ?string, http_status: ?int, tokens: ?array}
     */
    public function generate(string $prompt, array $options = []): array;
}
