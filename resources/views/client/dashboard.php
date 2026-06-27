<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - PlexiQ LIMS</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="256x256" href="/assets/images/plexiq-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/lims.css" rel="stylesheet">
    <style>
        .client-header { background: linear-gradient(135deg, #11998e, #0a8a7a); color: #fff; padding: 24px 0; }
        .client-body { min-height: calc(100vh - 140px); }
    </style>
</head>
<body>
    <div class="client-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1"><i class="bi bi-building me-2"></i><?= htmlspecialchars($customerName ?: 'Client Portal') ?></h3>
                    <p class="mb-0 opacity-75">Welcome, <?= htmlspecialchars($user['full_name']) ?></p>
                </div>
                <div>
                    <a href="/client/logout" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
    <div class="client-body bg-light py-4">
        <div class="container">
            <?php $success = session_flash('success'); ?>
            <?php $error = session_flash('error'); ?>
            <?php $info = session_flash('info'); ?>
            <?php if ($info): ?><div class="alert alert-info py-2"><?= htmlspecialchars($info) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header"><i class="bi bi-file-text me-1"></i>Certificate of Analysis</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>COA Number</th>
                                <th>Sample Code</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['document_number']) ?></strong></td>
                                <td><?= htmlspecialchars($d['sample_code']) ?></td>
                                <td><?= htmlspecialchars($d['product_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($d['batch_number'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= ['Draft'=>'warning','Released'=>'success','Revoked'=>'danger'][$d['status']] ?? 'secondary' ?>">
                                        <?= $d['status'] ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($d['generated_at'])) ?></td>
                                <td>
                                    <a href="/client/coa/<?= $d['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-eye"></i> View</a>
                                    <a href="/client/coa/<?= $d['id'] ?>/pdf" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($documents)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-file-earmark-text fs-1 d-block mb-2 opacity-25"></i>
                                No COA documents available yet.
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-white border-top py-3 text-center text-muted small">
        &copy; <?= date('Y') ?> PlexiQ LIMS v1.0
    </footer>
</body>
</html>
