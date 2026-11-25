<?php
layout('app');
$title = 'Clinical Diagnostics & Health Interoperability (FHIR R4 / HL7 v2)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Clinical Diagnostics & Health Interoperability</h1>
            <p class="text-muted mb-0">HL7 FHIR R4 JSON resources and HL7 v2.5.1 ORU^R01 standard diagnostic report generation</p>
        </div>
        <div>
            <span class="badge bg-primary p-2"><i class="bi bi-hospital"></i> HL7 FHIR R4 Compliant</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Clinical Sample Reports Ready for Export</span>
                    <span class="badge bg-secondary"><?= count($samples) ?> samples</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Sample Code</th>
                                    <th>Name / Specimen</th>
                                    <th>Status</th>
                                    <th>Export Formats</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($samples)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No clinical samples found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($samples as $s): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($s['sample_code']) ?></code></td>
                                            <td><?= htmlspecialchars($s['sample_name'] ?? 'Diagnostic Specimen') ?></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($s['status']) ?></span></td>
                                            <td>
                                                <a href="/clinical/fhir/report/<?= $s['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm me-1">
                                                    <i class="bi bi-filetype-json"></i> FHIR R4 Bundle
                                                </a>
                                                <a href="/clinical/hl7/<?= $s['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-file-earmark-text"></i> HL7 ORU^R01
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

        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Ingested / Stored FHIR Resources</span>
                    <span class="badge bg-secondary"><?= count($fhirResources) ?> entries</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Resource ID</th>
                                    <th>Sample</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($fhirResources)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">No FHIR resources stored yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fhirResources as $r): ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($r['resource_type']) ?></span></td>
                                            <td><code><?= htmlspecialchars($r['resource_id']) ?></code></td>
                                            <td><?= htmlspecialchars($r['sample_code'] ?? 'N/A') ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars($r['created_at']) ?></td>
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
</div>
