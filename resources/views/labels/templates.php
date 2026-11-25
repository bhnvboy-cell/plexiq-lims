<?php
$title = 'Industrial Label Templates (ZPL / Brother / Barcode)';
require __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Industrial Label Templates</h1>
            <p class="text-muted mb-0">ZPL II, TSPL, and 2D Barcode / QR Code templates for sample vials and boxes</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Configured Label Formats</span>
            <span class="badge bg-secondary"><?= count($templates) ?> templates</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Template Name</th>
                            <th>Target Entity</th>
                            <th>Driver Format</th>
                            <th>Dimensions</th>
                            <th>Raw Template Body</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No label templates configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($templates as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                                    <td><span class="badge bg-info text-dark text-capitalize"><?= htmlspecialchars($t['label_type']) ?></span></td>
                                    <td><span class="badge bg-dark text-uppercase"><?= htmlspecialchars($t['output_format']) ?></span></td>
                                    <td><?= $t['width_mm'] ?> &times; <?= $t['height_mm'] ?> mm</td>
                                    <td>
                                        <pre class="mb-0 bg-light p-2 border rounded" style="max-height: 80px; max-width: 400px; font-size: 0.75rem;"><code><?= htmlspecialchars($t['template_body']) ?></code></pre>
                                    </td>
                                    <td>
                                        <?= !empty($t['is_default']) ? '<span class="badge bg-success">Default</span>' : '<span class="text-muted">No</span>' ?>
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
