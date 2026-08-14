#!/usr/bin/env php
<?php
/**
 * PlexiQ LIMS Queue Worker
 *
 * Processes jobs from the jobs table asynchronously.
 *
 * Usage:
 *   php bin/worker.php [--once] [--sleep=5] [--queue=default] [--stop-when-empty]
 *
 * Examples:
 *   php bin/worker.php --once                          # process a single job
 *   php bin/worker.php --sleep=3 --queue=webhooks      # poll webhooks queue every 3s
 *   php bin/worker.php --stop-when-empty               # drain queue then exit
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

require_once __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

require_once __DIR__ . '/../app/Helpers/helpers.php';

$once = in_array('--once', $argv, true);
$stopWhenEmpty = in_array('--stop-when-empty', $argv, true);
$sleep = 5;
$queue = (string)config('queue.default_queue', 'default');

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sleep=')) {
        $sleep = max(1, (int)substr($arg, 8));
    }
    if (str_starts_with($arg, '--queue=')) {
        $queue = substr($arg, 8);
    }
}

$db = \App\Helpers\Database::connect();

echo '[' . date('Y-m-d H:i:s') . "] Worker started (queue={$queue}, sleep={$sleep}s)\n";

while (true) {
    $job = claim($db, $queue);
    if ($job === null) {
        if ($once || $stopWhenEmpty) {
            break;
        }
        sleep($sleep);
        continue;
    }

    process($db, $job);

    if ($once) {
        break;
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Worker exiting\n";

function claim(\PDO $db, string $queue): ?array
{
    $db->beginTransaction();
    $stmt = $db->prepare(
        "SELECT * FROM jobs
         WHERE status = 'pending' AND queue = ? AND available_at <= CURRENT_TIMESTAMP
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE SKIP LOCKED"
    );
    $stmt->execute([$queue]);
    $job = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$job) {
        $db->rollBack();
        return null;
    }
    $db->prepare(
        "UPDATE jobs SET status = 'running', reserved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    )->execute([$job['id']]);
    $db->commit();
    return $job;
}

function process(\PDO $db, array $job): void
{
    $id = (int)$job['id'];
    $payload = json_decode($job['payload'] ?? '{}', true) ?: [];
    try {
        $handler = resolveJob($job['job']);
        $handler->handle($payload);
        $db->prepare(
            "UPDATE jobs SET status = 'completed', completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$id]);
        echo '[' . date('Y-m-d H:i:s') . "] Job #{$id} ({$job['job']}) completed\n";
    } catch (\Throwable $e) {
        $attempts = (int)$job['attempts'] + 1;
        $error = mb_substr($e->getMessage(), 0, 2000);
        if ($attempts >= (int)$job['max_attempts']) {
            $db->prepare(
                "UPDATE jobs SET status = 'failed', attempts = ?, last_error = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            )->execute([$attempts, $error, $id]);
            echo '[' . date('Y-m-d H:i:s') . "] Job #{$id} ({$job['job']}) FAILED permanently: {$error}\n";
        } else {
            $db->prepare(
                "UPDATE jobs SET status = 'pending', attempts = ?, last_error = ?,
                    available_at = CURRENT_TIMESTAMP + interval '60 seconds',
                    reserved_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            )->execute([$attempts, $error, $id]);
            echo '[' . date('Y-m-d H:i:s') . "] Job #{$id} ({$job['job']}) retrying (attempt {$attempts}): {$error}\n";
        }
    }
}

function resolveJob(string $class): \App\Jobs\Job
{
    if (!class_exists($class) && str_starts_with($class, 'App\\')) {
        throw new \RuntimeException("Job handler not found: {$class}");
    }
    return new $class();
}
