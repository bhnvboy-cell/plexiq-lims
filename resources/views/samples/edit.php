<?php $title = 'Edit Sample: ' . $sample['sample_code']; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-pencil"></i> Edit Sample: <?= htmlspecialchars($sample['sample_code']) ?></h2>
    <a href="/samples/<?= $sample['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/samples/<?= $sample['id'] ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Sample Information</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-select">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c->id ?>" <?= $c->id == $sample['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c->customer_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= $p->id ?>" <?= $p->id == $sample['product_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p->product_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($sample['batch_number'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch Size</label>
                        <input type="text" name="batch_size" class="form-control" value="<?= htmlspecialchars($sample['batch_size'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Dates & Priority</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Received Date</label>
                        <input type="date" name="received_date" class="form-control" value="<?= $sample['received_date'] ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Completion Date</label>
                        <input type="date" name="target_completion_date" class="form-control" value="<?= $sample['target_completion_date'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manufacture Date</label>
                        <input type="date" name="manufacture_date" class="form-control" value="<?= $sample['manufacture_date'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= $sample['expiry_date'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <?php foreach (['Low','Normal','High','Urgent'] as $p): ?>
                            <option value="<?= $p ?>" <?= $sample['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
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
                        <option value="<?= $a->id ?>" <?= $a->id == $sample['assigned_analyst_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reviewer</label>
                    <select name="assigned_reviewer_id" class="form-select">
                        <option value="">Select Reviewer</option>
                        <?php foreach ($reviewers as $r): ?>
                        <option value="<?= $r->id ?>" <?= $r->id == $sample['assigned_reviewer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Approver</label>
                    <select name="assigned_approver_id" class="form-select">
                        <option value="">Select Approver</option>
                        <?php foreach ($approvers as $a): ?>
                        <option value="<?= $a->id ?>" <?= $a->id == $sample['assigned_approver_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($sample['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Sample</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
