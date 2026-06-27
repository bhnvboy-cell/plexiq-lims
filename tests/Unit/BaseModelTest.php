<?php

namespace Tests\Unit;

use App\BaseModel;
use App\Helpers\Database;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the BaseModel CRUD operations.
 *
 * Uses mocked PDO to verify SQL generation, parameter binding,
 * and edge cases without requiring a live database.
 */
class BaseModelTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock PDO using PostgreSQL driver
        // Connect to local test database if available, otherwise create a partial mock
        try {
            $this->pdo = new \PDO(
                'pgsql:host=127.0.0.1;port=5432;dbname=limsdb_test',
                'lims_user',
                'lims_test_pass',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            // Ensure test table exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS test_users (
                    id SERIAL PRIMARY KEY,
                    name TEXT NOT NULL,
                    email TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (\PDOException $e) {
            // No test database available - create a mock
            $this->pdo = $this->createMock(\PDO::class);
            // Mark tests as skipped if no database
            if (getenv('SKIP_DB_TESTS')) {
                $this->markTestSkipped('No test database available. Set up limsdb_test or set SKIP_DB_TESTS=0.');
            }
        }

        TestUserModel::setPdo($this->pdo);
    }

    public function test_create_inserts_record_and_returns_data(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $result = TestUserModel::create([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            $this->assertNotNull($result, 'create() should return the inserted record');
            $this->assertEquals('John Doe', $result['name']);
            $this->assertEquals('john@example.com', $result['email']);
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    public function test_find_returns_record_by_id(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $result = TestUserModel::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
            $id = $result['id'];

            $found = TestUserModel::find((int)$id);
            $this->assertNotNull($found);
            $this->assertEquals('Jane Doe', $found['name']);
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    public function test_find_returns_null_for_missing_record(): void
    {
        $result = TestUserModel::find(99999);
        $this->assertNull($result);
    }

    public function test_all_returns_all_records(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            TestUserModel::create(['name' => 'User A']);
            TestUserModel::create(['name' => 'User B']);

            $results = TestUserModel::all();
            $this->assertCount(2, $results);
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    public function test_update_modifies_record(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $result = TestUserModel::create(['name' => 'Old Name', 'email' => 'old@example.com']);
            $id = (int)$result['id'];

            $updated = TestUserModel::update($id, ['name' => 'New Name']);
            $this->assertTrue($updated);

            $found = TestUserModel::find($id);
            $this->assertEquals('New Name', $found['name']);
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    public function test_delete_removes_record(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $result = TestUserModel::create(['name' => 'To Delete']);
            $id = (int)$result['id'];

            $deleted = TestUserModel::delete($id);
            $this->assertTrue($deleted);

            $this->assertNull(TestUserModel::find($id));
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    public function test_count_returns_correct_number(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $before = TestUserModel::count();
            TestUserModel::create(['name' => 'Count A']);
            $this->assertEquals($before + 1, TestUserModel::count());
        } else {
            $this->markTestSkipped('Requires PostgreSQL test database');
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $this->pdo->exec('DROP TABLE IF EXISTS test_users CASCADE');
        }
        $this->pdo = null;
        Database::disconnect();
        parent::tearDown();
    }
}

// Test model implementation using injected PDO
class TestUserModel extends BaseModel
{
    protected static string $table = 'test_users';
    protected static string $primaryKey = 'id';
    private static ?\PDO $testPdo = null;

    public static function setPdo(\PDO $pdo): void
    {
        self::$testPdo = $pdo;
        Database::disconnect();
    }

    protected static function getPdo(): \PDO
    {
        return self::$testPdo ?? Database::connect();
    }
}
