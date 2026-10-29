<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Simple input validator
 */
class Validator
{
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $this->errors[$field][] = "{$label} is required.";
        }

        return $this;
    }

    public function email(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$label} must be a valid email.";
        }

        return $this;
    }

    public function min(string $field, int $min, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if (is_string($value) && mb_strlen($value) < $min) {
            $this->errors[$field][] = "{$label} must be at least {$min} characters.";
        }

        return $this;
    }

    public function max(string $field, int $max, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if (is_string($value) && mb_strlen($value) > $max) {
            $this->errors[$field][] = "{$label} must not exceed {$max} characters.";
        }

        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = "{$label} has an invalid value.";
        }

        return $this;
    }

    public function integer(string $field, string $label = ''): self
    {
        $value = $this->data[$field] ?? null;
        $label = $label !== '' ? $label : $field;

        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field][] = "{$label} must be an integer.";
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }
}
