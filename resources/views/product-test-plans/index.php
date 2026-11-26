<?php
layout('app');
$title = 'Product Test Plans (Sampling Stage Routing)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Product Test Plans</h1>
            <p class="text-muted mb-0">Stage-Specific Testing Protocols: Route analyses automatically based on sample lifecycle stage</p>
        </div>
        <div>
            <a href="/specifications" class="btn btn-outline-primary">
                <i class="bi bi-journal-text me-1"></i> Specifications
            </a>
        </div>
    </div>

    <!-- Product Selector Bar -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body">
            <form action="/product-test-plans" method="GET" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <label class="form-label fw-bold mb-1">Select Product Protocol:</label>
                    <select name="product_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($selectedProduct && $selectedProduct['id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedProduct): ?>
                    <div class="col-md-7 d-flex align-items-end justify-content-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                            <i class="bi bi-plus-lg me-1"></i> Assign Analysis to Stage
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($selectedProduct): ?>
        <!-- Test Plans Grouped by Stage -->
        <div class="row g-4">
            <?php foreach ($stages as $stage): ?>
                <?php
                $stagePlans = array_filter($testPlans, fn($tp) => $tp['stage'] === $stage);
                $badgeColor = match ($stage) {
                    'Finished Good' => 'bg-success',
                    'Raw Material' => 'bg-primary',
                    'In-Process' => 'bg-warning text-dark',
                    'Stability' => 'bg-info text-dark',
                    'Release' => 'bg-dark',
                    default => 'bg-secondary'
                };
                ?>
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                            <div>
                                <span class="badge <?= $badgeColor ?> fs-6 me-2"><?= $stage ?></span>
                                <span class="text-muted small">(<?= count($stagePlans) ?> analyses mapped)</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openAssignModalForStage('<?= $stage ?>')">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($stagePlans)): ?>
                                <div class="text-center py-4 text-muted small">
                                    <em>No analyses mapped for <?= $stage ?> stage.</em>
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($stagePlans as $plan): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($plan['analysis_name']) ?></div>
                                                <div class="small text-muted">
                                                    <code><?= htmlspecialchars($plan['analysis_code']) ?></code>
                                                    <?php if (!empty($plan['sop_reference'])): ?>
                                                        &bull; SOP: <?= htmlspecialchars($plan['sop_reference']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($plan['is_mandatory']): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger small">Mandatory</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border small">Optional</span>
                                                <?php endif; ?>
                                                <form action="/product-test-plans/remove" method="POST" onsubmit="return confirm('Remove this analysis from the plan?');">
                                                    <input type="hidden" name="product_id" value="<?= $selectedProduct['id'] ?>">
                                                    <input type="hidden" name="stage" value="<?= $stage ?>">
                                                    <input type="hidden" name="analysis_id" value="<?= $plan['analysis_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Assign Analysis -->
<?php if ($selectedProduct): ?>
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/product-test-plans/assign" method="POST" class="modal-content">
            <input type="hidden" name="product_id" value="<?= $selectedProduct['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Assign Analysis to Stage Protocol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Testing Stage</label>
                    <select name="stage" id="modalStageSelect" class="form-select" required>
                        <?php foreach ($stages as $stg): ?>
                            <option value="<?= $stg ?>"><?= $stg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Analysis (Method / Test)</label>
                    <select name="analysis_id" class="form-select" required>
                        <option value="">-- Select Analysis --</option>
                        <?php foreach ($analyses as $ana): ?>
                            <option value="<?= $ana['id'] ?>">
                                <?= htmlspecialchars($ana['analysis_name']) ?> (<?= htmlspecialchars($ana['analysis_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" id="mandatoryCheck" checked>
                    <label class="form-check-label" for="mandatoryCheck">
                        Mandatory test (Sample cannot be approved without this result)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Analysis</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignModalForStage(stage) {
    document.getElementById('modalStageSelect').value = stage;
    const modal = new bootstrap.Modal(document.getElementById('assignModal'));
    modal.show();
}
</script>
<?php endif; ?>
