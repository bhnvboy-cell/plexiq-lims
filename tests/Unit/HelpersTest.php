<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for global helper functions.
 *
 * Covers:
 * - String escaping
 * - CSRF token generation and validation
 * - Session flash messages
 * - Status badge rendering
 * - Money formatting
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function test_e_escapes_html_special_chars(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&#039;xss&#039;);&lt;/script&gt;', e("<script>alert('xss');</script>"));
    }

    public function test_e_returns_empty_string_for_null(): void
    {
        $this->assertEquals('', e(null));
    }

    public function test_e_does_not_double_encode(): void
    {
        $this->assertEquals('&amp;', e('&'));
    }

    public function test_csrf_token_generates_and_persists(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();

        $this->assertNotEmpty($token1);
        $this->assertEquals($token1, $token2, 'Token should persist within same session');
    }

    public function test_csrf_token_is_hex_string(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_csrf_field_returns_hidden_input(): void
    {
        $token = csrf_token();
        $field = csrf_field();

        $this->assertStringContainsString('_csrf_token', $field);
        $this->assertStringContainsString($token, $field);
    }

    public function test_session_flash_stores_and_retrieves(): void
    {
        session_flash('success', 'Operation completed');
        $this->assertEquals('Operation completed', session_flash('success'));
    }

    public function test_session_flash_returns_null_after_read(): void
    {
        session_flash('info', 'Some message');
        session_flash('info'); // consume
        $this->assertNull(session_flash('info'));
    }

    public function test_session_flash_returns_null_for_missing_key(): void
    {
        $this->assertNull(session_flash('nonexistent'));
    }

    public function test_format_money_defaults_to_usd(): void
    {
        $this->assertEquals('$1,234.56', format_money(1234.56));
    }

    public function test_format_money_with_currency(): void
    {
        $this->assertEquals('€500.00', format_money(500, 'EUR'));
        $this->assertEquals('£75.25', format_money(75.25, 'GBP'));
        $this->assertEquals('₹1,000.00', format_money(1000, 'INR'));
    }

    public function test_status_badge_returns_html(): void
    {
        $badge = status_badge('Approved');
        $this->assertStringContainsString('badge', $badge);
        $this->assertStringContainsString('bg-success', $badge);
        $this->assertStringContainsString('Approved', $badge);
    }

    public function test_status_badge_uses_secondary_for_unknown(): void
    {
        $badge = status_badge('UnknownStatus');
        $this->assertStringContainsString('bg-secondary', $badge);
    }

    public function test_status_badge_mapping(): void
    {
        $cases = [
            'Registered' => 'secondary',
            'Pending' => 'secondary',
            'In Progress' => 'info',
            'Completed' => 'success',
            'Reviewed' => 'primary',
            'Approved' => 'success',
            'Rejected' => 'danger',
            'COA Released' => 'success',
        ];

        foreach ($cases as $status => $expectedClass) {
            $badge = status_badge($status);
            $this->assertStringContainsString("bg-$expectedClass", $badge, "Status '$status' should map to '$expectedClass'");
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}
