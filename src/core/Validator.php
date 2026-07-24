<?php

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) { $this->data = $data; }

    /** Rules: 'required', 'email', 'min:3', 'max:200', 'in:a,b,c', 'phone', 'int', 'min_num:7' */
    public function check(string $field, array $rules, ?string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        $value = trim((string)($this->data[$field] ?? ''));
        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            switch ($name) {
                case 'required':
                    if ($value === '') $this->errors[$field] = "{$label} is required.";
                    break;
                case 'email':
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))
                        $this->errors[$field] = "{$label} doesn't look like a valid email.";
                    break;
                case 'min':
                    if ($value !== '' && mb_strlen($value) < (int)$arg)
                        $this->errors[$field] = "{$label} should be at least {$arg} characters.";
                    break;
                case 'max':
                    if (mb_strlen($value) > (int)$arg)
                        $this->errors[$field] = "{$label} is too long (max {$arg}).";
                    break;
                case 'in':
                    $opts = explode(',', (string)$arg);
                    if ($value !== '' && !in_array($value, $opts, true))
                        $this->errors[$field] = "{$label} is not a recognised option.";
                    break;
                case 'phone':
                    if ($value !== '' && !preg_match('/^[\d\s\+\-\(\)]{6,20}$/', $value))
                        $this->errors[$field] = "{$label} doesn't look like a valid phone.";
                    break;
                case 'int':
                    if ($value !== '' && !preg_match('/^\d+$/', $value))
                        $this->errors[$field] = "{$label} must be a whole number.";
                    break;
                case 'min_num':
                    if ($value !== '' && (int)$value < (int)$arg)
                        $this->errors[$field] = "{$label} must be at least {$arg}.";
                    break;
                case 'max_num':
                    if ($value !== '' && (int)$value > (int)$arg)
                        $this->errors[$field] = "{$label} must be {$arg} or below.";
                    break;
            }
            if (isset($this->errors[$field])) break; // first failure wins
        }
        return $this;
    }

    public function passes(): bool { return empty($this->errors); }
    public function fails(): bool  { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function first(): ?string { return $this->errors ? reset($this->errors) : null; }
}
