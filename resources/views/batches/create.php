<?php $title = 'Register New Batch'; layout('app'); ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card shadow-sm">
<div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Register New Batch</h5></div>
<div class="card-body">
<form method="POST" action="/batches">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Batch Number <span class="text-danger">*</span></label>
            <input type="text" name="batch_number" class="form-control" required placeholder="e.g. BATCH-2026-001">
        </div>
        <div class="col-md-6">
            <label class="form-label">Batch Size</label>
            <input type="text" name="batch_size" class="form-control" placeholder="e.g. 1000 kg / 500 L">
        </div>
        <div class="col-md-6">
            <label class="form-label">Product <span class="text-danger">*</span></label>
            <select name="product_id" class="form-select" required id="productSelect" onchange="updateProductTests()">
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p->id ?>" data-category="<?= e($p->category ?? '') ?>"><?= e($p->product_code) ?> — <?= e($p->product_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Assigned Analyst</label>
            <select name="assigned_analyst_id" class="form-select">
                <option value="">— Auto-assign —</option>
                <?php foreach ($analysts as $a): ?>
                <option value="<?= $a->id ?>"><?= htmlspecialchars($a->full_name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Manufacture Date</label>
            <input type="date" name="manufacture_date" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Optional batch notes..."></textarea>
        </div>
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>A sample will be automatically created with all product-specific tests assigned. Tests and specifications are configured in <a href="/master/product-tests" class="text-decoration-none">Product-Test Mapping</a>.</small>
                </div>
            </div>
        </div>
        <div class="col-12" id="testPreview" style="display:none">
            <label class="form-label fw-bold">Tests that will be auto-assigned:</label>
            <div id="testList" class="mb-0"></div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Register Batch &amp; Create Sample</button>
        <a href="/batches" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>
<script>
function updateProductTests() {
    const sel = document.getElementById('productSelect');
    const preview = document.getElementById('testPreview');
    const list = document.getElementById('testList');
    const id = sel.value;
    if (!id) { preview.style.display = 'none'; list.innerHTML = ''; return; }
    fetch('/batches/product-tests/' + id)
        .then(r => r.json())
        .then(data => {
            preview.style.display = 'block';
            if (data.length === 0) {
                list.innerHTML = '<span class="text-warning">No tests mapped to this product. Configure in Product-Test Mapping.</span>';
            } else {
                list.innerHTML = '<ul class="mb-0">' + data.map(t =>
                    '<li><strong>' + he(t.test_name) + '</strong> &mdash; Spec: ' + he(t.effective_spec || 'No spec') + '</li>'
                ).join('') + '</ul>';
            }
        });
}
function he(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
</script>
