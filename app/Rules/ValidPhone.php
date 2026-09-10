<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mirrors Dental_FrontEnd's own isValidPhone() (src/utils/validation.js)
 * exactly -- strip whitespace, strip one leading '+', then require 9-15
 * digits. Kept as one rule class (rather than a plain 'regex:...' rule
 * repeated per request) so the two normalization steps can't drift out of
 * sync with the frontend's version across the several forms that collect a
 * phone number.
 */
class ValidPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = preg_replace('/\s+/', '', (string) $value);
        $normalized = preg_replace('/^\+/', '', $normalized);

        if ($normalized === '' || ! preg_match('/^[0-9]{9,15}$/', $normalized)) {
            $fail('The :attribute must be a valid phone number.');
        }
    }
}
