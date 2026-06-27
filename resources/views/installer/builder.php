<?php $title = 'Installer Builder'; layout('app'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-box-seam me-2"></i>Installer Builder</h4>
    <div>
        <span class="badge bg-<?= $iscc_available ? 'success' : 'danger' ?> me-2" id="isccBadge">
            ISCC: <?= $iscc_available ? 'Available' : 'Not Found' ?>
        </span>
        <a href="/installer/history" class="btn btn-sm btn-outline-secondary" onclick="showHistory(event)"><i class="bi bi-clock-history"></i> Build History</a>
    </div>
</div>

<?php if (!$iscc_available): ?>
<div class="alert alert-warning">
    <strong><i class="bi bi-exclamation-triangle me-1"></i>Inno Setup Not Found</strong>
    <p class="mb-0 mt-1">ISCC.exe is required to compile the installer. Install <a href="https://jrsoftware.org/isdl.php" target="_blank">Inno Setup 6</a> on this server, or use the <code>server-installer/build.bat</code> script directly.</p>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-gear me-1"></i>Installer Configuration</h6></div>
            <div class="card-body">
                <form method="POST" action="/installer/build" id="buildForm">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Application Name</label>
                            <input type="text" name="app_name" class="form-control" value="<?= e($settings['app_name']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Version</label>
                            <input type="text" name="app_version" class="form-control" value="<?= e($settings['app_version']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Publisher</label>
                            <input type="text" name="app_publisher" class="form-control" value="<?= e($settings['app_publisher']) ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6><i class="bi bi-server me-1"></i>Server Configuration</h6>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label class="form-label">Port</label>
                            <input type="number" name="server_port" class="form-control" value="<?= e($settings['server_port']) ?>" min="1024" max="65535">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Output Filename</label>
                            <input type="text" name="output_filename" class="form-control" value="<?= e($settings['output_filename']) ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6><i class="bi bi-database me-1"></i>Database Configuration</h6>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label class="form-label">Host</label>
                            <input type="text" name="db_host" class="form-control" value="<?= e($settings['db_host']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Port</label>
                            <input type="number" name="db_port" class="form-control" value="<?= e($settings['db_port']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control" value="<?= e($settings['db_name']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <input type="text" name="db_user" class="form-control" value="<?= e($settings['db_user']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Password</label>
                            <input type="password" name="db_pass" class="form-control" value="<?= e($settings['db_pass']) ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        The installer will bundle the current application code with these settings.
                        The resulting .exe will set up the database, configure the server, and create shortcuts.
                        Build time: ~30-60 seconds.
                    </p>

                    <button type="submit" class="btn btn-primary btn-lg mt-3 w-100" id="buildBtn" <?= !$iscc_available ? 'disabled' : '' ?>>
                        <i class="bi bi-hammer me-1"></i> <span id="buildBtnText">Build Installer</span>
                        <div class="spinner-border spinner-border-sm d-none ms-2" id="buildSpinner" role="status"></div>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-terminal me-1"></i>Build Log</h6></div>
            <div class="card-body p-0">
                <pre id="buildLog" class="m-0 p-3" style="font-size:11px;line-height:1.4;max-height:400px;overflow:auto;background:#1a1a2e;color:#00ff41;font-family:'Consolas','Courier New',monospace;border-radius:0;"><?php
                    $lastLog = $_SESSION['_installer_log'] ?? null;
                    echo $lastLog ? e($lastLog) : 'Ready to build. Configure settings and click "Build Installer".';
                ?></pre>
            </div>
            <div class="card-footer text-muted small" id="buildStatus">
                <?php if (!empty($_SESSION['_installer_build_id'])): ?>
                Build ID: <?= e($_SESSION['_installer_build_id']) ?>
                <?php else: ?>
                No builds yet
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($_SESSION['_installer_download'])): $exe = $_SESSION['_installer_download']; ?>
        <div class="card shadow-sm mt-3 border-success">
            <div class="card-body text-center py-3">
                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                <h5 class="mt-2">Installer Ready</h5>
                <p class="text-muted small mb-2"><?= e(basename($exe)) ?> (<?= round(filesize($exe) / 1024) ?> KB)</p>
                <a href="/installer/download" class="btn btn-success"><i class="bi bi-download me-1"></i> Download EXE</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Build History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-1"></i>Build History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="historyTable">
                <thead class="table-light"><tr><th>Date</th><th>Version</th><th>Port</th><th>Database</th><th>Status</th><th>Size</th><th>Time</th><th>Built By</th></tr></thead>
                <tbody id="historyBody"><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
    </div>
</div></div></div>

<script>
document.getElementById('buildForm').addEventListener('submit', function() {
    document.getElementById('buildBtn').disabled = true;
    document.getElementById('buildBtnText').textContent = 'Building...';
    document.getElementById('buildSpinner').classList.remove('d-none');
    document.getElementById('buildLog').textContent = '[' + new Date().toISOString() + '] Starting build...\n';
});

function showHistory(e) {
    e.preventDefault();
    var modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();

    fetch('/installer/history')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('historyBody');
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No builds yet</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function(b) {
                var status = b.exit_code === 0
                    ? '<span class="badge bg-success">Success</span>'
                    : '<span class="badge bg-danger">Failed (' + b.exit_code + ')</span>';
                var size = b.exe_size > 0 ? (b.exe_size / 1024).toFixed(0) + ' KB' : '—';
                var time = b.build_time ? b.build_time + 's' : '—';
                return '<tr><td>' + b.created_at + '</td><td>' + b.app_version + '</td><td>' + b.server_port + '</td><td>' + b.db_name + '</td><td>' + status + '</td><td>' + size + '</td><td>' + time + '</td><td>' + (b.built_by_name || '—') + '</td></tr>';
            }).join('');
        })
        .catch(function() {
            document.getElementById('historyBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load history</td></tr>';
        });
}

// Auto-refresh log if there's a build in progress
<?php if (!empty($_SESSION['_installer_build_id'])): ?>
setInterval(function() {
    fetch('/installer/log/<?= $_SESSION['_installer_build_id'] ?>')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.log) {
                document.getElementById('buildLog').textContent = data.log;
                document.getElementById('buildLog').scrollTop = document.getElementById('buildLog').scrollHeight;
            }
        });
}, 2000);
<?php endif; ?>
</script>
