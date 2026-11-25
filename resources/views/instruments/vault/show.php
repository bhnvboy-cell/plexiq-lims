<?php
$title = 'Vault Stream Inspection - ' . htmlspecialchars($vault['original_filename']);
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Raw Data Vault Record: <?= htmlspecialchars($vault['original_filename']) ?></h1>
            <p class="text-muted mb-0">UID: <code><?= htmlspecialchars($vault['vault_uid']) ?></code></p>
        </div>
        <div>
            <a href="/instruments/vault" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Vault</a>
            <form action="/instruments/vault/verify/<?= $vault['id'] ?>" method="POST" class="d-inline">
                <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i> Re-Verify SHA-256 Checksum</button>
            </form>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">Forensic Integrity Metadata</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">SHA-256 Digest:</span>
                            <code class="text-break"><?= htmlspecialchars($vault['sha256_hash']) ?></code>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">HMAC-SHA256 Signature:</span>
                            <code class="text-break"><?= htmlspecialchars($vault['hmac_signature']) ?></code>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">File Size:</span>
                            <span><?= number_format($vault['file_size_bytes']) ?> bytes</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Driver Used:</span>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($vault['driver_used']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light fw-bold">Parsed Analytical Parameters (<?= count($parsedResults) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 250px;">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Sample</th>
                                    <th>Analyte</th>
                                    <th>Value</th>
                                    <th>$R_t$ (min)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($parsedResults)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">No parsed records.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($parsedResults as $p): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($p['sample_code']) ?></strong></td>
                                            <td><?= htmlspecialchars($p['test_code']) ?></td>
                                            <td><?= htmlspecialchars((string)$p['value']) ?> <?= htmlspecialchars($p['unit'] ?? '') ?></td>
                                            <td><?= htmlspecialchars((string)($p['retention_time'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Raw Stream Preview -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Original Raw Instrument Stream (Immutable)</div>
        <div class="card-body">
            <pre class="bg-light p-3 border rounded font-monospace" style="max-height: 400px; overflow-y: auto;"><code><?= htmlspecialchars($vault['raw_content']) ?></code></pre>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
