<?php
$title = 'Sample Genealogy & Lineage - ' . htmlspecialchars($sample['sample_code']);
require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Genealogy & Lineage: <?= htmlspecialchars($sample['sample_code']) ?></h1>
            <p class="text-muted mb-0">Sample hierarchy, parentage, aliquots, and composite composition</p>
        </div>
        <div>
            <a href="/samples/<?= $sample['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Sample</a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#aliquotModal">
                <i class="bi bi-diagram-3"></i> Create Aliquots
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sample Details Card -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">Sample Metadata</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Sample Code:</span>
                            <strong><?= htmlspecialchars($sample['sample_code']) ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Hierarchy Type:</span>
                            <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($sample['hierarchy_type'] ?? 'primary') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Status:</span>
                            <span class="badge bg-secondary"><?= htmlspecialchars($sample['status']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Batch:</span>
                            <span><?= htmlspecialchars($sample['batch_number'] ?? 'N/A') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Genealogy Path:</span>
                            <code><?= htmlspecialchars($sample['genealogy_path'] ?: (string)$sample['id']) ?></code>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tree Lineage Visualizer -->
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">Genealogy Tree</div>
                <div class="card-body">
                    <?php if ($tree['parent']): ?>
                        <div class="alert alert-secondary mb-3">
                            <strong><i class="bi bi-arrow-up-circle"></i> Parent Sample:</strong>
                            <a href="/samples/<?= $tree['parent']['id'] ?>" class="fw-bold ms-2"><?= htmlspecialchars($tree['parent']['sample_code']) ?></a>
                            <span class="badge bg-info text-dark ms-2"><?= htmlspecialchars($tree['parent']['hierarchy_type']) ?></span>
                            <a href="/samples/<?= $tree['parent']['id'] ?>/genealogy" class="btn btn-sm btn-link">View Tree</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($tree['composite_sources'])): ?>
                        <div class="alert alert-info mb-3">
                            <strong><i class="bi bi-intersect"></i> Composite Source Components:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($tree['composite_sources'] as $src): ?>
                                    <a href="/samples/<?= $src['id'] ?>" class="badge bg-light text-dark border text-decoration-none p-2">
                                        <?= htmlspecialchars($src['sample_code']) ?> (<?= htmlspecialchars($src['status']) ?>)
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="p-3 bg-light border rounded mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-box-seam fs-3 text-primary me-3"></i>
                            <div>
                                <h5 class="mb-0 text-primary"><?= htmlspecialchars($sample['sample_code']) ?> (Current Focus)</h5>
                                <small class="text-muted">Batch: <?= htmlspecialchars($sample['batch_number'] ?? 'N/A') ?> | Status: <?= htmlspecialchars($sample['status']) ?></small>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4"><i class="bi bi-diagram-2"></i> Child Aliquots & Sub-samples (<?= count($tree['children']) ?>)</h6>
                    <?php if (empty($tree['children'])): ?>
                        <p class="text-muted italic">No child aliquots or splits generated yet.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($tree['children'] as $child): ?>
                                <a href="/samples/<?= $child['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-arrow-return-right me-2 text-muted"></i>
                                        <strong><?= htmlspecialchars($child['sample_code']) ?></strong>
                                        <span class="badge bg-secondary ms-2">Aliquot #<?= $child['aliquot_index'] ?></span>
                                    </div>
                                    <div>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($child['status']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Aliquot Wizard -->
<div class="modal fade" id="aliquotModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/samples/<?= $sample['id'] ?>/aliquot" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Aliquots</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Create child aliquots replicating test specifications and maintaining full chain-of-custody genealogy.</p>
                <div class="mb-3">
                    <label class="form-label">Number of Aliquots (1-99)</label>
                    <input type="number" name="count" class="form-control" min="1" max="99" value="3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Volume / Qty per Aliquot (optional)</label>
                    <input type="text" name="volume" class="form-control" placeholder="e.g. 5 mL, 250 mg">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Aliquots</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
