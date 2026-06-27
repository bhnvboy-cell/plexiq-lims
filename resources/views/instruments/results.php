<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-arrow-down-circle me-2"></i>Instrument Results</h4>
        <span class="text-muted small"><?= count($results) ?> pending result(s) to match</span>
    </div>
    <?php if (!empty($results)): ?>
    <form method="POST" action="/instruments/results/match-all" class="d-inline">
        <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
        <button class="btn btn-success"><i class="bi bi-lightning-charge me-1"></i>Auto-Match All</button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($results)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-check-circle text-success"></i>
        <h5>All Results Matched</h5>
        <p class="text-muted">No pending instrument results. Import new data from an instrument to see results here.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Instrument</th>
                    <th>Sample Code</th>
                    <th>Test Code</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Imported</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><span class="fw-medium">#<?= $r['id'] ?></span></td>
                    <td><?= e($r['instrument_code']) ?></td>
                    <td><code><?= e($r['sample_code'] ?: '-') ?></code></td>
                    <td><code><?= e($r['test_code'] ?: '-') ?></code></td>
                    <td><span class="fw-medium"><?= e($r['result_value'] ?? $r['result_text'] ?? '-') ?></span></td>
                    <td><?= e($r['unit'] ?: '-') ?></td>
                    <td><span class="badge bg-warning bg-opacity-10 text-warning"><?= e($r['status']) ?></span></td>
                    <td><small class="text-muted"><?= e($r['imported_at']) ?></small></td>
                    <td class="text-end">
                        <form method="POST" action="/instruments/results/<?= $r['id'] ?>/match" class="d-inline">
                            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-link-45deg me-1"></i>Match</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
