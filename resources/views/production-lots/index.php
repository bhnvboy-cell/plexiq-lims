<?php
layout('app');
$title = 'Production Lots & Material Release';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-boxes me-2"></i>Production Lots</h1>
            <p class="text-muted mb-0">Commercial Manufacturing Lots, GxP Quality Disposition, and ERP Synchronization</p>
        </div>
        <div>
            <a href="/production-lots/create" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Production Lot
            </a>
            <a href="/lab-batches" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-flask me-1"></i> Lab Analytical Batches
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="/production-lots" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search Lot / ERP Batch / Product</label>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Filter by Disposition</label>
                    <select name="disposition" class="form-select">
                        <option value="">All Dispositions</option>
                        <?php foreach ($dispositions as $d): ?>
                            <option value="<?= $d ?>" <?= ($filters['disposition'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Product</label>
                    <select name="product_id" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ((int)($filters['product_id'] ?? 0)) === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['product_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lots Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-columns me-2"></i>Production Lots Registry</span>
            <span class="badge bg-secondary"><?= count($lots) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Lot Number</th>
                            <th>Product</th>
                            <th>ERP Batch Ref</th>
                            <th>Mfg Date</th>
                            <th>Lot Size</th>
                            <th>QC Samples</th>
                            <th>Disposition</th>
                            <th>ERP Sync</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lots)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No production lots found. Click "New Production Lot" to record one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lots as $lot): ?>
                                <tr>
                                    <td>
                                        <a href="/production-lots/<?= $lot['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($lot['lot_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($lot['product_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($lot['product_code']) ?></small>
                                    </td>
                                    <td><code><?= htmlspecialchars($lot['erp_batch_number'] ?? '-') ?></code></td>
                                    <td><?= $lot['manufacturing_date'] ?? '-' ?></td>
                                    <td><?= $lot['lot_size'] !== null ? htmlspecialchars((string)$lot['lot_size']) . ' ' . htmlspecialchars($lot['lot_unit']) : '-' ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= $lot['approved_sample_count'] ?> / <?= $lot['sample_count'] ?> Approved
                                        </span>
                                        <?php if ((int)$lot['open_oos_count'] > 0): ?>
                                            <span class="badge bg-danger ms-1"><?= $lot['open_oos_count'] ?> OOS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($lot['disposition']) {
                                            'Released' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                            'On Hold' => 'bg-warning text-dark',
                                            'Conditionally Released' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $lot['disposition'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($lot['erp_sync_status'] === 'Synced'): ?>
                                            <span class="badge bg-success"><i class="bi bi-cloud-check me-1"></i>Synced</span>
                                        <?php elseif ($lot['erp_sync_status'] === 'Failed'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-cloud-slash me-1"></i>Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/production-lots/<?= $lot['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View & Release
                                        </a>
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
