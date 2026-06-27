<?php $title = 'Product-Test Mapping'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-diagram-3 me-2"></i>Product-Test Mapping</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ptModal"><i class="bi bi-plus"></i> Map Test</button>
</div>

<?php
$grouped = [];
foreach ($mappings as $m) {
    $key = $m['category'] . '|||' . $m['product_name'] . '|||' . $m['product_id'];
    $grouped[$key][] = $m;
}
ksort($grouped);
?>

<?php if (empty($mappings)): ?>
<div class="alert alert-info">No product-test mappings configured. <a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#ptModal">Create your first mapping</a>.</div>
<?php else: ?>
<?php foreach ($grouped as $key => $tests):
    list($cat, $pname, $pid) = explode('|||', $key);
?>
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="badge bg-info bg-opacity-10 text-info me-2"><?= e($cat) ?></span><strong><?= e($pname) ?></strong></span>
        <span class="badge bg-secondary"><?= count($tests) ?> tests</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Test</th><th>Method</th><th>Unit</th><th>Global Spec</th><th>Product-Specific Spec</th><th>Effective Spec</th><th>Active</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tests as $m): ?>
                <tr>
                    <td><strong><?= e($m['test_name']) ?></strong><br><small class="text-muted"><?= e($m['test_code']) ?></small></td>
                    <td><?= e($m['method_name'] ?? '—') ?></td>
                    <td><?= e($m['unit_code'] ?? '—') ?></td>
                    <td><code><?= e($m['global_spec'] ?? '—') ?></code></td>
                    <td>
                        <?php if ($m['min_spec_limit'] !== null || $m['max_spec_limit'] !== null || $m['spec_limit_text']): ?>
                            <code><?= $m['spec_limit_text'] ? e($m['spec_limit_text']) : e($m['min_spec_limit']) . ' - ' . e($m['max_spec_limit']) ?></code>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><code class="text-success"><?= e($m['effective_spec']) ?></code></td>
                    <td><?= $m['is_active'] ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i></span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i></span>' ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="editMapping(<?= $m['id'] ?>)"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="/master/product-tests/<?= $m['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this mapping?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Mapping Modal -->
<div class="modal fade" id="ptModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST" action="/master/product-tests" id="ptForm">
    <?= csrf_field() ?>
    <div class="modal-header"><h5 class="modal-title">Product-Test Mapping</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Product <span class="text-danger">*</span></label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p->id ?>"><?= e($p->product_code) ?> — <?= e($p->product_name) ?> (<?= e($p->category ?? 'N/A') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Test <span class="text-danger">*</span></label>
                <select name="test_id" class="form-select" required>
                    <option value="">— Select —</option>
                    <?php foreach ($allTests as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['test_code']) ?> — <?= e($t['test_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Product-Specific Min</label>
                <input type="number" step="any" name="min_spec_limit" class="form-control" placeholder="Leave blank to use global">
            </div>
            <div class="col-md-4">
                <label class="form-label">Product-Specific Max</label>
                <input type="number" step="any" name="max_spec_limit" class="form-control" placeholder="Leave blank to use global">
            </div>
            <div class="col-md-4">
                <label class="form-label">Spec Text (override)</label>
                <input type="text" name="spec_limit_text" class="form-control" placeholder="e.g. NMT 0.5%">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Mapping</button>
    </div>
</form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editPtModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST" id="editPtForm">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="PUT">
    <div class="modal-header"><h5 class="modal-title">Edit Product-Test Mapping</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Product</label><input type="text" id="editProduct" class="form-control" readonly></div>
            <div class="col-md-6"><label class="form-label">Test</label><input type="text" id="editTest" class="form-control" readonly></div>
            <div class="col-md-4"><label class="form-label">Min Spec Limit</label><input type="number" step="any" name="min_spec_limit" id="editMin" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Max Spec Limit</label><input type="number" step="any" name="max_spec_limit" id="editMax" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Spec Text</label><input type="text" name="spec_limit_text" id="editSpecText" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="editSort" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Active</label><select name="is_active" id="editActive" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update</button>
    </div>
</form>
</div></div></div>

<script>
function editMapping(id) {
    fetch('/master/product-tests/' + id + '/edit')
        .then(r => r.json())
        .then(d => {
            const m = document.getElementById('editPtModal');
            m.querySelector('#editProduct').value = d.product_name + ' (' + d.product_code + ')';
            m.querySelector('#editTest').value = d.test_name + ' (' + d.test_code + ')';
            m.querySelector('#editMin').value = d.min_spec_limit || '';
            m.querySelector('#editMax').value = d.max_spec_limit || '';
            m.querySelector('#editSpecText').value = d.spec_limit_text || '';
            m.querySelector('#editSort').value = d.sort_order || 0;
            m.querySelector('#editActive').value = d.is_active ? '1' : '0';
            m.querySelector('#editPtForm').action = '/master/product-tests/' + id;
            new bootstrap.Modal(m).show();
        });
}
</script>
