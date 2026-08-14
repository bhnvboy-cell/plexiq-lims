<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-truck me-2"></i><?= e($supplier['supplier_name']) ?></h4>
        <span class="text-muted small">Code: <?= e($supplier['supplier_code']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/suppliers" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if (in_array($auth['role'], ['Admin'])): ?>
        <a href="/suppliers/<?= $supplier['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-info-circle me-1"></i>General Info</strong></div>
            <div class="card-body">
                <div class="detail-label">Supplier Code</div>
                <div class="detail-value mb-3"><?= e($supplier['supplier_code']) ?></div>
                <div class="detail-label">Supplier Name</div>
                <div class="detail-value mb-3"><?= e($supplier['supplier_name']) ?></div>
                <div class="detail-label">Type</div>
                <div class="detail-value mb-3"><span class="badge bg-info bg-opacity-10 text-info"><?= e($supplier['supplier_type'] ?? '-') ?></span></div>
                <div class="detail-label">Status</div>
                <div class="detail-value mb-3"><?= !empty($supplier['is_approved']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></div>
                <div class="detail-label">Rating</div>
                <div class="detail-value mb-3"><?= $supplier['rating'] ? '<span class="badge bg-warning bg-opacity-10 text-warning">' . e($supplier['rating']) . '/5</span>' : '<span class="text-muted">Not rated</span>' ?></div>
                <div class="detail-label">Approval Status</div>
                <div class="detail-value"><?php $abadge = match ($supplier['status'] ?? '') { 'Approved'=>'success', 'Pending'=>'warning', 'Rejected'=>'danger', 'Under Review'=>'info', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $abadge ?>"><?= e($supplier['status'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-geo-alt me-1"></i>Contact Information</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Contact Person</div>
                        <div class="detail-value mb-3"><?= e($supplier['contact_person'] ?? '-') ?></div>
                        <div class="detail-label">Email</div>
                        <div class="detail-value mb-3"><?= e($supplier['email'] ?? '-') ?></div>
                        <div class="detail-label">Phone</div>
                        <div class="detail-value mb-3"><?= e($supplier['phone'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Address</div>
                        <div class="detail-value mb-3"><?= nl2br(e($supplier['address'] ?? '-')) ?></div>
                        <div class="detail-label">City</div>
                        <div class="detail-value mb-3"><?= e($supplier['city'] ?? '-') ?></div>
                        <div class="detail-label">Country</div>
                        <div class="detail-value mb-3"><?= e($supplier['country'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-briefcase me-1"></i>Business Details</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Website</div>
                        <div class="detail-value mb-3"><?= e($supplier['website'] ?? '-') ?></div>
                        <div class="detail-label">Tax ID / VAT</div>
                        <div class="detail-value mb-3"><?= e($supplier['tax_id'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Payment Terms</div>
                        <div class="detail-value mb-3"><?= e($supplier['payment_terms'] ?? '-') ?></div>
                        <div class="detail-label">Currency</div>
                        <div class="detail-value mb-3"><?= e($supplier['currency'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="detail-label">Notes</div>
                <div class="detail-value"><?= nl2br(e($supplier['notes'] ?? '-')) ?></div>
            </div>
        </div>

        <?php if (!empty($supplier['products'])): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-box me-1"></i>Products Supplied</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Product Code</th><th>Product Name</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($supplier['products'] as $p): ?>
                        <tr>
                            <td><?= e($p['product_code']) ?></td>
                            <td><?= e($p['product_name']) ?></td>
                            <td><?= !empty($p['is_preferred']) ? '<span class="badge bg-success">Preferred</span>' : '<span class="badge bg-secondary">Standard</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-award me-1"></i>Qualifications</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Type</th><th>Date</th><th>Result</th><th>Certificate</th><th>Auditor</th><th>Expiry</th></tr></thead>
                    <tbody>
                        <?php foreach ($qualifications as $q): ?>
                        <tr>
                            <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($q['qualification_type']) ?></span></td>
                            <td><?= e($q['qualification_date']) ?></td>
                            <td><?php $rbadge = match ($q['result']) { 'Pass'=>'success', 'Fail'=>'danger', 'Conditional'=>'warning', default=>'secondary' }; ?>
                                <span class="badge bg-<?= $rbadge ?>"><?= e($q['result']) ?></span>
                            </td>
                            <td><?= e($q['certificate_number'] ?? '-') ?></td>
                            <td><?= e($q['auditor'] ?? '-') ?></td>
                            <td><small class="text-muted"><?= e($q['expiry_date'] ?? '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($qualifications)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No qualifications recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
