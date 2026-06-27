<?php $title = 'Register Sample'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-plus-circle"></i> Register New Sample</h2>
    <a href="/samples" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/samples">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Sample Information</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c->id ?>"><?= htmlspecialchars($c->customer_name) ?> (<?= htmlspecialchars($c->customer_code) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= $p->id ?>"><?= htmlspecialchars($p->product_name) ?> (<?= htmlspecialchars($p->product_code) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. BATCH-001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch Size</label>
                        <input type="text" name="batch_size" class="form-control" placeholder="e.g. 1000 kg">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Dates & Priority</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Manufacture Date</label>
                        <input type="date" name="manufacture_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Received Date *</label>
                        <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Completion Date</label>
                        <input type="date" name="target_completion_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Normal" selected>Normal</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Assign Personnel</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Analyst</label>
                    <select name="assigned_analyst_id" class="form-select">
                        <option value="">Select Analyst</option>
                        <?php foreach ($analysts as $a): ?>
                        <option value="<?= $a->id ?>"><?= htmlspecialchars($a->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reviewer</label>
                    <select name="assigned_reviewer_id" class="form-select">
                        <option value="">Select Reviewer</option>
                        <?php foreach ($reviewers as $r): ?>
                        <option value="<?= $r->id ?>"><?= htmlspecialchars($r->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Approver</label>
                    <select name="assigned_approver_id" class="form-select">
                        <option value="">Select Approver</option>
                        <?php foreach ($approvers as $a): ?>
                        <option value="<?= $a->id ?>"><?= htmlspecialchars($a->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Tests to Perform</div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($tests as $t): ?>
                <div class="col-md-4 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="test_ids[]" value="<?= $t['id'] ?>" id="test_<?= $t['id'] ?>">
                        <label class="form-check-label" for="test_<?= $t['id'] ?>">
                            <?= htmlspecialchars($t['test_code']) ?> - <?= htmlspecialchars($t['test_name']) ?>
                            <small class="text-muted">(<?= htmlspecialchars($t['spec_limit_text'] ?? '') ?>)</small>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Register Sample</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
