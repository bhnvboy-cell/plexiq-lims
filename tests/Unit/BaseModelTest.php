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
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '5432');
            $dbName = env('DB_DATABASE', 'limsdb');
            $user = env('DB_USERNAME', 'postgres');
            $pass = env('DB_PASSWORD', '');
            $this->pdo = new \PDO(
                "pgsql:host={$host};port={$port};dbname={$dbName}",
                $user,
                $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            // Ensure test table exists and is empty
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS test_users (
                    id SERIAL PRIMARY KEY,
                    name TEXT NOT NULL,
                    email TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
                TRUNCATE TABLE test_users RESTART IDENTITY;
            ");
        } catch (\PDOException $e) {
            // No test database available - create a mock
            $this->pdo = $this->createMock(\PDO::class);
        }

        TestUserModel::setPdo($this->pdo);
    }

    public function test_find_returns_null_for_missing_record(): void
    {
        if (!($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql')) {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('execute')->with([99999])->willReturn(true);
            $stmt->expects($this->once())->method('fetch')->with(\PDO::FETCH_ASSOC)->willReturn(false);
            $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);
        }
        $result = TestUserModel::find(99999);
        $this->assertNull($result);
    }

    public function test_create_inserts_record_and_returns_data(): void
    {
        if (!($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql')) {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('execute')->with(['John Doe', 'john@example.com'])->willReturn(true);
            $stmt->expects($this->once())->method('fetch')->with(\PDO::FETCH_ASSOC)->willReturn([
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);
            $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);
        }
        $result = TestUserModel::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertNotNull($result, 'create() should return the inserted record');
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function test_find_returns_record_by_id(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $result = TestUserModel::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
            $id = (int)$result['id'];
        } else {
            $id = 1;
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('execute')->with([1])->willReturn(true);
            $stmt->expects($this->once())->method('fetch')->with(\PDO::FETCH_ASSOC)->willReturn([
                'id' => 1,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
            ]);
            $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);
        }

        $found = TestUserModel::find($id);
        $this->assertNotNull($found);
        $this->assertEquals('Jane Doe', $found['name']);
    }

    public function test_all_returns_all_records(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            TestUserModel::create(['name' => 'User A']);
            TestUserModel::create(['name' => 'User B']);
        } else {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn([
                ['id' => 1, 'name' => 'User A'],
                ['id' => 2, 'name' => 'User B'],
            ]);
            $this->pdo->expects($this->once())->method('query')->willReturn($stmt);
        }

        $results = TestUserModel::all();
        $this->assertCount(2, $results);
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
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('execute')->with(['New Name', 1])->willReturn(true);
            $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

            $updated = TestUserModel::update(1, ['name' => 'New Name']);
            $this->assertTrue($updated);
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
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('execute')->with([1])->willReturn(true);
            $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

            $deleted = TestUserModel::delete(1);
            $this->assertTrue($deleted);
        }
    }

    public function test_count_returns_correct_number(): void
    {
        if ($this->pdo instanceof \PDO && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $before = TestUserModel::count();
            TestUserModel::create(['name' => 'Count A']);
            $this->assertEquals($before + 1, TestUserModel::count());
        } else {
            $stmt = $this->createMock(\PDOStatement::class);
            $stmt->expects($this->once())->method('fetchColumn')->willReturn(5);
            $this->pdo->expects($this->once())->method('query')->willReturn($stmt);

            $this->assertEquals(5, TestUserModel::count());
        }
    }

    public function test_validate_identifier_rejects_sql_injection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUserModel::checkIdentifier('name; DROP TABLE users; --');
    }

    public function test_normalize_values_converts_booleans_for_postgres(): void
    {
        $normalized = TestUserModel::checkNormalize([true, false, 'text', 0, null]);
        $this->assertSame(['t', 'f', 'text', 0, null], $normalized);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo instanceof \PDO) {
            try {
                if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $this->pdo->exec('DROP TABLE IF EXISTS test_users CASCADE');
                }
            } catch (\Throwable $e) {
            }
        }
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

    public static function checkIdentifier(string $col): string
    {
        return static::validateIdentifier($col);
    }

    public static function checkNormalize(array $values): array
    {
        return static::normalizeValues($values);
    }
}
