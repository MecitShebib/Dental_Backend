<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidPhone;
use PHPUnit\Framework\TestCase;

class ValidPhoneTest extends TestCase
{
    private function passes(string $value): bool
    {
        $failed = false;
        (new ValidPhone)->validate('phone', $value, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    public function test_accepts_a_plain_digit_string_within_length_bounds(): void
    {
        $this->assertTrue($this->passes('963955123456'));
        $this->assertTrue($this->passes('123456789')); // exactly 9, the floor
    }

    public function test_accepts_a_leading_plus_and_internal_whitespace_same_as_the_frontends_own_isvalidphone(): void
    {
        $this->assertTrue($this->passes('+963 955 123 456'));
        $this->assertTrue($this->passes(' 963955123456 '));
    }

    public function test_rejects_too_short_or_too_long(): void
    {
        $this->assertFalse($this->passes('12345678')); // 8, one under the floor
        $this->assertFalse($this->passes(str_repeat('1', 16))); // one over the ceiling
    }

    public function test_rejects_non_digit_characters(): void
    {
        $this->assertFalse($this->passes('abc9551234'));
        $this->assertFalse($this->passes('963-955-1234'));
    }

    public function test_rejects_empty(): void
    {
        $this->assertFalse($this->passes(''));
        $this->assertFalse($this->passes('   '));
    }
}
