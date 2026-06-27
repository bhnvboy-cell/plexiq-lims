<?php $title = 'ELN Entries'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-journal-text me-2"></i>ELN Entries</h4>
    <div>
        <span class="badge bg-secondary me-2"><?= count($entries) ?> total</span>
        <a href="/notebooks/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Entry</a>
    </div>
</div>
<?php if (empty($entries)): ?>
<div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-text display-4 d-block mb-3"></i>No notebook entries found. <a href="/notebooks/create" class="alert-link">Create your first entry</a>.</div></div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Entry Code</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><code><?= e($e['entry_code'] ?? '—') ?></code></td>
                    <td class="fw-bold"><?= e($e['title']) ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($e['entry_type'] ?? 'General') ?></span></td>
                    <td><?= status_badge($e['status'] ?? 'Draft') ?></td>
                    <td><small><?= e($e['created_by_name'] ?? '—') ?></small></td>
                    <td><small class="text-muted"><?= date('d M Y', strtotime($e['created_at'])) ?></small></td>
                    <td><a href="/notebooks/<?= $e['notebook_id'] ?? '' ?>/entries/<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
