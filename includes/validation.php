<?php
/**
 * ============================================================
 * INPUT VALIDATION & SANITIZATION HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

/**
 * Trim + strip control chars. Safe for direct DB insertion via PDO.
 */
function clean(string $value): string
{
    $value = trim($value);
    // Remove null bytes and control characters except newlines/tabs
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
}

/**
 * Collect errors for a form. Usage:
 *   $v = new Validator($_POST);
 *   $v->required('full_name', 'Full name')
 *     ->max('full_name', 100)
 *     ->email('email');
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function value(string $field): string
    {
        return clean((string) ($this->data[$field] ?? ''));
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function max(string $field, int $max, string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && mb_strlen($v) > $max) {
            $this->errors[$field] = ($label ?? ucfirst(str_replace('_', ' ', $field)))
                . " must not exceed {$max} characters.";
        }
        return $this;
    }

    public function min(string $field, int $min, string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && mb_strlen($v) < $min) {
            $this->errors[$field] = ($label ?? ucfirst(str_replace('_', ' ', $field)))
                . " must be at least {$min} characters.";
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = $this->value($field);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function phone(string $field, string $label = 'Phone'): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $v)) {
            $this->errors[$field] = "{$label} format looks invalid.";
        }
        return $this;
    }

    public function date(string $field, string $label = 'Date'): self
    {
        $v = $this->value($field);
        if ($v !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                $this->errors[$field] = "{$label} must be a valid date (YYYY-MM-DD).";
            }
        }
        return $this;
    }

    public function inList(string $field, array $allowed, string $label = null): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = ($label ?? ucfirst($field)) . ' has an invalid value.';
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? array_values($this->errors)[0] : null;
    }
}

/**
 * Escape for use inside an HTML attribute or body.
 */
function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}