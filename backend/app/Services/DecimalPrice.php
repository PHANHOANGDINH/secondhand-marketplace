<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DecimalPrice implements ValidationRule
{
    public function __construct(private bool $allowZero = false) {}

    public static function valid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/D', $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valid($value) || (! $this->allowZero && bccomp($value, '0', 2) <= 0)) {
            $fail('Use a decimal string within DECIMAL(15,2), with at most two fractional digits and a valid positive amount.');
        }
    }
}
