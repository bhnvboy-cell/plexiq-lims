<?php
$title = 'Document Control & SOPs (ISO 17025 / GxP)';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Document Control & Controlled Copies</h1>
            <p class="text-muted mb-0">Standard Operating Procedures (SOPs), Versioning, Effective Dates & Issuance</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSopModal">
                <i class="bi bi-file-earmark-plus"></i> New SOP
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Controlled Procedures Master List</span>
            <span class="badge bg-secondary"><?= count($sops) ?> documents</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>SOP Number</th>
                            <th>Title</th>
                            <th>Version</th>
                            <th>Effective Date</th>
                            <th>Review Due</th>
                            <th>Controlled Copies</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sops)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No SOP records registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sops as $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($s['sop_number']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                                    <td><span class="badge bg-info text-dark">v<?= htmlspecialchars($s['version']) ?></span></td>
                                    <td><?= htmlspecialchars($s['effective_date'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($s['review_due_date'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= (int)$s['active_copies'] ?> active copies</span>
                                    </td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($s['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New SOP -->
<div class="modal fade" id="newSopModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/sops" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register Standard Operating Procedure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">SOP Number / Identifier</label>
                    <input type="text" name="sop_number" class="form-control font-monospace" required placeholder="e.g. SOP-QC-042">
                </div>
                <div class="mb-3">
                    <label class="form-label">SOP Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Standard Operating Procedure for HPLC Dissolution Testing">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Version</label>
                        <input type="text" name="version" class="form-control" value="1.0" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Register SOP</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
