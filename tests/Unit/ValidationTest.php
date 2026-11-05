<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for input validation logic.
 *
 * Validates the validation rules used across the application:
 * - Required fields
 * - String length (min/max)
 * - Email format
 * - Numeric values
 * - Allowed values (in: rule)
 */
class ValidationTest extends TestCase
{
    private function validate(array $data, array $rules): array
    {
        $controller = new class extends \App\BaseController {
            public function runValidate(array $data, array $rules): array
            {
                return $this->validate($data, $rules);
            }
        };
        return $controller->runValidate($data, $rules);
    }

    public function test_required_field_accepts_zero(): void
    {
        $this->assertArrayNotHasKey('qty', $this->validate(['qty' => 0], ['qty' => 'required|numeric']));
        $this->assertArrayNotHasKey('qty', $this->validate(['qty' => '0'], ['qty' => 'required|numeric']));
    }

    public function test_optional_field_skips_rules_when_empty(): void
    {
        $this->assertArrayNotHasKey('email', $this->validate(['email' => ''], ['email' => 'email']));
        $this->assertArrayNotHasKey('notes', $this->validate([], ['notes' => 'min:5|max:100']));
    }

    public function test_required_field_validates(): void
    {
        $errors = $this->validate(['name' => ''], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_required_field_passes_when_present(): void
    {
        $errors = $this->validate(['name' => 'John'], ['name' => 'required']);
        $this->assertArrayNotHasKey('name', $errors);
    }

    public function test_min_length_validates(): void
    {
        $errors = $this->validate(['password' => 'ab'], ['password' => 'min:8']);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_min_length_passes(): void
    {
        $errors = $this->validate(['password' => 'abcdefgh'], ['password' => 'min:8']);
        $this->assertArrayNotHasKey('password', $errors);
    }

    public function test_max_length_validates(): void
    {
        $errors = $this->validate(['name' => str_repeat('a', 101)], ['name' => 'max:100']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_max_length_passes(): void
    {
        $errors = $this->validate(['name' => str_repeat('a', 100)], ['name' => 'max:100']);
        $this->assertArrayNotHasKey('name', $errors);
    }

    public function test_email_validates_invalid_format(): void
    {
        $errors = $this->validate(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_email_passes_valid_format(): void
    {
        $errors = $this->validate(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function test_numeric_validates_non_numeric(): void
    {
        $errors = $this->validate(['amount' => 'abc'], ['amount' => 'numeric']);
        $this->assertArrayHasKey('amount', $errors);
    }

    public function test_numeric_passes_for_numbers(): void
    {
        $errors = $this->validate(['amount' => '42.5'], ['amount' => 'numeric']);
        $this->assertArrayNotHasKey('amount', $errors);
    }

    public function test_in_rule_validates_allowed_values(): void
    {
        $errors = $this->validate(['priority' => 'INVALID'], ['priority' => 'in:Low,Normal,High,Urgent']);
        $this->assertArrayHasKey('priority', $errors);
    }

    public function test_in_rule_passes_for_allowed_values(): void
    {
        foreach (['Low', 'Normal', 'High', 'Urgent'] as $value) {
            $errors = $this->validate(['priority' => $value], ['priority' => 'in:Low,Normal,High,Urgent']);
            $this->assertArrayNotHasKey('priority', $errors, "Value '$value' should be allowed");
        }
    }

    public function test_multiple_rules_on_same_field(): void
    {
        $errors = $this->validate(
            ['username' => 'ab'],
            ['username' => 'required|min:3|max:50']
        );
        $this->assertArrayHasKey('username', $errors);
    }

    public function test_multiple_rules_all_pass(): void
    {
        $errors = $this->validate(
            ['username' => 'validuser'],
            ['username' => 'required|min:3|max:50']
        );
        $this->assertArrayNotHasKey('username', $errors);
    }
}
