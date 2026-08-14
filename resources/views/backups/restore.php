<?php $title = 'Restore Backup'; layout('app'); ?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger bg-opacity-10 text-danger">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Restore Backup</h6>
            </div>
            <div class="card-body">
                <?php $success = session_flash('success'); $error = session_flash('error'); ?>
                <?php if ($success): ?><div class="alert alert-success py-2"><?= e($success) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

                <div class="alert alert-warning">
                    <strong>This is destructive.</strong> Restoring will <u>replace all existing data</u> with the contents of this backup file. Current rows in affected tables are dropped and recreated from the backup.
                </div>

                <table class="table table-sm table-borderless mb-4">
                    <tr>
                        <td class="text-muted">File</td>
                        <td class="fw-bold"><?= e($fileName) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created</td>
                        <td><?= $backup ? date('d M Y H:i', strtotime($backup['created_at'])) : '—' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Size</td>
                        <td><?= $backup ? round($backup['size'] / 1024, 1) . ' KB' : '—' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Database</td>
                        <td><code><?= e($backup['database'] ?? 'unknown') ?></code></td>
                    </tr>
                    <?php if (!empty($backup['sha256'])): ?>
                    <tr>
                        <td class="text-muted">Checksum</td>
                        <td><code class="small"><?= e($backup['sha256']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <form method="POST" action="/backups/restore/<?= rawurlencode($fileName) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Type <strong>RESTORE</strong> to confirm:</label>
                        <input type="text" name="confirm" class="form-control" placeholder="RESTORE" autocomplete="off" required>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/backups" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Cancel</a>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
