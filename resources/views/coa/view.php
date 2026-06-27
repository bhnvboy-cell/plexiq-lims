<?php $title = 'COA: ' . $document['document_number']; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-pdf"></i> COA: <?= htmlspecialchars($document['document_number']) ?></h2>
    <div>
        <a href="/coa/<?= $document['id'] ?>/pdf" class="btn btn-danger"><i class="bi bi-file-pdf"></i> Download PDF</a>
        <a href="/coa" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

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
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
