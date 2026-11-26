<?php
layout('app');
$title = 'Create Production Lot';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-box-seam me-2"></i>Create Production Lot</h1>
            <p class="text-muted mb-0">Record a manufactured lot or raw material shipment for quality disposition.</p>
        </div>
        <a href="/production-lots" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-info-circle me-1"></i> Lot Information
                </div>
                <div class="card-body">
                    <form method="POST" action="/production-lots">
                        <?= csrf_field() ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Lot Number / Batch Number *</label>
                                <input type="text" name="lot_number" class="form-control" placeholder="e.g. LOT-2026-PAR-001" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">ERP Batch Reference Number</label>
                                <input type="text" name="erp_batch_number" class="form-control" placeholder="SAP/Oracle Batch ID">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Product *</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Active approved specification version will be automatically linked based on manufacturing date.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manufacturing Facility / Site</label>
                                <select name="site_id" class="form-select">
                                    <option value="">-- Default Site --</option>
                                    <?php foreach ($sites as $site): ?>
                                        <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lot Size</label>
                                <input type="number" step="0.0001" name="lot_size" class="form-control" placeholder="e.g. 5000">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Unit of Measure</label>
                                <select name="lot_unit" class="form-select">
                                    <option value="kg" selected>Kilograms (kg)</option>
                                    <option value="L">Liters (L)</option>
                                    <option value="tabs">Tablets (tabs)</option>
                                    <option value="vials">Vials</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Manufacturing Date *</label>
                                <input type="date" name="manufacturing_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry / Retest Date</label>
                                <input type="date" name="expiry_date" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes & Comments</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Batch manufacturing records reference, synthesis route, or notes..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> Register Production Lot</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
