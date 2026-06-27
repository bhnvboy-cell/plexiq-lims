<?php

namespace Tests;

use App\Helpers\Database;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for PlexiQ LIMS.
 *
 * Provides:
 * - Database connection helpers
 * - Seed data loading
 * - Transaction-based test isolation
 * - Mock request/response utilities
 */
abstract class TestCase extends BaseTestCase
{
    protected static bool $schemaLoaded = false;

    protected ?\PDO $db = null;

    /**
     * Get a database connection for testing.
     * Creates one if not already set up.
     */
    protected function db(): \PDO
    {
        if ($this->db === null) {
            $this->db = Database::connect();
        }
        return $this->db;
    }

    /**
     * Load schema into the test database.
     * Runs only once per test suite run.
     */
    protected static function loadSchema(): void
    {
        if (self::$schemaLoaded) return;

        $conn = Database::connect();
        $schemaFile = BASE_PATH . '/database/schema.sql';

        if (!file_exists($schemaFile)) {
            throw new \RuntimeException("Schema file not found: $schemaFile");
        }

        $sql = file_get_contents($schemaFile);
        $conn->exec($sql);
        self::$schemaLoaded = true;
    }

    /**
     * Load seed data into the test database.
     */
    protected static function loadSeeds(): void
    {
        $conn = Database::connect();
        $seedFile = BASE_PATH . '/database/seed_data.sql';

        if (!file_exists($seedFile)) {
            throw new \RuntimeException("Seed file not found: $seedFile");
        }

        $sql = file_get_contents($seedFile);
        $conn->exec($sql);
    }

    /**
     * Begin a database transaction for test isolation.
     */
    protected function beginTransaction(): void
    {
        $this->db()->beginTransaction();
    }

    /**
     * Roll back the current transaction.
     */
    protected function rollBack(): void
    {
        if ($this->db && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Create a mock POST request by setting $_POST and $_SERVER.
     */
    protected function mockPostRequest(array $data, array $headers = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }
    }

    /**
     * Create a mock GET request.
     */
    protected function mockGetRequest(array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $query;
    }

    /**
     * Simulate an authenticated session.
     */
    protected function actingAs(int $userId = 1, string $role = 'Admin'): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_name'] = 'Test User';
        $_SESSION['user_username'] = 'testuser';
    }

    /**
     * Clear the authenticated session.
     */
    protected function clearAuth(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_name'], $_SESSION['user_username']);
    }

    protected function tearDown(): void
    {
        $this->rollBack();
        Database::disconnect();
        parent::tearDown();
    }
}
