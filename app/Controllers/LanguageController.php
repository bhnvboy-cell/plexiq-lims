<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class LanguageController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $languages = $db->query("SELECT * FROM languages ORDER BY is_default DESC, language_name")->fetchAll(\PDO::FETCH_ASSOC);
        $result = \App\Helpers\Pagination::run($db, "
            SELECT t.*, l.language_code, l.language_name
            FROM translations t
            JOIN languages l ON t.language_id = l.id
        ", "
            SELECT COUNT(*)
            FROM translations t
        ", [], 50, 'l.language_name, t.translation_key');
        $selectedLang = $_GET['lang'] ?? ($languages[0]['language_code'] ?? 'en');
        $selectedLangName = '';
        foreach ($languages as $l) {
            if ($l['language_code'] === $selectedLang) { $selectedLangName = $l['language_name']; break; }
        }
        $filters = $_GET['filters'] ?? '';
        return $this->render('languages.index', [
            'languages' => $languages,
            'translations' => $result['items'],
            'paginator' => $result,
            'selectedLang' => $selectedLang,
            'selectedLangName' => $selectedLangName,
            'filters' => $filters,
        ]);
    }

    public function switchLang(string $code): void
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT id FROM languages WHERE language_code = ? AND is_active = TRUE");
        $stmt->execute([$code]);
        $lang = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($lang) {
            $_SESSION['lang'] = $code;
        }
        $this->back();
    }

    public function createTranslation(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("INSERT INTO translations (language_id, translation_key, translation_value, module) VALUES (?, ?, ?, ?)")->execute([
            $_POST['language_id'],
            $_POST['translation_key'],
            $_POST['translation_value'],
            $_POST['group_name'] ?? $_POST['module'] ?? 'general',
        ]);
        Audit::log('Translation Created', 'translations', null, null, ['key' => $_POST['translation_key'], 'language_id' => $_POST['language_id']]);
        session_flash('success', 'Translation added.');
        $this->redirect('/languages');
    }

    public function updateTranslation(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("UPDATE translations SET translation_value = ?, module = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([
            $_POST['translation_value'],
            $_POST['group_name'] ?? $_POST['module'] ?? 'general',
            $id,
        ]);
        Audit::log('Translation Updated', 'translations', $id);
        session_flash('success', 'Translation updated.');
        $this->redirect('/languages');
    }

    public function export(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $langCode = $_GET['lang'] ?? null;
        if ($langCode) {
            $stmt = $db->prepare("
                SELECT t.translation_key, t.translation_value, t.module
                FROM translations t
                JOIN languages l ON t.language_id = l.id
                WHERE l.language_code = ?
            ");
            $stmt->execute([$langCode]);
        } else {
            $stmt = $db->query("
                SELECT t.translation_key, t.translation_value, t.module, l.language_code
                FROM translations t
                JOIN languages l ON t.language_id = l.id
            ");
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="translations_' . ($langCode ?? 'all') . '_' . date('Y-m-d') . '.json"');
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
