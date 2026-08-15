<?php $title = 'Chain of Custody'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0">
        <i class="bi bi-signpost-2 me-2"></i>Chain of Custody
        <small class="text-muted fs-6 ms-2">Sample custody transfer log</small>
    </h4>
    <a href="/samples" class="btn btn-outline-primary btn-sm"><i class="bi bi-collection"></i> Browse Samples</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-arrow-left-right stat-icon"></i>
            <div class="stat-value"><?= $stats['total_transfers'] ?? 0 ?></div>
            <div class="stat-label">Total Transfers</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-value"><?= $stats['pending_receipt'] ?? 0 ?></div>
            <div class="stat-label">Awaiting Receipt</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stats-card stats-card-green">
            <i class="bi bi-shield-lock stat-icon"></i>
            <div class="stat-value"><?= $stats['sealed'] ?? 0 ?></div>
            <div class="stat-label">Sealed Transfers</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-1"></i>Custody Transfers</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Sample</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Transferred At</th>
                    <th>Transferred By</th>
                    <th>Received</th>
                    <th>Location</th>
                    <th>Sealed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfers as $t): ?>
                <tr>
                    <td>
                        <a href="/samples/<?= $t['sample_id'] ?>" class="fw-bold text-decoration-none"><?= e($t['sample_code']) ?></a>
                    </td>
                    <td><?= e($t['transfer_from'] ?? '-') ?></td>
                    <td><?= e($t['transfer_to'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= date('Y-m-d H:i', strtotime($t['transferred_at'])) ?></small></td>
                    <td><?= e($t['transferred_by_name'] ?? '-') ?></td>
                    <td>
                        <?php if ($t['received_at']): ?>
                            <span class="badge bg-success"><?= e($t['received_by_name'] ?? 'Received') ?></span>
                            <small class="text-muted d-block"><?= date('Y-m-d H:i', strtotime($t['received_at'])) ?></small>
                        <?php else: ?>
                            <form method="POST" action="/coc/<?= $t['id'] ?>/receive" class="d-inline">
                                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i> Mark Received</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['location'] ?? '-') ?></td>
                    <td>
                        <?php if ($t['sealed']): ?>
                            <span class="badge bg-info"><?= e($t['seal_number'] ?? 'Sealed') ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/samples/<?= $t['sample_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <?php if (in_array($auth['role'], ['Admin'])): ?>
                        <form method="POST" action="/coc/<?= $t['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this custody entry?');">
                            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transfers)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No custody transfers recorded yet. Record a transfer from any sample detail page.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
