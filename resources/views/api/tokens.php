<?php $title = 'API Tokens'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-key me-2"></i>API Tokens</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTokenModal"><i class="bi bi-plus-lg"></i> Create Token</button>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-list me-1"></i>Active Tokens</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Token Hash</th>
                    <th>Permissions</th>
                    <th>Last Used</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tokens)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No API tokens configured. <a href="#" data-bs-toggle="modal" data-bs-target="#createTokenModal">Create one</a>.</td></tr>
                <?php else: foreach ($tokens as $t): ?>
                <tr>
                    <td class="fw-bold"><?= e($t['name']) ?></td>
                    <td><code><?= e($t['token_hash'] ?? substr($t['token'], 0, 20) . '...') ?></code></td>
                    <td><span class="badge bg-<?= $t['scope'] === 'full' ? 'danger' : 'info' ?> bg-opacity-10 text-dark"><?= e($t['scope'] ?? 'read') ?></span></td>
                    <td><small class="text-muted"><?= $t['last_used_at'] ? date('d M Y H:i', strtotime($t['last_used_at'])) : 'Never' ?></small></td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($t['created_at'])) ?></small></td>
                    <td><small class="text-muted"><?= $t['expires_at'] ? date('d M Y', strtotime($t['expires_at'])) : 'Never' ?></small></td>
                    <td><?php if ($t['revoked_at']): ?><span class="badge bg-danger">Revoked</span><?php else: ?><span class="badge bg-success">Active</span><?php endif; ?></td>
                    <td>
                        <?php if (empty($t['revoked_at'])): ?>
                        <form method="POST" action="/api-management/tokens/<?= $t['id'] ?>/revoke" class="d-inline" onsubmit="return confirm('Revoke this token? Any integrations using it will stop working.')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Token Modal -->
<div class="modal fade" id="createTokenModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/api-management/tokens">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-key me-1"></i>Create API Token</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Token Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. CI/CD Integration">
        </div>
        <div class="mb-3">
            <label class="form-label">Scope</label>
            <select name="scope" class="form-select">
                <option value="read">Read Only</option>
                <option value="write">Read & Write</option>
                <option value="full">Full Access</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Expires In</label>
            <select name="expires_in" class="form-select">
                <option value="30">30 days</option>
                <option value="90" selected>90 days</option>
                <option value="180">180 days</option>
                <option value="365">1 year</option>
                <option value="">Never</option>
            </select>
        </div>
        <div class="alert alert-info py-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>The token will be shown only once after creation. Copy it immediately.
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-key"></i> Generate Token</button>
    </div>
</form>
</div></div></div>

<?php if (!empty($newToken)): ?>
<div class="modal fade show" id="newTokenModal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
<div class="modal-dialog"><div class="modal-content border-success">
<div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-check-circle me-1"></i>Token Created</h5></div>
<div class="modal-body">
    <p class="text-muted">Copy this token now. For security reasons, it will not be shown again.</p>
    <div class="input-group mb-3">
        <input type="text" class="form-control" id="newTokenValue" value="<?= e($newToken) ?>" readonly>
        <button class="btn btn-outline-primary" onclick="copyToken()"><i class="bi bi-clipboard"></i></button>
    </div>
    <div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Store this token securely. If lost, you must create a new one.</div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-success" onclick="document.getElementById('newTokenModal').style.display='none'">I've Copied It</button>
</div>
</div></div></div>
<script>
function copyToken() {
    const inp = document.getElementById('newTokenValue');
    inp.select(); inp.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(inp.value).then(() => alert('Token copied to clipboard!'));
}
</script>
<?php endif; ?>
