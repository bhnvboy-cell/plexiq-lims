<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COA: <?= htmlspecialchars($document['document_number']) ?> - PlexiQ LIMS</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="256x256" href="/assets/images/plexiq-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/lims.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="bg-white border-bottom py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-file-text me-1"></i>COA: <?= htmlspecialchars($document['document_number']) ?></span>
            <div>
                <a href="/client/coa/<?= $document['id'] ?>/pdf" class="btn btn-sm btn-danger"><i class="bi bi-file-pdf"></i> Download PDF</a>
                <a href="/client/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>
    </div>
    <div class="container py-4">
        <div class="card">
            <div class="card-body p-4">
                <style>
                    .coa-preview { font-family: Arial, sans-serif; font-size: 12px; }
                    .coa-preview table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                    .coa-preview th, .coa-preview td { border: 1px solid #333; padding: 6px; text-align: left; }
                    .coa-preview th { background-color: #e0e0e0; }
                    .coa-preview .header { text-align: center; margin-bottom: 20px; }
                    .coa-preview .footer { margin-top: 30px; font-size: 10px; text-align: center; color: #999; }
                </style>
                <div class="coa-preview">
                    <?= $coaHtml ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
