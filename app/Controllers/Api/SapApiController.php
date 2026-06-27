<?php

namespace App\Controllers\Api;

use App\BaseController;
use App\Services\SapApiService;

class SapApiController extends BaseController
{
    private SapApiService $service;

    public function __construct()
    {
        $this->service = new SapApiService();
    }

    public function pushSample(): string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            return $this->json(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        // Authenticate
        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $result = $this->service->pushSample($data);
        $status = $result['success'] ? 201 : 400;
        return $this->json($result, $status);
    }

    public function pushResult(): string
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            return $this->json(['success' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $result = $this->service->pushResult($data);
        $status = $result['success'] ? 201 : 400;
        return $this->json($result, $status);
    }

    public function pullCustomers(): string
    {
        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        return $this->json($this->service->pullCustomers());
    }

    public function pullProducts(): string
    {
        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        return $this->json($this->service->pullProducts());
    }

    public function pullSpecifications(): string
    {
        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        return $this->json($this->service->pullSpecifications());
    }

    public function status(): string
    {
        $auth = $this->authenticate();
        if (!$auth) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $syncService = new \App\Services\SapSyncService();
        return $this->json(['success' => true, 'data' => $syncService->getStatus()]);
    }

    private function authenticate(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Basic\s+(.+)/', $authHeader, $matches)) {
            $credentials = base64_decode($matches[1]);
            [$username, $password] = explode(':', $credentials, 2);
            return $this->service->authenticate($username, $password);
        }

        // Fallback to POST params for testing
        if (isset($_POST['api_username']) && isset($_POST['api_password'])) {
            return $this->service->authenticate($_POST['api_username'], $_POST['api_password']);
        }

        return null;
    }
}
