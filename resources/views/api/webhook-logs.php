<?php $title = 'Webhook Delivery Logs'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-broadcast me-2"></i>Webhook Delivery Logs</h4>
    <a href="/api-management/webhooks" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Webhooks</a>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Deliveries</h6>
        <span class="badge bg-secondary"><?= count($logs ?? []) ?> events</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>Event</th>
                    <th>Response</th>
                    <th>Payload</th>
                    <th>Response Body</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No webhook deliveries recorded for this webhook.</td></tr>
                <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><small class="text-muted"><?= date('d M Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($l['event'] ?? '—') ?></span></td>
                    <td>
                        <?php
                        $code = (int)($l['response_code'] ?? 0);
                        $cls = $code >= 200 && $code < 300 ? 'success' : ($code === 0 ? 'secondary' : 'danger');
                        ?>
                        <span class="badge bg-<?= $cls ?>"><?= $code ?: '—' ?></span>
                    </td>
                    <td><code class="small"><?= e(mb_strimwidth($l['payload'] ?? '', 0, 60, '…')) ?></code></td>
                    <td><code class="small"><?= e(mb_strimwidth($l['response_body'] ?? '', 0, 60, '…')) ?></code></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
