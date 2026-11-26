<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Services\Ai\QualityAiAdvisorService;

class QualityAiController extends BaseController
{
    public function index(): string
    {
        Auth::requireAuth();
        $db = Database::connect();
        $suggestions = $db->query("
            SELECT s.*, u.full_name, u.username
            FROM ai_quality_suggestions s
            LEFT JOIN users u ON s.reviewed_by = u.id
            ORDER BY s.id DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('quality.ai_advisor', [
            'suggestions' => $suggestions
        ]);
    }

    public function analyze(): string
    {
        Auth::requireAuth();
        $entityType = $_POST['entity_type'] ?? 'oos';
        $entityId = (int)($_POST['entity_id'] ?? 1);
        $context = [
            'description' => $_POST['description'] ?? '',
            'test_name' => $_POST['test_name'] ?? '',
            'reported_value' => $_POST['reported_value'] ?? null,
            'expected_limit' => $_POST['expected_limit'] ?? null
        ];

        try {
            $advisor = new QualityAiAdvisorService();
            $result = $advisor->analyzeIncident($entityType, $entityId, $context);
            return $this->json(['success' => true, 'analysis' => $result]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function review(int $id): string
    {
        Auth::requireRole('QA Manager');
        $decision = $_POST['decision'] ?? 'accepted';
        $notes = $_POST['notes'] ?? '';

        try {
            $advisor = new QualityAiAdvisorService();
            $result = $advisor->reviewSuggestion($id, Auth::id(), $decision, $notes);
            return $this->json(['success' => true, 'review' => $result]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
