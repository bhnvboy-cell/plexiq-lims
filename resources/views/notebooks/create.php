<?php $title = 'Create Notebook'; layout('app'); ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card shadow-sm">
<div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Notebook</h5></div>
<div class="card-body">
<form method="POST" action="/notebooks">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Notebook Name <span class="text-danger">*</span></label>
            <input type="text" name="notebook_name" class="form-control" required value="<?= e($notebook['notebook_name'] ?? '') ?>" placeholder="e.g. Stability Study Q1 2026">
        </div>
        <div class="col-md-4">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">— Select —</option>
                <option value="General" <?= ($notebook['category'] ?? '') === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Stability" <?= ($notebook['category'] ?? '') === 'Stability' ? 'selected' : '' ?>>Stability</option>
                <option value="Method Development" <?= ($notebook['category'] ?? '') === 'Method Development' ? 'selected' : '' ?>>Method Development</option>
                <option value="Validation" <?= ($notebook['category'] ?? '') === 'Validation' ? 'selected' : '' ?>>Validation</option>
                <option value="Research" <?= ($notebook['category'] ?? '') === 'Research' ? 'selected' : '' ?>>Research</option>
                <option value="QC" <?= ($notebook['category'] ?? '') === 'QC' ? 'selected' : '' ?>>QC</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Optional description of this notebook's purpose..."><?= e($notebook['description'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Create Notebook</button>
        <a href="/notebooks" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>
