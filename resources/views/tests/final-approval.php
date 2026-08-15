<?php $title = 'Final Approval'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-check-all"></i> Final Approval - Sign Off</h2>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Sample</th><th>Customer</th><th>Product</th><th>Test</th><th>Result</th><th>Spec</th><th>Reviewed By</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['sample_code']) ?></td>
                    <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($t['product_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($t['test_name']) ?></td>
                    <td><strong><?= htmlspecialchars((string)($t['result_value'] ?? $t['result_text'] ?? '-')) ?></strong>
                        <?php if (!empty($t['uncertainty'])): ?>
                        <small class="text-muted d-block">± <?= htmlspecialchars((string)$t['uncertainty']) ?> (k=<?= htmlspecialchars((string)($t['k_factor'] ?? 2)) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['spec_limit_text'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['reviewed_by_name'] ?? '-') ?></td>
                    <td>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/final-approve" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Digitally sign and approve this result?')">
                                <i class="bi bi-check-all"></i> Sign & Approve
                            </button>
                        </form>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/final-approve" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Send back for revision?')">
                                <i class="bi bi-arrow-return-left"></i> Revise
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tests)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No results pending final approval.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
