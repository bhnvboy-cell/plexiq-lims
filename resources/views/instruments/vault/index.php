<?php
$title = 'Immutable Raw Data Vault (ALCOA+ Forensic Storage)';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Immutable Raw Data Vault</h1>
            <p class="text-muted mb-0">Original Attributable Analytical Streams with Cryptographic SHA-256 Tamper-Evidence</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Ingested Instrument Streams</span>
            <span class="badge bg-secondary"><?= count($records) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Received At</th>
                            <th>Vault UID</th>
                            <th>Instrument</th>
                            <th>Filename</th>
                            <th>Driver</th>
                            <th>SHA-256 Checksum</th>
                            <th>Status</th>
                            <th>Inspect</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No raw instrument streams ingested into the vault yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['received_at']) ?></td>
                                    <td><code><?= htmlspecialchars(substr($r['vault_uid'], 0, 18)) ?>...</code></td>
                                    <td><?= htmlspecialchars($r['instrument_name'] ?? 'Edge Collector') ?></td>
                                    <td><strong><?= htmlspecialchars($r['original_filename']) ?></strong></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['driver_used']) ?></span></td>
                                    <td>
                                        <code title="<?= htmlspecialchars($r['sha256_hash']) ?>"><?= substr($r['sha256_hash'], 0, 16) ?>...</code>
                                    </td>
                                    <td>
                                        <?php if ($r['parse_status'] === 'Tampered'): ?>
                                            <span class="badge bg-danger">TAMPERED</span>
                                        <?php elseif ($r['parse_status'] === 'Parsed'): ?>
                                            <span class="badge bg-success">Parsed & Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($r['parse_status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/instruments/vault/<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Inspect
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
