<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-shield-check me-2"></i>CAPA Records</h4>
        <span class="text-muted small"><?= count($records) ?> record(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin','Analyst'])): ?>
    <a href="/capa/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New CAPA</a>
    <?php endif; ?>
</div>

<?php if (empty($records)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-shield-check"></i>
        <h5>No CAPA Records</h5>
        <p class="text-muted">No corrective or preventive actions have been created yet.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>CAPA #</th>
                    <th>Title</th>
                    <th>Source</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Due Date</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/capa/<?= $r['id'] ?>" class="fw-medium text-decoration-none"><?= e($r['capa_number']) ?></a></td>
                    <td><?= e(substr($r['title'], 0, 40)) . (strlen($r['title']) > 40 ? '…' : '') ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($r['source_type'] ?: 'N/A') ?></span></td>
                    <td><?php $pbadge = match ($r['priority']) { 'Critical'=>'danger', 'High'=>'warning', 'Medium'=>'info', 'Low'=>'secondary', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $pbadge ?> bg-opacity-10 text-<?= $pbadge ?>"><?= e($r['priority']) ?></span></td>
                    <td><?php $sbadge = match ($r['status']) { 'Open'=>'danger', 'In Progress'=>'warning', 'Under Review'=>'info', 'Completed'=>'primary', 'Closed'=>'success', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $sbadge ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e($r['assigned_name'] ?: 'Unassigned') ?></td>
                    <td><small class="text-muted"><?= e($r['due_date'] ?? '-') ?></small></td>
                    <td class="text-end"><a href="/capa/<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
