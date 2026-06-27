<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="page-title mb-0"><i class="bi bi-receipt me-2"></i><?= e($invoice['invoice_number']) ?></h4>
            <span class="badge bg-<?= match ($invoice['status']) { 'Draft'=>'secondary', 'Sent'=>'primary', 'Approved'=>'info', 'Paid'=>'success', 'Overdue'=>'danger', 'Cancelled'=>'danger', default=>'secondary' } ?> fs-6"><?= e($invoice['status']) ?></span>
        </div>
        <span class="text-muted small">Date: <?= e($invoice['invoice_date'] ?? '-') ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="/billing" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/billing/<?= $invoice['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <button class="btn btn-outline-info btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="/billing/<?= $invoice['id'] ?>/pdf" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
        <?php if ($invoice['payment_status'] !== 'Paid' && in_array($auth['role'], ['Admin'])): ?>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="bi bi-cash me-1"></i>Record Payment</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-person me-1"></i>Customer</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-label">Customer Name</div>
                        <div class="detail-value"><?= e($invoice['customer_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Customer Code</div>
                        <div class="detail-value"><?= e($invoice['customer_code'] ?? '-') ?></div>
                    </div>
                    <?php if (!empty($invoice['customer_address'])): ?>
                    <div class="col-12">
                        <div class="detail-label">Address</div>
                        <div class="detail-value"><?= nl2br(e($invoice['customer_address'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-list-check me-1"></i>Line Items</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $li): ?>
                        <tr>
                            <td><?= e($li['description']) ?></td>
                            <td><?= e($li['quantity'] ?? 1) ?></td>
                            <td><?= e(number_format((float)($li['unit_price'] ?? 0), 2)) ?></td>
                            <td><strong><?= e(number_format((float)($li['total_price'] ?? ($li['quantity'] ?? 1) * ($li['unit_price'] ?? 0)), 2)) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No line items.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-calculator me-1"></i>Summary</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span><?= e(number_format((float)($invoice['subtotal'] ?? 0), 2)) ?></span>
                </div>
                <?php if (($invoice['tax_amount'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tax</span>
                    <span><?= e(number_format((float)$invoice['tax_amount'], 2)) ?></span>
                </div>
                <?php endif; ?>
                <?php if (($invoice['discount_amount'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Discount</span>
                    <span class="text-danger">-<?= e(number_format((float)$invoice['discount_amount'], 2)) ?></span>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <strong>Total</strong>
                    <strong class="fs-5"><?= e(number_format((float)($invoice['total_amount'] ?? 0), 2)) ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Paid</span>
                    <span class="text-success"><?= e(number_format((float)($invoice['paid_amount'] ?? 0), 2)) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Balance Due</span>
                    <strong class="text-danger"><?= e(number_format((float)($invoice['total_amount'] ?? 0) - (float)($invoice['paid_amount'] ?? 0), 2)) ?></strong>
                </div>
            </div>
        </div>

        <?php if (!empty($payments)): ?>
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-cash me-1"></i>Payments</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Ref #</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><small><?= e($p['payment_date']) ?></small></td>
                            <td><strong><?= e(number_format((float)$p['amount'], 2)) ?></strong></td>
                            <td><small><?= e($p['payment_method'] ?? '-') ?></small></td>
                            <td><small><?= e($p['reference_number'] ?? '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Record Payment Modal -->
<?php if ($invoice['payment_status'] !== 'Paid' && in_array($auth['role'], ['Admin'])): ?>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/billing/<?= $invoice['id'] ?>/payments/store">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash me-1"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Invoice</label>
                        <input class="form-control" value="<?= e($invoice['invoice_number']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Balance Due</label>
                        <input class="form-control" value="<?= e(number_format((float)($invoice['total_amount'] ?? 0) - (float)($invoice['paid_amount'] ?? 0), 2)) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input name="amount" type="number" step="0.01" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input name="payment_date" type="date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input name="reference_number" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
