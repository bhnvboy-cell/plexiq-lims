<?php $title = 'Sample Types'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-tag me-2"></i>Sample Types</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sampleTypeModal"><i class="bi bi-plus"></i> Add Sample Type</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($sampleTypes as $st): ?>
                <tr>
                    <td><span class="badge bg-dark"><?= e($st['type_code']) ?></span></td>
                    <td><?= e($st['type_name']) ?></td>
                    <td><?= e($st['description'] ?? '-') ?></td>
                    <td><?= $st['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editSampleType(<?= $st['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="/master/sample-types/<?= $st['id'] ?>/toggle" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-<?= $st['is_active'] ? 'warning' : 'success' ?>"><?= $st['is_active'] ? '<i class="bi bi-x-circle"></i>' : '<i class="bi bi-check-circle"></i>' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="sampleTypeModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/master/sample-types">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Add Sample Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Code</label><input type="text" name="type_code" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="type_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
<script>
function editSampleType(id) {
    fetch('/master/sample-types/' + id + '/edit').then(r=>r.json()).then(d=>{
        document.getElementById('sampleTypeModal').querySelector('.modal-title').textContent='Edit Sample Type';
        const f=document.querySelector('#sampleTypeModal form');
        f.action='/master/sample-types/'+id;
        f.querySelector('[name=type_code]').value=d.type_code;
        f.querySelector('[name=type_name]').value=d.type_name;
        f.querySelector('[name=description]').value=d.description||'';
        new bootstrap.Modal(document.getElementById('sampleTypeModal')).show();
    });
}
</script>
