<?php $title = 'Backup & Restore'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-database-gear me-2"></i>Backup & Restore</h4>
    <div>
        <form method="POST" action="/backups" class="d-inline" onsubmit="this.querySelector('button').disabled = true;">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm"><i class="bi bi-hdd-stack me-1"></i>Create Backup Now</button>
        </form>
    </div>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-files me-1"></i>Backups on Disk</h6></div>
            <div class="card-body text-center">
                <div class="display-4 fw-bold"><?= count($backups) ?></div>
                <div class="text-muted small">stored in <code class="text-danger"><?= e($backupDir) ?></code></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-sliders me-1"></i>Backup Settings</h6></div>
            <div class="card-body">
                <form method="POST" action="/backups/settings" class="row g-3 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Retention (keep last N)</label>
                        <input type="number" min="1" max="500" class="form-control form-control-sm" name="retention_count" value="<?= (int)($settings['retention_count'] ?? 10) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">pg_dump path (optional)</label>
                        <input type="text" class="form-control form-control-sm" name="pg_dump_path" value="<?= e($settings['pg_dump_path'] ?? '') ?>" placeholder="auto-detect">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">psql path (optional)</label>
                        <input type="text" class="form-control form-control-sm" name="psql_path" value="<?= e($settings['psql_path'] ?? '') ?>" placeholder="auto-detect">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-check"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-archive me-1"></i>Available Backups</h6></div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Type</th>
                    <th>Created</th>
                    <th class="text-end">Size</th>
                    <th>Checksum</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backups)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No backups yet. Click "Create Backup Now" to take your first backup.</td></tr>
                <?php endif; ?>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td>
                        <i class="bi bi-file-earmark-zip me-1 text-muted"></i><?= e($b['file']) ?>
                        <?php if (empty($b['valid'])): ?><span class="badge bg-danger">checksum mismatch</span><?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $b['type'] === 'manual' ? 'primary' : 'secondary' ?> bg-opacity-10 text-<?= $b['type'] === 'manual' ? 'primary' : 'secondary' ?>"><?= e($b['type']) ?></span></td>
                    <td><small class="text-muted"><?= date('d M Y H:i', strtotime($b['created_at'])) ?></small></td>
                    <td class="text-end"><small><?= round($b['size'] / 1024, 1) ?> KB</small></td>
                    <td><code class="small" title="<?= e($b['sha256'] ?? '') ?>"><?= $b['sha256'] ? mb_substr($b['sha256'], 0, 12) . '…' : '—' ?></code></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="/backups/download/<?= rawurlencode($b['file']) ?>" class="btn btn-sm btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>
                            <form method="POST" action="/backups/delete/<?= rawurlencode($b['file']) ?>" class="d-inline" onsubmit="return confirm('Delete this backup file?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                            <a href="/backups/restore/<?= rawurlencode($b['file']) ?>" class="btn btn-sm btn-outline-primary" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>Recent Backup Activity</h6></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>File</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>By</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($runs)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No activity recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($runs as $r): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></small></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($r['backup_type']) ?></span></td>
                    <td><small><?= e($r['file_name'] ?? '—') ?></small></td>
                    <td><span class="badge bg-<?= $r['status'] === 'success' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $r['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span></td>
                    <td><small class="text-muted"><?= $r['duration_ms'] ? round($r['duration_ms'] / 1000, 1) . 's' : '—' ?></small></td>
                    <td><small><?= e($r['user_name'] ?? 'CLI') ?></small></td>
                    <td><small class="text-muted text-truncate d-block" style="max-width:280px;" title="<?= e($r['message'] ?? '') ?>"><?= e($r['message'] ?? '') ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
