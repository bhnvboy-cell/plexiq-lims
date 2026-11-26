<?php $title = 'Review by Exception'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-shield-check text-primary"></i> Quality Review by Exception</h2>
        <p class="text-muted mb-0 small">Review and approve analytical test results. Results outside specification limits or with OOT alerts are highlighted.</p>
    </div>
    <div class="btn-group">
        <a href="/results/review-by-exception" class="btn btn-sm <?= empty($exceptionsOnly) ? 'btn-primary' : 'btn-outline-primary' ?>">
            <i class="bi bi-list-check me-1"></i>All Results (<?= count($tests) ?>)
        </a>
        <a href="/results/review-by-exception?exceptions_only=1" class="btn btn-sm <?= !empty($exceptionsOnly) ? 'btn-warning' : 'btn-outline-warning' ?>">
            <i class="bi bi-exclamation-triangle me-1"></i>Exceptions Only
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Sample</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Test Parameter</th>
                    <th>Result Value</th>
                    <th>Specification</th>
                    <th class="text-center">Status</th>
                    <th>Entered By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $t): 
                    $val = is_numeric($t['result_value']) ? (float)$t['result_value'] : null;
                    $min = is_numeric($t['min_spec_limit']) ? (float)$t['min_spec_limit'] : null;
                    $max = is_numeric($t['max_spec_limit']) ? (float)$t['max_spec_limit'] : null;
                    
                    // Determine true Pass/Fail using both explicit flag and numeric bounds
                    $isFail = false;
                    $isPass = false;
                    if ($t['is_within_spec'] === false || $t['is_within_spec'] === 'f' || $t['is_within_spec'] === 0 || $t['is_within_spec'] === '0') {
                        $isFail = true;
                    } elseif ($val !== null && $min !== null && $max !== null && ($min > 0 || $max > 0)) {
                        if ($val < $min || $val > $max) {
                            $isFail = true;
                        } else {
                            $isPass = true;
                        }
                    } elseif ($t['is_within_spec'] === true || $t['is_within_spec'] === 't' || $t['is_within_spec'] === 1 || $t['is_within_spec'] === '1') {
                        $isPass = true;
                    }
                ?>
                <tr class="<?= $isFail ? 'table-danger' : '' ?>">
                    <td>
                        <a href="/samples/<?= $t['sample_id'] ?>" class="fw-bold text-decoration-none">
                            <?= htmlspecialchars($t['sample_code']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['product_name'] ?? 'N/A') ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($t['test_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($t['test_code']) ?></small>
                    </td>
                    <td>
                        <strong class="<?= $isFail ? 'text-danger' : 'text-success' ?>">
                            <?= htmlspecialchars((string)($t['result_value'] ?? $t['result_text'] ?? '-')) ?>
                        </strong>
                        <?php if (!empty($t['uncertainty'])): ?>
                        <small class="text-muted d-block" style="font-size:0.75rem;">± <?= htmlspecialchars((string)$t['uncertainty']) ?> (k=<?= htmlspecialchars((string)($t['k_factor'] ?? 2)) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary border">
                            <?= htmlspecialchars($t['spec_limit_text'] ?? ($t['min_spec_limit'] . ' - ' . $t['max_spec_limit'])) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($isFail): ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>OOS / Fail</span>
                        <?php elseif ($isPass): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Pass</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($t['entered_by_name'] ?? '-') ?></small></td>
                    <td class="text-end text-nowrap">
                        <?php if (!empty($t['result_id'])): ?>
                        <a href="/results/<?= $t['result_id'] ?>/lineage" class="btn btn-sm btn-outline-info me-1" title="View Full Forensic Lineage">
                            <i class="bi bi-diagram-3"></i>
                        </a>
                        <?php endif; ?>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/review" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this result?')">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                        </form>
                        <form method="POST" action="/tests/<?= $t['id'] ?>/review" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this result? Analyst must re-test.')">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
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
