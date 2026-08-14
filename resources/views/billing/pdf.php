<?php $title = 'Invoice ' . e($invoice['invoice_number'] ?? ''); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($invoice['invoice_number'] ?? '') ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color: #212529; margin: 40px; }
    .invoice-header { display: flex; justify-content: space-between; margin-bottom: 30px; }
    .brand { font-size: 28px; font-weight: bold; color: #0d6efd; }
    .meta { text-align: right; font-size: 13px; line-height: 1.6; }
    .meta .invoice-no { font-size: 18px; font-weight: bold; }
    h4 { border-bottom: 2px solid #dee2e6; padding-bottom: 6px; }
    .info-grid { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 14px; line-height: 1.7; }
    table.items { width: 100%; border-collapse: collapse; font-size: 14px; }
    table.items th { background: #f1f3f5; text-align: left; padding: 8px; border-bottom: 2px solid #dee2e6; }
    table.items td { padding: 8px; border-bottom: 1px solid #e9ecef; }
    table.items .num { text-align: right; }
    .totals { width: 320px; margin-left: auto; margin-top: 24px; font-size: 14px; }
    .totals tr td { padding: 4px 8px; }
    .totals .grand { font-size: 18px; font-weight: bold; border-top: 2px solid #212529; }
    .footer { margin-top: 60px; font-size: 12px; color: #6c757d; text-align: center; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: bold; }
    .badge-success { background: #d1e7dd; color: #0f5132; }
    .badge-secondary { background: #e9ecef; color: #495057; }
    .badge-warning { background: #fff3cd; color: #664d03; }
</style>
</head>
<body>
<div class="invoice-header">
    <div>
        <div class="brand">PlexiQ LIMS</div>
        <div style="font-size:13px;color:#6c757d;">Quality Laboratory Services</div>
    </div>
    <div class="meta">
        <div class="invoice-no">Invoice <?= e($invoice['invoice_number'] ?? '') ?></div>
        <div>Status: <span class="badge bg-<?= ($invoice['status'] ?? '') === 'Paid' ? 'success' : (($invoice['status'] ?? '') === 'Pending' ? 'warning' : 'secondary') ?>"><?= e($invoice['status'] ?? '—') ?></span></div>
        <div>Invoice Date: <?= !empty($invoice['invoice_date']) ? date('d M Y', strtotime($invoice['invoice_date'])) : '—' ?></div>
        <div>Due Date: <?= !empty($invoice['due_date']) ? date('d M Y', strtotime($invoice['due_date'])) : '—' ?></div>
    </div>
</div>

<div class="info-grid">
    <div>
        <h4>Billed To</h4>
        <strong><?= e($invoice['customer_name'] ?? '—') ?></strong><br>
        <?= e($invoice['customer_code'] ?? '') ?><br>
        <?= e($invoice['address'] ?? '') ?><br>
        <?= e($invoice['city'] ?? '') ?><?= !empty($invoice['state']) ? ', ' . e($invoice['state']) : '' ?> <?= e($invoice['postal_code'] ?? '') ?><br>
        <?= e($invoice['country'] ?? '') ?>
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th>#</th>
            <th>Description</th>
            <th class="num">Quantity</th>
            <th class="num">Unit Price</th>
            <th class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
        <tr><td colspan="5" style="text-align:center;color:#6c757d;">No line items.</td></tr>
        <?php else: foreach ($items as $i => $item): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($item['description'] ?? '—') ?></td>
            <td class="num"><?= e($item['quantity'] ?? '') ?></td>
            <td class="num"><?= $item['unit_price'] !== null ? '$' . number_format((float)$item['unit_price'], 2) : '—' ?></td>
            <td class="num"><?= $item['total_price'] !== null ? '$' . number_format((float)$item['total_price'], 2) : '—' ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">$<?= number_format((float)($invoice['subtotal'] ?? 0), 2) ?></td></tr>
    <?php if ((float)($invoice['discount_amount'] ?? 0) > 0): ?>
    <tr><td>Discount</td><td class="num">-$<?= number_format((float)$invoice['discount_amount'], 2) ?></td></tr>
    <?php endif; ?>
    <tr><td>Tax</td><td class="num">$<?= number_format((float)($invoice['tax_amount'] ?? 0), 2) ?></td></tr>
    <tr class="grand"><td>Total</td><td class="num">$<?= number_format((float)($invoice['total_amount'] ?? 0), 2) ?></td></tr>
</table>

<?php if (!empty($invoice['notes'])): ?>
<div style="margin-top:24px;font-size:13px;">
    <h4>Notes</h4>
    <div><?= nl2br(e($invoice['notes'])) ?></div>
</div>
<?php endif; ?>

<div class="footer">This is a computer-generated invoice from PlexiQ LIMS. If you have any questions, contact your laboratory representative.</div>
</body>
</html>
