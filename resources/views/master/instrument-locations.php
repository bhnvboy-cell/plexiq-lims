<?php $title = 'Instrument Locations'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-geo-alt me-2"></i>Instrument Locations</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#locModal"><i class="bi bi-plus"></i> Add Location</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Building</th><th>Floor</th><th>Room</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($locations as $l): ?>
                <tr>
                    <td><span class="badge bg-dark"><?= e($l['location_code']) ?></span></td>
                    <td><?= e($l['location_name']) ?></td>
                    <td><?= e($l['building'] ?? '-') ?></td>
                    <td><?= e($l['floor'] ?? '-') ?></td>
                    <td><?= e($l['room'] ?? '-') ?></td>
                    <td><?= $l['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editLoc(<?= $l['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="/master/instrument-locations/<?= $l['id'] ?>/toggle" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-<?= $l['is_active'] ? 'warning' : 'success' ?>"><?= $l['is_active'] ? '<i class="bi bi-x-circle"></i>' : '<i class="bi bi-check-circle"></i>' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="locModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/master/instrument-locations">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Add Location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Code</label><input type="text" name="location_code" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Name</label><input type="text" name="location_name" class="form-control" required></div></div>
                <div class="mb-3"><label class="form-label">Building</label><input type="text" name="building" class="form-control"></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Floor</label><input type="text" name="floor" class="form-control"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Room</label><input type="text" name="room" class="form-control"></div></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
<script>
function editLoc(id) {
    fetch('/master/instrument-locations/'+id+'/edit').then(r=>r.json()).then(d=>{
        const m=document.getElementById('locModal');
        m.querySelector('.modal-title').textContent='Edit Location';
        const f=m.querySelector('form'); f.action='/master/instrument-locations/'+id;
        f.querySelector('[name=location_code]').value=d.location_code;
        f.querySelector('[name=location_name]').value=d.location_name;
        f.querySelector('[name=building]').value=d.building||'';
        f.querySelector('[name=floor]').value=d.floor||'';
        f.querySelector('[name=room]').value=d.room||'';
        new bootstrap.Modal(m).show();
    });
}
</script>
