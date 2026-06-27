<?php $title = 'Certificate of Analysis'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-text"></i> Certificate of Analysis (COA)</h2>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>COA Number</th><th>Sample Code</th><th>Customer</th><th>Product</th><th>Status</th><th>Generated</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $d): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['document_number']) ?></strong></td>
                    <td><?= htmlspecialchars($d['sample_code']) ?></td>
                    <td><?= htmlspecialchars($d['customer_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($d['product_name'] ?? 'N/A') ?></td>
                    <td>
                        <span class="badge bg-<?= ['Draft'=>'warning','Released'=>'success','Revoked'=>'danger'][$d['status']] ?? 'secondary' ?>">
                            <?= $d['status'] ?>
                        </span>
                    </td>
                    <td><?= date('Y-m-d H:i', strtotime($d['generated_at'])) ?></td>
                    <td>
                        <a href="/coa/<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                        <a href="/coa/<?= $d['id'] ?>/pdf" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</a>
                        <?php if ($d['status'] === 'Draft' && in_array($auth['role'], ['Admin', 'Approver'])): ?>
                        <form method="POST" action="/coa/<?= $d['id'] ?>/release" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Release this COA?')"><i class="bi bi-check"></i> Release</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($documents)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No COA documents found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
