<?php
layout('app');
$title = 'Create Product Specification';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center mb-4">
                <a href="/specifications" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Back</a>
                <div>
                    <h1 class="h3 mb-0">Create Product Specification</h1>
                    <p class="text-muted mb-0">Establish master specification header and initialize Draft Version 1.0</p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <form action="/specifications" method="POST" class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Specification Code <span class="text-danger">*</span></label>
                            <input type="text" name="spec_code" class="form-control" required placeholder="e.g. SPEC-STARCH-001">
                            <small class="text-muted">Unique identifier for this specification document.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Product <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-select" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Compendial / Quality Grade</label>
                            <input type="text" name="grade" class="form-control" value="Standard" placeholder="e.g. USP-NF, Ph. Eur., Food Grade, Reagent ACS">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Customer Target (Optional)</label>
                            <select name="customer_id" class="form-select">
                                <option value="">Global / Standard Specification (All Customers)</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Initial Version Effective From</label>
                            <input type="date" name="effective_from" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/specifications" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Initialize Specification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
