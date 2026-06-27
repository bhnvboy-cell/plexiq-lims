<?php

namespace Tests\Unit;

use App\Helpers\Auth;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Auth helper class.
 *
 * Uses isolated session state to verify:
 * - Session-based authentication check
 * - Role verification
 * - Login/logout flow
 *
 * Note: Full login integration testing requires a database.
 * These tests verify the session and role logic layer.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function test_check_returns_false_when_not_logged_in(): void
    {
        $this->assertFalse(Auth::check());
    }

    public function test_check_returns_true_when_logged_in(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(Auth::check());
    }

    public function test_id_returns_null_when_not_logged_in(): void
    {
        $this->assertNull(Auth::id());
    }

    public function test_id_returns_user_id_when_logged_in(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertEquals(42, Auth::id());
    }

    public function test_role_returns_null_when_not_set(): void
    {
        $this->assertNull(Auth::role());
    }

    public function test_role_returns_stored_role(): void
    {
        $_SESSION['user_role'] = 'Admin';
        $this->assertEquals('Admin', Auth::role());
    }

    public function test_hasRole_returns_true_for_matching_role(): void
    {
        $_SESSION['user_role'] = 'Analyst';
        $this->assertTrue(Auth::hasRole('Analyst'));
    }

    public function test_hasRole_returns_false_for_non_matching_role(): void
    {
        $_SESSION['user_role'] = 'Analyst';
        $this->assertFalse(Auth::hasRole('Admin'));
    }

    public function test_hasAnyRole_returns_true_when_one_matches(): void
    {
        $_SESSION['user_role'] = 'Reviewer';
        $this->assertTrue(Auth::hasAnyRole(['Admin', 'Reviewer', 'Approver']));
    }

    public function test_hasAnyRole_returns_false_when_none_match(): void
    {
        $_SESSION['user_role'] = 'Customer';
        $this->assertFalse(Auth::hasAnyRole(['Admin', 'Analyst']));
    }

    public function test_logout_clears_session_and_destroys(): void
    {
        if (getenv('SKIP_DB_TESTS')) {
            $this->markTestSkipped('Auth::logout requires database');
        }

        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'Admin';

        Auth::logout();

        $this->assertEmpty($_SESSION);
    }

    public function test_logout_does_not_crash_when_not_logged_in(): void
    {
        if (getenv('SKIP_DB_TESTS')) {
            $this->markTestSkipped('Auth::logout requires database');
        }

        $_SESSION = ['random' => 'data'];
        Auth::logout();
        $this->assertEmpty($_SESSION);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}
