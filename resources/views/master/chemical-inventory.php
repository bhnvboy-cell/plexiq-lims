<?php $title = 'Chemical Inventory'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-droplet me-2"></i>Chemical Inventory</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#chemModal"><i class="bi bi-plus"></i> Add Chemical</button>
</div>
<?php if ($stats['low_stock'] > 0): ?>
<div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= $stats['low_stock'] ?> chemical(s) are low stock or below minimum</div>
<?php endif; ?>
<?php if ($stats['expired'] > 0): ?>
<div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= $stats['expired'] ?> chemical(s) are expired</div>
<?php endif; ?>
<div class="row g-2 mb-3">
    <div class="col-auto"><a href="?status=In Stock" class="btn btn-sm btn-outline-success <?= ($_GET['status']??'')=='In Stock'?'active':'' ?>">In Stock</a></div>
    <div class="col-auto"><a href="?status=Low Stock" class="btn btn-sm btn-outline-warning <?= ($_GET['status']??'')=='Low Stock'?'active':'' ?>">Low Stock</a></div>
    <div class="col-auto"><a href="?status=Expired" class="btn btn-sm btn-outline-danger <?= ($_GET['status']??'')=='Expired'?'active':'' ?>">Expired</a></div>
    <div class="col-auto"><a href="/master/chemical-inventory" class="btn btn-sm btn-outline-secondary">All</a></div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Chemical Name</th><th>CAS #</th><th>Supplier</th><th>Quantity</th><th>Min Qty</th><th>Location</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($chemicals as $c): ?>
                <?php $isLow = $c['quantity'] <= $c['minimum_quantity'] && $c['quantity'] > 0; ?>
                <tr class="<?= $c['status']==='Expired'?'table-danger':($isLow?'table-warning':'') ?>">
                    <td><strong><?= e($c['chemical_name']) ?></strong></td>
                    <td><small class="text-muted"><?= e($c['cas_number'] ?? '-') ?></small></td>
                    <td><?= e($c['supplier'] ?? '-') ?></td>
                    <td><span class="fw-bold <?= $isLow?'text-danger':'' ?>"><?= e($c['quantity']) ?></span> <?= e($c['unit_type']) ?></td>
                    <td><?= e($c['minimum_quantity']) ?> <?= e($c['unit_type']) ?></td>
                    <td><?= e($c['storage_location'] ?? '-') ?></td>
                    <td><?= $c['expiry_date'] ? e($c['expiry_date']) : '-' ?></td>
                    <td>
                        <span class="badge bg-<?= $c['status']==='In Stock'?'success':($c['status']==='Low Stock'?'warning':($c['status']==='Expired'?'danger':'secondary')) ?>">
                            <?= e($c['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editChem(<?= $c['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-info" onclick="adjustChem(<?= $c['id'] ?>)"><i class="bi bi-plus-minus"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="chemModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST" action="/master/chemical-inventory">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Add Chemical</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row"><div class="col-md-8 mb-3"><label class="form-label">Chemical Name *</label><input type="text" name="chemical_name" class="form-control" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">CAS Number</label><input type="text" name="cas_number" class="form-control"></div></div>
                <div class="row"><div class="col-md-4 mb-3"><label class="form-label">Catalog #</label><input type="text" name="catalog_number" class="form-control"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Unit Type *</label><select name="unit_type" class="form-select" required><option value="L">Liter (L)</option><option value="mL">Milliliter (mL)</option><option value="g">Gram (g)</option><option value="mg">Milligram (mg)</option><option value="kg">Kilogram (kg)</option><option value="pcs">Pieces (pcs)</option><option value="box">Box</option></select></div></div>
                <div class="row"><div class="col-md-4 mb-3"><label class="form-label">Quantity</label><input type="number" step="0.0001" name="quantity" class="form-control" value="0"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Min Quantity</label><input type="number" step="0.0001" name="minimum_quantity" class="form-control" value="0"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Unit Price</label><input type="number" step="0.01" name="unit_price" class="form-control"></div></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Storage Location</label><input type="text" name="storage_location" class="form-control"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Received Date</label><input type="date" name="received_date" class="form-control"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div></div>
                <div class="mb-3"><label class="form-label">Hazard Symbols / Safety Data Sheet</label><textarea name="safety_data_sheet" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
<script>
function editChem(id) {
    fetch('/master/chemical-inventory/'+id+'/edit').then(r=>r.json()).then(d=>{
        const m=document.getElementById('chemModal');
        m.querySelector('.modal-title').textContent='Edit Chemical';
        const f=m.querySelector('form'); f.action='/master/chemical-inventory/'+id;
        ['chemical_name','cas_number','catalog_number','supplier','unit_type','quantity','minimum_quantity','unit_price','storage_location','received_date','expiry_date','safety_data_sheet'].forEach(k => {
            if(f.querySelector('[name='+k+']')) f.querySelector('[name='+k+']').value=d[k]||'';
        });
        new bootstrap.Modal(m).show();
    });
}
function adjustChem(id) {
    const qty = prompt('Enter quantity to add/subtract (use - for subtraction):');
    if (qty !== null) {
        const f=document.createElement('form'); f.method='POST'; f.action='/master/chemical-inventory/'+id+'/adjust';
        f.innerHTML='<?= csrf_field() ?><input name="adjustment" value="'+qty+'">';
        document.body.appendChild(f); f.submit();
    }
}
</script>
