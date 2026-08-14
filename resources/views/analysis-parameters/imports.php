<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-file-earmark-arrow-down me-2"></i>Instrument Imports</h4>
    <a href="/instruments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Instruments</a>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>Imported rows land in <strong>sample_analysis_parameters</strong> via column mapping and run through spec/OOS checks. Run the queue worker (<code>php bin/worker.php --queue=imports</code>) to process queued files.
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Created</th><th>Instrument</th><th>Source File</th><th>Sample</th>
                    <th>Column / Test</th><th>Result</th><th>Status</th><th>Resolved Parameter</th><th>Result Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><small><?= e(date('Y-m-d H:i', strtotime($r['created_at']))) ?></small></td>
                    <td><small><?= e($r['instrument_name']) ?></small></td>
                    <td><small class="text-muted"><?= e($r['source_file'] ?? '-') ?></small></td>
                    <td><span class="fw-medium"><?= e($r['resolved_sample'] ?? $r['sample_code']) ?></span></td>
                    <td><small><?= e($r['test_code'] ?? '-') ?></small></td>
                    <td><strong><?= e($r['result_value'] ?? $r['result_text'] ?? '-') ?></strong> <small><?= e($r['unit'] ?? '') ?></small></td>
                    <td><span class="badge bg-<?= $r['status'] === 'Imported' ? 'success' : ($r['status'] === 'Failed' ? 'danger' : 'secondary') ?>"><?= e($r['status']) ?></span></td>
                    <td><small><?= e($r['parameter_name'] ?? '-') ?></small></td>
                    <td><small><?= e($r['sap_status'] ?? '-') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($results)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No imports yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $paginator = $pagination; include __DIR__ . '/../partials/pagination.php'; ?>
