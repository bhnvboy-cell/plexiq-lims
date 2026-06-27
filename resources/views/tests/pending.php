<?php $title = 'Pending Tests'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-clipboard-data"></i> Pending Test Results</h2>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Sample</th><th>Customer</th><th>Product</th><th>Test</th><th>Specification</th><th>Assigned To</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><a href="/samples/<?= $t['sample_id'] ?>"><?= htmlspecialchars($t['sample_code']) ?></a></td>
                    <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($t['product_name'] ?? 'N/A') ?></td>
                    <td><strong><?= htmlspecialchars($t['test_code']) ?></strong><br><small><?= htmlspecialchars($t['test_name']) ?></small></td>
                    <td><small><?= htmlspecialchars($t['spec_limit_text'] ?? ($t['min_spec_limit'] . ' - ' . $t['max_spec_limit'])) ?></small></td>
                    <td><?= htmlspecialchars($t['assigned_to_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge bg-<?= $t['status'] === 'Pending' ? 'secondary' : 'info' ?>"><?= $t['status'] ?></span></td>
                    <td><a href="/tests/<?= $t['id'] ?>/result" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Enter Result</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tests)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No pending tests. All results have been entered.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
