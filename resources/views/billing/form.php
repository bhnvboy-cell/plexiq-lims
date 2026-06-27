<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $invoice ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $invoice ? 'Edit Invoice' : 'New Invoice' ?></h4>
    <a href="/billing" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $invoice ? '/billing/' . $invoice['id'] . '/update' : '/billing/store' ?>" id="invoiceForm">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Invoice Number <span class="text-danger">*</span></label>
                    <input name="invoice_number" class="form-control" required value="<?= e($invoice['invoice_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                    <input name="invoice_date" type="date" class="form-control" required value="<?= $invoice['invoice_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="<?= e($invoice['due_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($invoice['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['customer_name']) ?> (<?= e($c['customer_code'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Draft" <?= ($invoice['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Sent" <?= ($invoice['status'] ?? '') === 'Sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="Approved" <?= ($invoice['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Paid" <?= ($invoice['status'] ?? '') === 'Paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="Cancelled" <?= ($invoice['status'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sample (Optional)</label>
                    <select name="sample_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($samples as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($invoice['sample_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['sample_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes / Terms</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($invoice['notes'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-primary"><i class="bi bi-list-check me-1"></i>Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLineItem()"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
                    </div>
                    <hr class="mt-1">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-3" id="lineItemsTable">
                    <thead>
                        <tr>
                            <th style="width:45%">Description</th>
                            <th style="width:12%">Qty</th>
                            <th style="width:18%">Unit Price</th>
                            <th style="width:15%">Total</th>
                            <th style="width:10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                        <?php foreach ($items as $i => $li): ?>
                        <tr>
                            <td><input name="items[<?= $i ?>][description]" class="form-control" value="<?= e($li['description']) ?>" required></td>
                            <td><input name="items[<?= $i ?>][quantity]" type="number" step="1" class="form-control" value="<?= e($li['quantity'] ?? 1) ?>" onchange="calcRow(this)"></td>
                            <td><input name="items[<?= $i ?>][unit_price]" type="number" step="0.01" class="form-control" value="<?= e($li['unit_price'] ?? 0) ?>" onchange="calcRow(this)"></td>
                            <td><span class="row-total fw-medium"><?= e(number_format((float)($li['total_price'] ?? ($li['quantity']??1)*($li['unit_price']??0)), 2)) ?></span></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calcTotal();"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td><input name="items[0][description]" class="form-control" placeholder="Description" required></td>
                            <td><input name="items[0][quantity]" type="number" step="1" class="form-control" value="1" onchange="calcRow(this)"></td>
                            <td><input name="items[0][unit_price]" type="number" step="0.01" class="form-control" value="0" onchange="calcRow(this)"></td>
                            <td><span class="row-total">0.00</span></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calcTotal();"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td><strong id="invoiceTotal">0.00</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Subtotal</label>
                    <input name="subtotal" type="number" step="0.01" class="form-control" id="subtotalInput" value="<?= e($invoice['subtotal'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax Rate (%)</label>
                    <input name="tax_rate" type="number" step="0.01" class="form-control" value="<?= e($invoice['tax_rate'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tax Amount</label>
                    <input name="tax_amount" type="number" step="0.01" class="form-control" id="taxAmountInput" value="<?= e($invoice['tax_amount'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount</label>
                    <input name="discount_amount" type="number" step="0.01" class="form-control" value="<?= e($invoice['discount_amount'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $invoice ? 'Update' : 'Create' ?> Invoice</button>
                <a href="/billing" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
let itemIndex = <?= !empty($items) ? count($items) : 1 ?>;

function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name$="[unit_price]"]').value) || 0;
    row.querySelector('.row-total').textContent = (qty * price).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.row-total').forEach(el => total += parseFloat(el.textContent) || 0);
    document.getElementById('invoiceTotal').textContent = total.toFixed(2);
    document.getElementById('subtotalInput').value = total.toFixed(2);
}

function addLineItem() {
    const tbody = document.querySelector('#lineItemsTable tbody');
    const idx = itemIndex++;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input name="items[${idx}][description]" class="form-control" placeholder="Description" required></td>
        <td><input name="items[${idx}][quantity]" type="number" step="1" class="form-control" value="1" onchange="calcRow(this)"></td>
        <td><input name="items[${idx}][unit_price]" type="number" step="0.01" class="form-control" value="0" onchange="calcRow(this)"></td>
        <td><span class="row-total">0.00</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calcTotal();"><i class="bi bi-x-lg"></i></button></td>`;
    tbody.appendChild(tr);
}

setTimeout(calcTotal, 100);
</script>
