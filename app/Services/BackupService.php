<?php

namespace App\Services;

use App\Helpers\Audit;
use App\Helpers\Database;

/**
 * Backup & restore engine.
 *
 * Backups are plain-SQL PostgreSQL dumps (pg_dump -Fp --clean --if-exists --no-owner)
 * stored under storage/backups/ with a sidecar meta.json manifest.
 * Restore replays the dump through the psql client.
 */
class BackupService
{
    private string $dir;

    public function __construct()
    {
        $this->dir = storage_path(ltrim((string)config('backup.path', 'backups'), '/'));
        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true)) {
            throw new \RuntimeException('Unable to create backup directory: ' . $this->dir);
        }
    }

    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * Run a backup. Returns manifest array on success.
     */
    public function create(string $type = 'manual'): array
    {
        $started = microtime(true);
        $db = config('database');
        $pgDump = $this->findBinary('pg_dump_path', ['pg_dump', 'C:\Program Files\PostgreSQL\18\bin\pg_dump.exe', 'C:\Program Files\PostgreSQL\17\bin\pg_dump.exe', 'C:\Program Files\PostgreSQL\16\bin\pg_dump.exe', '/usr/bin/pg_dump', '/usr/local/bin/pg_dump']);

        $name = 'plexiq_' . date('Ymd_His') . '_' . strtolower($type) . '.sql';
        $file = $this->dir . '/' . $name;
        $temp = $file . '.tmp';

        $command = escapeshellarg($pgDump)
            . ' -h ' . escapeshellarg($db['host'])
            . ' -p ' . escapeshellarg((string)$db['port'])
            . ' -U ' . escapeshellarg($db['username'])
            . ' ' . (string)config('backup.dump_flags')
            . ' -f ' . escapeshellarg($temp)
            . ' ' . escapeshellarg($db['database']);

        [$output, $code] = $this->run($command);
        if ($code !== 0 || !file_exists($temp) || filesize($temp) === 0) {
            $message = 'pg_dump failed (exit ' . $code . '): ' . trim(implode("\n", $output));
            $this->recordRun($type, null, 0, 'failed', $message, $started);
            throw new \RuntimeException($message);
        }

        rename($temp, $file);

        $meta = [
            'file' => $name,
            'size' => filesize($file),
            'created_at' => date('c'),
            'type' => $type,
            'app_version' => '1.0',
            'database' => $db['database'],
            'pg_version' => $this->serverVersion(),
            'sha256' => hash_file('sha256', $file),
        ];
        file_put_contents($this->dir . '/' . $name . '.meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->recordRun($type, $name, $meta['size'], 'success', '', $started);
        $this->prune();

        return $meta;
    }

    /**
     * List backups, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $items = [];
        foreach (glob($this->dir . '/*.sql') ?: [] as $file) {
            $base = basename($file);
            $metaFile = $this->dir . '/' . $base . '.meta.json';
            $meta = file_exists($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : null;
            if (!is_array($meta)) {
                $meta = [
                    'file' => $base,
                    'size' => filesize($file),
                    'created_at' => date('c', filemtime($file)),
                    'type' => 'unknown',
                    'sha256' => hash_file('sha256', $file),
                ];
            }
            $meta['size'] = (int)($meta['size'] ?? filesize($file));
            $meta['path'] = $file;
            $meta['valid'] = (string)hash_file('sha256', $file) === (string)($meta['sha256'] ?? '') || empty($meta['sha256']);
            $items[] = $meta;
        }
        usort($items, fn ($a, $b) => strcmp($b['file'], $a['file']));
        return $items;
    }

    /**
     * Validate a backup filename and return its absolute path, or null.
     */
    public function resolve(string $fileName): ?string
    {
        if (trim($fileName) === '' || str_contains($fileName, '/') || str_contains($fileName, '\\') || str_contains($fileName, '..')) {
            return null;
        }
        $file = realpath($this->dir . '/' . $fileName);
        if ($file === false || !str_starts_with($file, realpath($this->dir) . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $file;
    }

    /**
     * Restore a backup by replaying the SQL dump through psql.
     * The dump contains DROP ... IF EXISTS / CREATE statements, so existing
     * objects of the same name are replaced.
     */
    public function restore(string $fileName): array
    {
        $started = microtime(true);
        $file = $this->resolve($fileName);
        if ($file === null) {
            throw new \RuntimeException('Invalid or missing backup file.');
        }

        $db = config('database');
        $psql = $this->findBinary('psql_path', ['psql', 'C:\Program Files\PostgreSQL\18\bin\psql.exe', 'C:\Program Files\PostgreSQL\17\bin\psql.exe', 'C:\Program Files\PostgreSQL\16\bin\psql.exe', '/usr/bin/psql', '/usr/local/bin/psql']);

        $command = escapeshellarg($psql)
            . ' -h ' . escapeshellarg($db['host'])
            . ' -p ' . escapeshellarg((string)$db['port'])
            . ' -U ' . escapeshellarg($db['username'])
            . ' -d ' . escapeshellarg($db['database'])
            . ' -v ON_ERROR_STOP=1 -f ' . escapeshellarg($file);

        [$output, $code] = $this->run($command);
        $duration = (int)((microtime(true) - $started) * 1000);

        if ($code !== 0) {
            $message = 'Restore failed (exit ' . $code . '): ' . trim(implode("\n", array_slice($output, -6)));
            $this->recordRun('restore', $fileName, (int)@filesize($file), 'failed', $message, $started);
            throw new \RuntimeException($message);
        }

        \App\Helpers\Cache::flush();
        $this->recordRun('restore', $fileName, (int)@filesize($file), 'success', '', $started, $duration);
        return ['file' => $fileName, 'duration_ms' => $duration];
    }

    public function delete(string $fileName): bool
    {
        $file = $this->resolve($fileName);
        if ($file === null) {
            return false;
        }
        $ok = @unlink($file);
        @unlink($file . '.meta.json');
        return $ok;
    }

    public function prune(): int
    {
        $keep = max(1, (int)$this->setting('retention_count', config('backup.retention_count', 10)));
        $files = $this->list();
        $removed = 0;
        foreach (array_slice($files, $keep) as $old) {
            if ($this->delete((string)$old['file'])) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Run a backup + restore round trip and return true on success.
     * Used to validate that a backup is restorable without wiping data
     * (restore is a no-op against the same dump only in tests; in practice
     * it replays CREATE/DROP which is destructive by design).
     */
    public function settings(): array
    {
        $rows = Database::connect()->query('SELECT setting_key, setting_value FROM backup_settings')->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public function saveSettings(array $values): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE backup_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?');
        foreach ($values as $key => $value) {
            $stmt->execute([(string)$value, (string)$key]);
        }
    }

    public function recentRuns(int $limit = 10): array
    {
        return Database::connect()
            ->query('SELECT br.*, u.full_name AS user_name FROM backup_runs br LEFT JOIN users u ON br.triggered_by = u.id ORDER BY br.id DESC LIMIT ' . max(1, $limit))
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function setting(string $key, $default = ''): string
    {
        $stmt = Database::connect()->prepare('SELECT setting_value FROM backup_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? (string)$default : (string)$value;
    }

    private function serverVersion(): string
    {
        try {
            return (string)Database::connect()->query('SHOW server_version')->fetchColumn();
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    private function recordRun(string $type, ?string $fileName, int $size, string $status, string $message, float $started, ?int $duration = null): void
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare('INSERT INTO backup_runs (backup_type, file_name, file_size, status, message, duration_ms, triggered_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $type,
                $fileName,
                $size,
                $status,
                $message !== '' ? mb_substr($message, 0, 2000) : null,
                $duration ?? (int)((microtime(true) - $started) * 1000),
                $_SESSION['user_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Logging a run must never break the backup itself.
            error_log('backup_runs insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Locate a PostgreSQL client binary. Returns a path/name or throws.
     */
    private function findBinary(string $configKey, array $candidates): string
    {
        $configured = (string)config('backup.' . $configKey, '');
        if ($configured !== '') {
            $candidates = array_merge([$configured], $candidates);
        }
        foreach ($candidates as $candidate) {
            if (strpbrk($candidate, '/\\') !== false && is_file($candidate)) {
                return $candidate;
            }
            if (strpbrk($candidate, '/\\') === false && $this->commandExists($candidate)) {
                return $candidate;
            }
        }
        throw new \RuntimeException(
            'PostgreSQL client binary not found. Install PostgreSQL or set ' . strtoupper(str_replace('_path', '_path', $configKey)) . ' in .env.'
        );
    }

    private function commandExists(string $name): bool
    {
        $cmd = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($name) . ' 2>NUL'
            : 'command -v ' . escapeshellarg($name) . ' 2>/dev/null';
        [$out, $code] = $this->run($cmd);
        return $code === 0 && !empty($out);
    }

    /**
     * Execute a command with the DB password exported, capturing combined output.
     *
     * @return array{0: string[], 1: int}
     */
    private function run(string $command): array
    {
        $previous = getenv('PGPASSWORD');
        putenv('PGPASSWORD=' . (string)config('database.password'));
        $output = [];
        $code = 0;
        $full = $command . ' 2>&1';
        exec($full, $output, $code);
        if ($previous === false) {
            putenv('PGPASSWORD');
        } else {
            putenv('PGPASSWORD=' . $previous);
        }
        return [$output, $code];
    }
}
