<?php $title = 'Edit Batch: ' . e($batch['batch_number']); layout('app'); ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card shadow-sm">
<div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Batch</h5>
    <a href="/batches/<?= $batch['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
</div>
<div class="card-body">
<form method="POST" action="/batches/<?= $batch['id'] ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Batch Number <span class="text-danger">*</span></label>
            <input type="text" name="batch_number" class="form-control" required value="<?= e($batch['batch_number']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Batch Size</label>
            <input type="text" name="batch_size" class="form-control" value="<?= e($batch['batch_size'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select">
                <?php foreach ($products as $p): ?>
                <option value="<?= $p->id ?>" <?= ($p->id == $batch['product_id']) ? 'selected' : '' ?>><?= e($p->product_code) ?> — <?= e($p->product_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" value="<?= $batch['status'] ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Manufacture Date</label>
            <input type="date" name="manufacture_date" class="form-control" value="<?= $batch['manufacture_date'] ?? '' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-control" value="<?= $batch['expiry_date'] ?? '' ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e($batch['notes'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        <a href="/batches/<?= $batch['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>
