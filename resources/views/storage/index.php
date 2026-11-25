<?php
$title = 'Cold-Chain & Storage Management';
require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Multi-Tier Storage Management</h1>
            <p class="text-muted mb-0">Cold-chain monitoring, freezers (-80°C, -20°C, 4°C), racks, and 2D grid boxes</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Storage Hierarchy</span>
            <span class="badge bg-secondary"><?= count($locations) ?> units</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Unit Name / Code</th>
                            <th>Type</th>
                            <th>Parent Location</th>
                            <th>Target Temp (°C)</th>
                            <th>Capacity / Grid</th>
                            <th>Occupancy</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($locations)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No storage locations configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($loc['name']) ?></strong><br>
                                        <code><?= htmlspecialchars($loc['code']) ?></code>
                                    </td>
                                    <td><span class="badge bg-info text-dark text-capitalize"><?= htmlspecialchars($loc['location_type']) ?></span></td>
                                    <td><?= htmlspecialchars($loc['parent_name'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($loc['temperature_nominal'] !== null): ?>
                                            <span class="badge <?= (float)$loc['temperature_nominal'] <= -20 ? 'bg-primary' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars($loc['temperature_nominal']) ?> °C
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Ambient</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $loc['capacity_rows'] ?> &times; <?= $loc['capacity_cols'] ?> (<?= (int)$loc['capacity_rows'] * (int)$loc['capacity_cols'] ?> slots)</td>
                                    <td>
                                        <span class="badge bg-success"><?= $loc['occupied_count'] ?> stored</span>
                                    </td>
                                    <td>
                                        <?php if (in_array($loc['location_type'], ['box', 'plate', 'rack', 'shelf'])): ?>
                                            <a href="/storage/box/<?= $loc['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-grid-3x3"></i> View Box Grid
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>
