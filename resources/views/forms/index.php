<?php
layout('app');
$title = 'Dynamic No-Code Forms & Environment Promotion';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dynamic Form Schemas & Promotion Engine</h1>
            <p class="text-muted mb-0">Drag-and-drop form schemas with GAMP 5 validation and signed promotion packages (DEV &rarr; UAT &rarr; PROD)</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSchemaModal">
                <i class="bi bi-plus-lg"></i> Create Form Schema
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Schemas List -->
        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Dynamic Schemas</span>
                    <span class="badge bg-secondary"><?= count($schemas) ?> schemas</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Schema Code</th>
                                    <th>Name</th>
                                    <th>Entity</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schemas)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No dynamic form schemas created yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($schemas as $s): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($s['schema_code']) ?></code></td>
                                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($s['entity_type']) ?></span></td>
                                            <td>v<?= (int)$s['version'] ?></td>
                                            <td><span class="badge <?= $s['status'] === 'published' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="promoteSchema(<?= $s['id'] ?>)">
                                                    <i class="bi bi-box-arrow-up-right"></i> Promote
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promotions Log -->
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Signed Environment Promotions</span>
                    <span class="badge bg-info text-dark">HMAC Signed</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Package</th>
                                    <th>Route</th>
                                    <th>Checksum</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($promotions)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">No promotion packages logged yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($promotions as $p): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($p['package_code']) ?></code></td>
                                            <td><?= htmlspecialchars($p['source_environment']) ?> &rarr; <?= htmlspecialchars($p['target_environment']) ?></td>
                                            <td title="<?= htmlspecialchars($p['sha256_checksum']) ?>"><code><?= substr($p['sha256_checksum'], 0, 8) ?>...</code></td>
                                            <td><span class="badge <?= $p['status'] === 'applied' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function promoteSchema(id) {
    if (!confirm('Promote this form schema to UAT environment with cryptographic signature?')) return;
    fetch('/forms/promote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `schema_id=${id}&source_env=DEV&target_env=UAT`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Package signed and ready: ' + d.package.package_code);
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    });
}
</script>
