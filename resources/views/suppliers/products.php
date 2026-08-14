<?php $title = 'Products: ' . e($supplier['supplier_name']); layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-box-seam me-2"></i>Products — <?= e($supplier['supplier_name']) ?></h4>
    <a href="/suppliers/<?= $supplier['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Linked Products</h6>
                <span class="badge bg-secondary"><?= count($products ?? []) ?> products</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Supplier Code</th>
                            <th>Unit Price</th>
                            <th>Lead Time (days)</th>
                            <th>Preferred</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No products linked to this supplier.</td></tr>
                        <?php else: foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= e($p['product_name']) ?></div>
                                <small class="text-muted"><?= e($p['product_code']) ?></small>
                            </td>
                            <td><?= e($p['supplier_product_code'] ?? '—') ?></td>
                            <td><?= $p['unit_price'] !== null ? '$' . e($p['unit_price']) : '—' ?></td>
                            <td><?= e($p['lead_time_days'] ?? '—') ?></td>
                            <td><?= !empty($p['is_preferred']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-link-45deg me-1"></i>Link Product</h6></div>
            <div class="card-body">
                <form method="POST" action="/suppliers/<?= $supplier['id'] ?>/products">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">— Select Product —</option>
                            <?php foreach ($allProducts as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['product_code']) ?> — <?= e($p['product_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier Product Code</label>
                        <input type="text" name="supplier_product_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" name="unit_price" class="form-control" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lead Time (days)</label>
                        <input type="number" name="lead_time_days" class="form-control">
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-link-45deg"></i> Link Product</button>
                </form>
            </div>
        </div>
    </div>
</div>
