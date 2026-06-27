<?php $title = 'BI Connections'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-database-gear me-2"></i>BI Connections</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConnectionModal"><i class="bi bi-plus-lg"></i> Add Connection</button>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Host</th>
                    <th>Database</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($connections)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No connections configured.</td></tr>
                <?php else: foreach ($connections as $c): ?>
                <tr>
                    <td class="fw-bold"><?= e($c['name']) ?></td>
                    <td><span class="badge bg-info"><?= e($c['type'] ?? 'PostgreSQL') ?></span></td>
                    <td><code><?= e($c['host'] ?? '-') ?></code></td>
                    <td><?= e($c['database'] ?? '-') ?></td>
                    <td>
                        <?php if ($c['is_active']): ?><span class="badge bg-success">Active</span>
                        <?php else: ?><span class="badge bg-secondary">Inactive</span><?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($c['created_at'] ?? 'now')) ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="testConnection(<?= $c['id'] ?>)"><i class="bi bi-plug"></i> Test</button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function testConnection(id) {
    fetch('/bi/connections/' + id + '/test').then(r => r.json()).then(d => alert(d.message || (d.success ? 'Connection OK' : 'Failed'))).catch(() => alert('Test failed.'));
}
</script>
