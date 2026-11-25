<?php
layout('app');
$title = 'Supply Chain Security & Software Bill of Materials (SBOM)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Supply Chain Security & SBOM</h1>
            <p class="text-muted mb-0">CycloneDX 1.5 JSON Software Bill of Materials, dependency provenance & NIST SP 800-218 SSDF compliance</p>
        </div>
        <div>
            <a href="/api/ops/sbom" target="_blank" class="btn btn-primary">
                <i class="bi bi-filetype-json"></i> Export CycloneDX 1.5 JSON
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Dependencies</div>
                    <h3 class="mb-0 fw-bold"><?= (int)$summary['total_dependencies'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Known Vulnerabilities (CVEs)</div>
                    <h3 class="mb-0 fw-bold text-success"><?= (int)$summary['vulnerabilities_found'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Supply Chain Assurance</div>
                    <div class="fw-bold mt-1 text-truncate text-info"><?= htmlspecialchars($summary['supply_chain_status']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependency Components Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">CycloneDX Components (Packages & Libraries)</span>
            <span class="badge bg-secondary"><?= count($bom['components'] ?? []) ?> components</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Package Name</th>
                            <th>Version</th>
                            <th>PURL Identifier</th>
                            <th>Licenses</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bom['components'])): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No external dependencies discovered in lockfile.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bom['components'] as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($c['version']) ?></code></td>
                                    <td><code class="small text-muted"><?= htmlspecialchars($c['purl']) ?></code></td>
                                    <td>
                                        <?php if (!empty($c['licenses'])): ?>
                                            <?php foreach ($c['licenses'] as $lic): ?>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($lic['license']['id'] ?? 'Proprietary') ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted text-truncate" style="max-width: 300px;"><?= htmlspecialchars($c['description'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
