<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-cpu me-2"></i>Instruments</h4>
        <span class="text-muted small"><?= count($instruments) ?> instrument(s) registered</span>
    </div>
    <?php if ($auth['role'] === 'Admin'): ?>
    <div>
        <form method="POST" action="/instruments/scan" class="d-inline me-2">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary"><i class="bi bi-folder-symlink me-1"></i>Scan Watch Folders</button>
        </form>
        <a href="/instruments/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Instrument</a>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($instruments)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-cpu"></i>
        <h5>No Instruments Registered</h5>
        <p class="text-muted">Add your first instrument to start importing test results.</p>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/instruments/create" class="btn btn-primary mt-2"><i class="bi bi-plus-lg me-1"></i>Add Instrument</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Model</th>
                    <th>Interface</th>
                    <th>Auto</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instruments as $inst): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($inst['instrument_code']) ?></span></td>
                    <td><?= e($inst['instrument_name']) ?></td>
                    <td><?= e($inst['model'] ?: '-') ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($inst['interface_type']) ?></span></td>
                    <td><?= $inst['auto_import'] ? '<span class="badge bg-success bg-opacity-10 text-success">Yes</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">No</span>' ?></td>
                    <td><?= $inst['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end">
                        <a href="/instruments/<?= $inst['id'] ?>/import" class="btn btn-sm btn-outline-info me-1" title="Import"><i class="bi bi-upload"></i></a>
                        <a href="/instruments/<?= $inst['id'] ?>/mappings" class="btn btn-sm btn-outline-primary me-1" title="Column Mapping"><i class="bi bi-diagram-3"></i></a>
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <a href="/instruments/<?= $inst['id'] ?>/edit" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="/instruments/<?= $inst['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this instrument?')">
                            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
