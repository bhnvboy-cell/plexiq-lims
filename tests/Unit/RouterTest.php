<?php

namespace Tests\Unit;

use App\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the custom Router class.
 *
 * Verifies:
 * - Route registration (GET, POST, PUT, DELETE)
 * - URL parameter matching
 * - Middleware execution
 * - CSRF protection
 * - 404 handling
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        $_SESSION = [];
    }

    public function test_registers_and_dispatches_get_route(): void
    {
        $this->router->get('/test', function () {
            return 'GET response';
        });

        ob_start();
        $this->router->dispatch('GET', '/test');
        $output = ob_get_clean();

        $this->assertEquals('GET response', $output);
    }

    public function test_registers_and_dispatches_post_route(): void
    {
        $this->setupCsrf();

        $this->router->post('/submit', function () {
            return 'POST response';
        });

        ob_start();
        $_POST['_csrf_token'] = $_SESSION['_csrf_token'];
        $this->router->dispatch('POST', '/submit');
        $output = ob_get_clean();

        $this->assertEquals('POST response', $output);
    }

    public function test_registers_and_dispatches_put_route(): void
    {
        $this->setupCsrf();

        $this->router->put('/update', function () {
            return 'PUT response';
        });

        ob_start();
        $_POST['_csrf_token'] = $_SESSION['_csrf_token'];
        $this->router->dispatch('PUT', '/update');
        $output = ob_get_clean();

        $this->assertEquals('PUT response', $output);
    }

    public function test_registers_and_dispatches_delete_route(): void
    {
        $this->setupCsrf();

        $this->router->delete('/remove', function () {
            return 'DELETE response';
        });

        ob_start();
        $_POST['_csrf_token'] = $_SESSION['_csrf_token'];
        $this->router->dispatch('DELETE', '/remove');
        $output = ob_get_clean();

        $this->assertEquals('DELETE response', $output);
    }

    public function test_route_with_numeric_parameter(): void
    {
        $this->router->get('/users/{id}', function (int $id) {
            return "User: $id";
        });

        ob_start();
        $this->router->dispatch('GET', '/users/42');
        $output = ob_get_clean();

        $this->assertEquals('User: 42', $output);
    }

    public function test_route_with_multiple_parameters(): void
    {
        $this->router->get('/samples/{id}/tests/{testId}', function (int $id, int $testId) {
            return "Sample $id, Test $testId";
        });

        ob_start();
        $this->router->dispatch('GET', '/samples/5/tests/12');
        $output = ob_get_clean();

        $this->assertEquals('Sample 5, Test 12', $output);
    }

    public function test_returns_404_for_unregistered_route(): void
    {
        ob_start();
        $this->router->dispatch('GET', '/nonexistent');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output ?? '');
    }

    public function test_returns_404_for_method_mismatch(): void
    {
        $this->setupCsrf();
        $this->router->get('/only-get', fn() => 'found');

        ob_start();
        $_POST['_csrf_token'] = $_SESSION['_csrf_token'];
        $this->router->dispatch('POST', '/only-get');
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output ?? '');
    }

    public function test_csrf_rejects_requests_without_token(): void
    {
        $this->router->post('/secure', fn() => 'should not reach');

        ob_start();
        unset($_POST['_csrf_token']);
        $_SESSION['_csrf_token'] = 'some-token';
        $this->router->dispatch('POST', '/secure');
        $output = ob_get_clean();

        $this->assertStringContainsString('419', $output ?? '');
    }

    public function test_csrf_rejects_mismatched_token(): void
    {
        $this->router->post('/secure', fn() => 'should not reach');

        ob_start();
        $_POST['_csrf_token'] = 'wrong-token';
        $_SESSION['_csrf_token'] = 'correct-token';
        $this->router->dispatch('POST', '/secure');
        $output = ob_get_clean();

        $this->assertStringContainsString('419', $output ?? '');
    }

    public function test_middleware_runs_before_handler(): void
    {
        $middlewareRan = false;

        $this->router->addMiddleware('test-mw', function () use (&$middlewareRan) {
            $middlewareRan = true;
            return true;
        });

        $this->router->get('/protected', fn() => 'content', ['test-mw']);

        ob_start();
        $this->router->dispatch('GET', '/protected');
        ob_get_clean();

        $this->assertTrue($middlewareRan, 'Middleware should have executed');
    }

    public function test_middleware_can_block_request(): void
    {
        $this->router->addMiddleware('blocker', function () {
            http_response_code(403);
            echo 'Blocked';
            return false;
        });

        $this->router->get('/blocked', fn() => 'should not reach', ['blocker']);

        ob_start();
        $this->router->dispatch('GET', '/blocked');
        $output = ob_get_clean();

        $this->assertEquals('Blocked', $output);
    }

    public function test_dispatches_controller_method(): void
    {
        $this->router->get('/controller-test', [TestController::class, 'handle']);

        ob_start();
        $this->router->dispatch('GET', '/controller-test');
        $output = ob_get_clean();

        $this->assertEquals('Controller handled', $output);
    }

    public function test_handles_trailing_slash_consistently(): void
    {
        $this->router->get('/dashboard', fn() => 'dashboard');

        ob_start();
        $this->router->dispatch('GET', '/dashboard/');
        $output = ob_get_clean();

        $this->assertEquals('dashboard', $output);
    }

    /**
     * Helper: set up a valid CSRF token pair in session.
     */
    private function setupCsrf(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}

// Helper controller for testing
class TestController
{
    public function handle(): string
    {
        return 'Controller handled';
    }
}
