<?php $title = 'Register Sample'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2><i class="bi bi-plus-circle"></i> Register New Sample</h2>
        <p class="text-muted mb-0">Commercial LIMS: Product + Lifecycle Stage automatically resolves the approved specification version and plans tests.</p>
    </div>
    <a href="/samples" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form method="POST" action="/samples">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-box-seam me-2"></i>Product & Master Data Routing</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer</label>
                        <select name="customer_id" class="form-select">
                            <option value="">-- Internal / No External Customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= is_array($c) ? $c['id'] : $c->id ?>"><?= htmlspecialchars(is_array($c) ? $c['customer_name'] : $c->customer_name) ?> (<?= htmlspecialchars(is_array($c) ? $c['customer_code'] : $c->customer_code) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">Product *</label>
                        <select name="product_id" id="product_id" class="form-select border-primary" required>
                            <option value="">-- Select Product for Commercial Test Plan & Spec Snapshots --</option>
                            <?php foreach ($products as $p): ?>
                            <?php $pId = is_array($p) ? $p['id'] : $p->id; ?>
                            <?php $pName = is_array($p) ? $p['product_name'] : $p->product_name; ?>
                            <?php $pCode = is_array($p) ? $p['product_code'] : $p->product_code; ?>
                            <option value="<?= $pId ?>"><?= htmlspecialchars($pName) ?> (<?= htmlspecialchars($pCode) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-primary"><i class="bi bi-info-circle me-1"></i>Selecting a product automatically attaches its active approved specification and plans stage-specific tests.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Lifecycle Stage *</label>
                            <select name="sample_stage" class="form-select" required>
                                <?php foreach ($stages as $stage): ?>
                                <option value="<?= $stage ?>" <?= $stage === 'Finished Good' ? 'selected' : '' ?>><?= $stage ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Sample Template</label>
                            <select name="template_id" class="form-select">
                                <option value="">-- Standard Sample --</option>
                                <?php if (!empty($templates)): ?>
                                    <?php foreach ($templates as $tmpl): ?>
                                    <option value="<?= $tmpl['id'] ?>"><?= htmlspecialchars($tmpl['template_name']) ?> (<?= htmlspecialchars($tmpl['template_code']) ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch / Lot Number</label>
                            <input type="text" name="batch_number" class="form-control" placeholder="e.g. LOT-2026-001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Size</label>
                            <input type="text" name="batch_size" class="form-control" placeholder="e.g. 500 kg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Verification -->
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="bi bi-shield-check me-2"></i>Receipt Verification & Inspection</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Receipt Condition Status</label>
                            <select name="receipt_check_status" class="form-select">
                                <option value="Passed" selected>Passed (Intact, In-Spec Temp)</option>
                                <option value="Conditional">Conditional (Minor Package Defect)</option>
                                <option value="Failed">Failed (Damaged / Temp Excursion)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Receipt Notes / Inspection Details</label>
                            <input type="text" name="receipt_check_notes" class="form-control" placeholder="Container seals verified intact">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="bi bi-calendar3 me-2"></i>Dates & Priority</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Sampling Date *</label>
                            <input type="date" name="sampling_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <div class="form-text">Determines which version of the specification applies.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Received Date *</label>
                            <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manufacture Date</label>
                            <input type="date" name="manufacture_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Completion Date</label>
                            <input type="date" name="target_completion_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
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

            <div class="card mb-3 shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="bi bi-people me-2"></i>Assign Personnel</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Analyst</label>
                            <select name="assigned_analyst_id" class="form-select">
                                <option value="">Select Analyst</option>
                                <?php foreach ($analysts as $a): ?>
                                <option value="<?= is_array($a) ? $a['id'] : $a->id ?>"><?= htmlspecialchars(is_array($a) ? $a['full_name'] : $a->full_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Reviewer</label>
                            <select name="assigned_reviewer_id" class="form-select">
                                <option value="">Select Reviewer</option>
                                <?php foreach ($reviewers as $r): ?>
                                <option value="<?= is_array($r) ? $r['id'] : $r->id ?>"><?= htmlspecialchars(is_array($r) ? $r['full_name'] : $r->full_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Approver</label>
                            <select name="assigned_approver_id" class="form-select">
                                <option value="">Select Approver</option>
                                <?php foreach ($approvers as $ap): ?>
                                <option value="<?= is_array($ap) ? $ap['id'] : $ap->id ?>"><?= htmlspecialchars(is_array($ap) ? $ap['full_name'] : $ap->full_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Legacy / Ad-hoc manual test selection toggle (only used if no product is selected) -->
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-card-checklist me-2"></i>Manual Test Assignment (Legacy Fallback)</span>
            <small class="text-muted">Ignored when a Product is selected (Product Test Plan applies automatically)</small>
        </div>
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

    <div class="card mb-3 shadow-sm border-0">
        <div class="card-header bg-light fw-bold"><i class="bi bi-chat-left-text me-2"></i>Notes & Comments</div>
        <div class="card-body">
            <textarea name="notes" class="form-control" rows="2" placeholder="Additional sampling or registration notes..."></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> Register Sample</button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
