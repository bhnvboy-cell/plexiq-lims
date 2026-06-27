<?php

namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class PluginController extends BaseController
{
    public function index(): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $plugins = $db->query("SELECT * FROM plugins ORDER BY plugin_name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->render('plugins.index', ['plugins' => $plugins]);
    }

    public function install(): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $existing = $db->prepare("SELECT id FROM plugins WHERE plugin_name = ?");
        $existing->execute([$_POST['name']]);
        if ($existing->fetch()) {
            session_flash('error', 'Plugin already installed.');
            $this->redirect('/plugins');
        }
        $db->prepare("INSERT INTO plugins (plugin_code, plugin_name, description, version, author, settings, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([
            $_POST['plugin_code'] ?? preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['plugin_name'] ?? 'plugin_' . time())),
            $_POST['plugin_name'],
            $_POST['description'] ?? '',
            $_POST['version'] ?? '1.0.0',
            $_POST['author'] ?? 'Unknown',
            $_POST['settings'] ?? '{}',
            !empty($_POST['is_active']),
        ]);
        $pluginId = $db->lastInsertId();
        Audit::log('Plugin Installed', 'plugins', $pluginId);
        session_flash('success', 'Plugin installed.');
        $this->redirect('/plugins');
    }

    public function uninstall(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $db->prepare("DELETE FROM plugins WHERE id = ?")->execute([$id]);
        Audit::log('Plugin Uninstalled', 'plugins', $id);
        session_flash('success', 'Plugin uninstalled.');
        $this->redirect('/plugins');
    }

    public function toggle(int $id): void
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT is_active FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($plugin) {
            $db->prepare("UPDATE plugins SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([!$plugin['is_active'], $id]);
            Audit::log('Plugin Toggled', 'plugins', $id);
        }
        $this->redirect('/plugins');
    }

    public function settings(int $id): string
    {
        Auth::requireRole('Admin');
        $db = \App\Helpers\Database::connect();
        $stmt = $db->prepare("SELECT * FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        $plugin = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$plugin) { session_flash('error', 'Plugin not found.'); $this->redirect('/plugins'); }
        $config = json_decode($plugin['config'] ?? '{}', true);
        return $this->render('plugins.settings', ['plugin' => $plugin, 'config' => $config]);
    }
}
