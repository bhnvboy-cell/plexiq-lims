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
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'Admin';

        Auth::logout();

        $this->assertEmpty($_SESSION);
    }

    public function test_logout_does_not_crash_when_not_logged_in(): void
    {
        $_SESSION = ['random' => 'data'];
        Auth::logout();
        $this->assertEmpty($_SESSION);
    }

    public function test_password_policy_validates_length_and_complexity(): void
    {
        // Weak passwords should return policy errors
        $shortErrors = Auth::validatePasswordPolicy('Sh0rt!');
        $this->assertNotEmpty($shortErrors);
        $this->assertStringContainsString('at least 10 characters', $shortErrors[0]);

        $noUpper = Auth::validatePasswordPolicy('lowercase123!@#');
        $this->assertNotEmpty($noUpper);

        $noSpecial = Auth::validatePasswordPolicy('UpperLower123456');
        $this->assertNotEmpty($noSpecial);

        // Compliant password should return zero errors
        $valid = Auth::validatePasswordPolicy('ValidCompliantP@ss123');
        $this->assertEmpty($valid);
    }

    public function test_segregation_of_duties_prevents_author_from_reviewing(): void
    {
        $_SESSION['user_id'] = 10;

        // Author trying to review their own test result -> MUST FAIL
        $record = ['entered_by' => 10, 'reviewed_by' => null];
        $this->assertFalse(Auth::verifySegregationOfDuties('review', $record));

        // Different user reviewing -> Allowed
        $recordOther = ['entered_by' => 99, 'reviewed_by' => null];
        $this->assertTrue(Auth::verifySegregationOfDuties('review', $recordOther));
    }

    public function test_segregation_of_duties_prevents_author_or_reviewer_from_approving(): void
    {
        $_SESSION['user_id'] = 10;

        // Author trying to approve -> MUST FAIL
        $recordAuthor = ['entered_by' => 10, 'reviewed_by' => 20];
        $this->assertFalse(Auth::verifySegregationOfDuties('approve', $recordAuthor));

        // Reviewer trying to approve -> MUST FAIL
        $recordReviewer = ['entered_by' => 5, 'reviewed_by' => 10];
        $this->assertFalse(Auth::verifySegregationOfDuties('approve', $recordReviewer));

        // Independent 3rd party approving -> Allowed
        $recordIndependent = ['entered_by' => 5, 'reviewed_by' => 20];
        $this->assertTrue(Auth::verifySegregationOfDuties('approve', $recordIndependent));
    }

    public function test_admin_has_granular_permissions_by_default(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'Admin';

        $this->assertTrue(Auth::can('samples.create'));
        $this->assertTrue(Auth::can('results.approve'));
        $this->assertTrue(Auth::can('audit.verify'));
    }

    public function test_upload_validator_rejects_disallowed_extensions(): void
    {
        $badFile = [
            'name' => 'malicious.php',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'tmp_name' => ''
        ];
        $errors = \App\Helpers\UploadValidator::validate($badFile);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not permitted', $errors[0]);

        $goodFile = [
            'name' => 'run_data.csv',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'tmp_name' => ''
        ];
        $validErrors = \App\Helpers\UploadValidator::validate($goodFile);
        $this->assertEmpty($validErrors);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}
