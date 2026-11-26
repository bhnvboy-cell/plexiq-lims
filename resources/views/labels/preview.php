<?php
$title = 'Industrial Label Preview - Sample #' . $sampleId;
layout('app');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Label Preview: Sample #<?= $sampleId ?></h1>
            <p class="text-muted mb-0">Format: <?= strtoupper($label['format']) ?></p>
        </div>
        <div>
            <a href="/samples/<?= $sampleId ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Sample</a>
            <a href="/labels/print/<?= $sampleId ?>?download=1" class="btn btn-primary"><i class="bi bi-download"></i> Download Raw Print Stream</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>Direct Industrial Driver Output (<?= strtoupper($label['format']) ?>)</span>
            <small class="text-muted"><?= htmlspecialchars($label['filename']) ?></small>
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 border rounded font-monospace" style="min-height: 200px;"><code><?= htmlspecialchars($label['payload']) ?></code></pre>
        </div>
    </div>
</div>
