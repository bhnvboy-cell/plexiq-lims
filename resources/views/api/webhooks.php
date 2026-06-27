<?php $title = 'Webhooks'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-webcam me-2"></i>Webhooks</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#webhookModal"><i class="bi bi-plus-lg"></i> Add Webhook</button>
</div>

<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0"><i class="bi bi-list me-1"></i>Webhook Endpoints</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>URL</th>
                    <th>Events</th>
                    <th>Status</th>
                    <th>Last Triggered</th>
                    <th>Last Response</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($webhooks)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No webhooks configured. <a href="#" data-bs-toggle="modal" data-bs-target="#webhookModal">Add one</a>.</td></tr>
                <?php else: foreach ($webhooks as $w): ?>
                <tr>
                    <td><code class="small"><?= e($w['url']) ?></code></td>
                    <td>
                        <?php $events = is_array($w['events']) ? $w['events'] : explode(',', $w['events'] ?? ''); ?>
                        <?php foreach (array_slice($events, 0, 3) as $ev): ?>
                        <span class="badge bg-info bg-opacity-10 text-info me-1"><?= e(trim($ev)) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($events) > 3): ?><span class="badge bg-secondary">+<?= count($events) - 3 ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($w['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= $w['last_triggered_at'] ? date('d M Y H:i', strtotime($w['last_triggered_at'])) : 'Never' ?></small></td>
                    <td>
                        <?php if ($w['last_response_code']): ?>
                        <span class="badge bg-<?= $w['last_response_code'] >= 200 && $w['last_response_code'] < 300 ? 'success' : 'danger' ?>"><?= $w['last_response_code'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($w['created_at'])) ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editWebhook(<?= $w['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="/api-management/webhooks/<?= $w['id'] ?>/toggle" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-<?= $w['is_active'] ? 'warning' : 'success' ?>">
                                <i class="bi bi-<?= $w['is_active'] ? 'pause' : 'play' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" action="/api-management/webhooks/<?= $w['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this webhook?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Webhook Modal -->
<div class="modal fade" id="webhookModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="/api-management/webhooks" id="webhookForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="webhookId" value="">
    <div class="modal-header"><h5 class="modal-title" id="webhookModalTitle"><i class="bi bi-webcam me-1"></i>Add Webhook</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Payload URL <span class="text-danger">*</span></label>
            <input type="url" name="url" id="webhookUrl" class="form-control" required placeholder="https://hooks.example.com/lims-events">
        </div>
        <div class="mb-3">
            <label class="form-label">Events <span class="text-danger">*</span></label>
            <div class="row g-2">
                <?php $allEvents = ['sample.created','sample.updated','batch.created','batch.completed','test.completed','test.approved','coa.released','oos.created','capa.created']; ?>
                <?php foreach ($allEvents as $ev): ?>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="events[]" value="<?= $ev ?>" id="ev_<?= str_replace('.', '_', $ev) ?>">
                        <label class="form-check-label" for="ev_<?= str_replace('.', '_', $ev) ?>"><?= e($ev) ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Secret (optional)</label>
            <input type="text" name="secret" id="webhookSecret" class="form-control" placeholder="HMAC secret for signature verification">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="webhookActive" value="1" checked>
            <label class="form-check-label" for="webhookActive">Active</label>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Save Webhook</button>
    </div>
</form>
</div></div></div>

<script>
function editWebhook(id) {
    fetch('/api-management/webhooks/' + id + '/edit')
        .then(r => r.json())
        .then(data => {
            document.getElementById('webhookId').value = data.id;
            document.getElementById('webhookUrl').value = data.url;
            document.getElementById('webhookSecret').value = data.secret || '';
            document.getElementById('webhookActive').checked = data.is_active == 1;
            document.getElementById('webhookModalTitle').innerHTML = '<i class="bi bi-pencil me-1"></i>Edit Webhook';
            document.getElementById('webhookForm').action = '/api-management/webhooks/' + id + '/update';
            const events = Array.isArray(data.events) ? data.events : (data.events ? data.events.split(',') : []);
            document.querySelectorAll('input[name="events[]"]').forEach(cb => { cb.checked = events.includes(cb.value); });
            new bootstrap.Modal(document.getElementById('webhookModal')).show();
        });
}
document.getElementById('webhookModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('webhookId').value = '';
    document.getElementById('webhookUrl').value = '';
    document.getElementById('webhookSecret').value = '';
    document.getElementById('webhookActive').checked = true;
    document.getElementById('webhookModalTitle').innerHTML = '<i class="bi bi-webcam me-1"></i>Add Webhook';
    document.getElementById('webhookForm').action = '/api-management/webhooks';
    document.querySelectorAll('input[name="events[]"]').forEach(cb => { cb.checked = false; });
});
</script>
