<?php $title = 'Analytical Methods'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-flask me-2"></i>Analytical Methods</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#methodModal"><i class="bi bi-plus"></i> Add Method</button>
</div>
<div class="row g-3">
    <?php foreach ($items as $m): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100 method-tile">
            <div class="card-body text-center">
                <div class="method-icon mb-2">
                    <i class="bi bi-flask fs-1" style="color: <?= ['#2b7be4','#11998e','#764ba2','#f5a623','#e74c3c','#3498db','#1abc9c','#9b59b6'][$m['id'] % 8] ?>;"></i>
                </div>
                <h6 class="fw-bold mb-1"><?= e($m['method_code']) ?></h6>
                <p class="small text-muted mb-2"><?= e($m['method_name']) ?></p>
                <?php if ($m['description']): ?>
                <small class="text-muted d-block mb-2"><?= e(substr($m['description'], 0, 60)) ?><?= strlen($m['description']) > 60 ? '...' : '' ?></small>
                <?php endif; ?>
                <div class="d-flex justify-content-center gap-1 mt-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="editMethod(<?= $m['id'] ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="/master/methods/<?= $m['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this method?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="methodModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/master/methods">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Add Method</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Method Code *</label><input type="text" name="method_code" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Method Name *</label><input type="text" name="method_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
<script>
function editMethod(id) {
    fetch('/master/methods/'+id+'/edit').then(r=>r.json()).then(d=>{
        const m=document.getElementById('methodModal');
        m.querySelector('.modal-title').textContent='Edit Method';
        const f=m.querySelector('form'); f.action='/master/methods/'+id;
        f.querySelector('[name=method_code]').value=d.method_code;
        f.querySelector('[name=method_name]').value=d.method_name;
        f.querySelector('[name=description]').value=d.description||'';
        new bootstrap.Modal(m).show();
    });
}
</script>
<style>
.method-tile { transition: all .2s ease; border: 2px solid transparent; }
.method-tile:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--primary); }
.method-icon { line-height: 1; }
</style>
