<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-receipt me-2"></i>Invoices</h4>
        <span class="text-muted small"><?= count($invoices) ?> invoice(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
    <a href="/billing/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Invoice</a>
    <?php endif; ?>
</div>

<?php if (empty($invoices)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-receipt"></i>
        <h5>No Invoices</h5>
        <p class="text-muted">No invoices have been created yet.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><a href="/billing/<?= $inv['id'] ?>" class="fw-medium text-decoration-none"><?= e($inv['invoice_number']) ?></a></td>
                    <td><?= e($inv['customer_name'] ?? 'N/A') ?></td>
                    <td><small class="text-muted"><?= e($inv['invoice_date'] ?? '-') ?></small></td>
                    <td><strong><?= e(number_format((float)($inv['total_amount'] ?? 0), 2)) ?></strong></td>
                    <td><?php $sbadge = match ($inv['status']) { 'Draft'=>'secondary', 'Sent'=>'primary', 'Approved'=>'info', 'Paid'=>'success', 'Overdue'=>'danger', 'Cancelled'=>'danger', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $sbadge ?>"><?= e($inv['status']) ?></span>
                    </td>
                    <td><?php $pbadge = match ($inv['payment_status']) { 'Pending'=>'warning', 'Partial'=>'info', 'Paid'=>'success', 'Overdue'=>'danger', default=>'secondary' }; ?>
                        <span class="badge bg-<?= $pbadge ?> bg-opacity-10 text-<?= $pbadge ?>"><?= e($inv['payment_status'] ?? 'Pending') ?></span>
                    </td>
                    <td class="text-end">
                        <a href="/billing/<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
