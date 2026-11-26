<?php
$title = 'Storage Box Grid - ' . htmlspecialchars($location['name']);
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($location['name']) ?> (<?= htmlspecialchars($location['code']) ?>)</h1>
            <p class="text-muted mb-0">2D Coordinate Matrix [Rows A-<?= chr(64 + count($matrix)) ?>, Cols 1-<?= count(reset($matrix) ?: []) ?>]</p>
        </div>
        <div>
            <a href="/storage" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Storage</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkInModal"><i class="bi bi-box-arrow-in-down"></i> Check-In Sample</button>
        </div>
    </div>

    <!-- Occupancy Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-light border shadow-sm p-3">
                <small class="text-muted">Total Slots</small>
                <div class="fs-4 fw-bold"><?= $stats['total_slots'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border shadow-sm p-3">
                <small class="text-muted">Occupied Slots</small>
                <div class="fs-4 fw-bold text-primary"><?= $stats['occupied_slots'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border shadow-sm p-3">
                <small class="text-muted">Free Slots</small>
                <div class="fs-4 fw-bold text-success"><?= $stats['free_slots'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border shadow-sm p-3">
                <small class="text-muted">Occupancy Rate</small>
                <div class="fs-4 fw-bold"><?= $stats['occupancy_pct'] ?>%</div>
            </div>
        </div>
    </div>

    <!-- Graphical 2D Grid Visualizer -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Grid Layout (2D Coordinate Well Map)</div>
        <div class="card-body overflow-auto">
            <table class="table table-bordered text-center align-middle" style="min-width: 650px;">
                <thead>
                    <tr class="table-light">
                        <th style="width: 50px;">#</th>
                        <?php foreach (array_keys(reset($matrix) ?: []) as $colNum): ?>
                            <th><?= $colNum ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matrix as $rNum => $cols): ?>
                        <tr>
                            <td class="table-light fw-bold"><?= chr(64 + $rNum) ?></td>
                            <?php foreach ($cols as $cNum => $cell): ?>
                                <?php if (!empty($cell['empty'])): ?>
                                    <td class="bg-light p-2" style="height: 60px;">
                                        <small class="text-muted d-block"><?= $cell['position_label'] ?></small>
                                        <span class="badge bg-secondary opacity-50">Empty</span>
                                    </td>
                                <?php else: ?>
                                    <td class="table-primary p-2" style="height: 60px;">
                                        <small class="fw-bold d-block text-truncate" title="<?= htmlspecialchars($cell['sample_code']) ?>">
                                            <a href="/samples/<?= $cell['sample_id'] ?>" class="text-decoration-none text-dark">
                                                <?= htmlspecialchars($cell['sample_code']) ?>
                                            </a>
                                        </small>
                                        <span class="badge bg-primary"><?= $cell['position_label'] ?></span>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Check-In Sample -->
<div class="modal fade" id="checkInModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/storage/check-in" method="POST" class="modal-content">
            <input type="hidden" name="storage_location_id" value="<?= $location['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Check-In Sample to Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Sample ID / Numeric ID</label>
                    <input type="number" name="sample_id" class="form-control" required placeholder="Enter Sample ID">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Row (1 to <?= count($matrix) ?>)</label>
                        <input type="number" name="row" class="form-control" min="1" max="<?= count($matrix) ?>" value="1" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Col (1 to <?= count(reset($matrix) ?: []) ?>)</label>
                        <input type="number" name="col" class="form-control" min="1" max="<?= count(reset($matrix) ?: []) ?>" value="1" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Coordinate</button>
            </div>
        </form>
    </div>
</div>
