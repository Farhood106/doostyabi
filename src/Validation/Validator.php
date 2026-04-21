<?php

declare(strict_types=1);

namespace App\Validation;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $ruleLine) {
            $value = $data[$field] ?? null;
            $ruleSet = explode('|', $ruleLine);

            foreach ($ruleSet as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->errors[$field][] = 'validation.required';
                }

                if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = 'validation.email';
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule, 4);
                    if (mb_strlen((string)$value) < $min) {
                        $this->errors[$field][] = 'validation.min';
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int)substr($rule, 4);
                    if (mb_strlen((string)$value) > $max) {
                        $this->errors[$field][] = 'validation.max';
                    }
                }

                if ($rule === 'int' && $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->errors[$field][] = 'validation.int';
                }
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
