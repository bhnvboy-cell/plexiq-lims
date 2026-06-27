<?php $title = $manufacturer ? 'Edit Manufacturer' : 'New Manufacturer'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-building-gear me-2"></i><?= $manufacturer ? 'Edit Manufacturer' : 'New Manufacturer' ?></h4>
    <a href="/master/manufacturers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $manufacturer ? '/master/manufacturers/' . $manufacturer['id'] : '/master/manufacturers' ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                    <input type="text" name="company_name" class="form-control" required value="<?= e($manufacturer['company_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Active</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= !isset($manufacturer) || $manufacturer['is_active'] ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= isset($manufacturer) && !$manufacturer['is_active'] ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= e($manufacturer['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= e($manufacturer['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= e($manufacturer['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="<?= e($manufacturer['country'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Postal Code</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= e($manufacturer['postal_code'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($manufacturer['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($manufacturer['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Website</label>
                    <input type="url" name="website" class="form-control" value="<?= e($manufacturer['website'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo Path (relative to storage/app/public/)</label>
                    <input type="text" name="logo_path" class="form-control" placeholder="e.g. logo/manufacturer-logo.png" value="<?= e($manufacturer['logo_path'] ?? '') ?>">
                    <small class="text-muted">Upload via file manager to <code>storage/app/public/</code></small>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= $manufacturer ? 'Update Manufacturer' : 'Create Manufacturer' ?></button>
                <a href="/master/manufacturers" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
