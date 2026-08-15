<?php $title = 'Review Results'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-check-circle"></i> Review Test Results</h2>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Sample</th><th>Customer</th><th>Product</th><th>Test</th><th>Result</th><th>Specification</th><th>Status</th><th>Entered By</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr class="<?= $t['is_within_spec'] === false ? 'table-danger' : ($t['is_within_spec'] === true ? '' : '') ?>">
                    <td><?= htmlspecialchars($t['sample_code']) ?></td>
                    <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($t['product_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($t['test_name']) ?></td>
                    <td><strong><?= htmlspecialchars((string)($t['result_value'] ?? $t['result_text'] ?? '-')) ?></strong>
                        <?php if (!empty($t['uncertainty'])): ?>
                        <small class="text-muted d-block">± <?= htmlspecialchars((string)$t['uncertainty']) ?> (k=<?= htmlspecialchars((string)($t['k_factor'] ?? 2)) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['spec_limit_text'] ?? ($t['min_spec_limit'] . ' - ' . $t['max_spec_limit'])) ?></td>
                    <td>
                        <?php if ($t['is_within_spec'] === true): ?><span class="badge bg-success">Pass</span>
                        <?php elseif ($t['is_within_spec'] === false): ?><span class="badge bg-danger">Fail</span>
                        <?php else: ?><span class="badge bg-secondary">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['entered_by_name'] ?? '-') ?></td>
                    <td>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/review" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this result?')"><i class="bi bi-check-lg"></i> Approve</button>
                        </form>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/review" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this result? Analyst must re-enter.')"><i class="bi bi-x-lg"></i> Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tests)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No results pending review.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
