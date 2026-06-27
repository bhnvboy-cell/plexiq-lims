<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-cash me-2"></i>Record Payment</h4>
    <a href="/billing/<?= $invoice['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Invoice</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $payment ? '/billing/' . $invoice['id'] . '/payments/' . $payment['id'] . '/update' : '/billing/' . $invoice['id'] . '/payments/store' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Invoice</label>
                    <input class="form-control" value="<?= e($invoice['invoice_number']) ?>" disabled>
                    <div class="form-text">Balance Due: <?= e(number_format((float)($invoice['total_amount'] ?? 0) - (float)($invoice['paid_amount'] ?? 0), 2)) ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Invoice Date</label>
                    <input class="form-control" value="<?= e($invoice['invoice_date'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customer</label>
                    <input class="form-control" value="<?= e($invoice['customer_name'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input name="amount" type="number" step="0.01" class="form-control" required value="<?= e($payment['amount'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                    <input name="payment_date" type="date" class="form-control" required value="<?= $payment['payment_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="Cash" <?= ($payment['payment_method'] ?? '') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="Check" <?= ($payment['payment_method'] ?? '') === 'Check' ? 'selected' : '' ?>>Check</option>
                        <option value="Bank Transfer" <?= ($payment['payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="Credit Card" <?= ($payment['payment_method'] ?? '') === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                        <option value="Debit Card" <?= ($payment['payment_method'] ?? '') === 'Debit Card' ? 'selected' : '' ?>>Debit Card</option>
                        <option value="Other" <?= ($payment['payment_method'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reference Number</label>
                    <input name="reference_number" class="form-control" value="<?= e($payment['reference_number'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($payment['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= $payment ? 'Update' : 'Record' ?> Payment</button>
            </div>
        </form>
    </div>
</div>
