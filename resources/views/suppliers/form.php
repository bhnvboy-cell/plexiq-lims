<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $supplier ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $supplier ? 'Edit Supplier' : 'New Supplier' ?></h4>
    <a href="/suppliers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $supplier ? '/suppliers/' . $supplier['id'] : '/suppliers' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-12"><h6 class="text-primary"><i class="bi bi-info-circle me-1"></i>General</h6><hr class="mt-1"></div>
                <div class="col-md-4">
                    <label class="form-label">Supplier Code <span class="text-danger">*</span></label>
                    <input name="supplier_code" class="form-control" required value="<?= e($supplier['supplier_code'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                    <input name="supplier_name" class="form-control" required value="<?= e($supplier['supplier_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Supplier Type</label>
                    <select name="supplier_type" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Raw Material" <?= ($supplier['supplier_type'] ?? '') === 'Raw Material' ? 'selected' : '' ?>>Raw Material</option>
                        <option value="Packaging" <?= ($supplier['supplier_type'] ?? '') === 'Packaging' ? 'selected' : '' ?>>Packaging</option>
                        <option value="Service" <?= ($supplier['supplier_type'] ?? '') === 'Service' ? 'selected' : '' ?>>Service</option>
                        <option value="Equipment" <?= ($supplier['supplier_type'] ?? '') === 'Equipment' ? 'selected' : '' ?>>Equipment</option>
                        <option value="Calibration" <?= ($supplier['supplier_type'] ?? '') === 'Calibration' ? 'selected' : '' ?>>Calibration</option>
                        <option value="Other" <?= ($supplier['supplier_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rating (1-5)</label>
                    <input name="rating" type="number" step="0.1" min="1" max="5" class="form-control" value="<?= e($supplier['rating'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Approval Status</label>
                    <select name="approval_status" class="form-select">
                        <option value="Pending" <?= ($supplier['approval_status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Under Review" <?= ($supplier['approval_status'] ?? '') === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="Approved" <?= ($supplier['approval_status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= ($supplier['approval_status'] ?? '') === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ($supplier['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary"><i class="bi bi-geo-alt me-1"></i>Contact</h6><hr class="mt-1"></div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input name="contact_person" class="form-control" value="<?= e($supplier['contact_person'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input name="email" type="email" class="form-control" value="<?= e($supplier['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input name="phone" class="form-control" value="<?= e($supplier['phone'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Address</label>
                    <input name="address" class="form-control" value="<?= e($supplier['address'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input name="city" class="form-control" value="<?= e($supplier['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State / Province</label>
                    <input name="state" class="form-control" value="<?= e($supplier['state'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <input name="country" class="form-control" value="<?= e($supplier['country'] ?? '') ?>">
                </div>

                <div class="col-12 mt-3"><h6 class="text-primary"><i class="bi bi-briefcase me-1"></i>Business</h6><hr class="mt-1"></div>
                <div class="col-md-4">
                    <label class="form-label">Website</label>
                    <input name="website" class="form-control" value="<?= e($supplier['website'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tax ID / VAT</label>
                    <input name="tax_id" class="form-control" value="<?= e($supplier['tax_id'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-select">
                        <option value="USD" <?= ($supplier['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="EUR" <?= ($supplier['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                        <option value="GBP" <?= ($supplier['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                        <option value="CAD" <?= ($supplier['currency'] ?? '') === 'CAD' ? 'selected' : '' ?>>CAD</option>
                        <option value="Other" <?= ($supplier['currency'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Terms</label>
                    <input name="payment_terms" class="form-control" placeholder="e.g. Net 30" value="<?= e($supplier['payment_terms'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= e($supplier['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $supplier ? 'Update' : 'Create' ?> Supplier</button>
                <a href="/suppliers" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
