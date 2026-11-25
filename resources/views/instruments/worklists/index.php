<?php
$title = 'Autosampler Sequences & Instrument Worklists';
require __DIR__ . '/../../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Autosampler Sequences & Instrument Worklists</h1>
            <p class="text-muted mb-0">Bidirectional worklists for HPLC, GC, LC-MS (Waters Empower, Chromeleon, ASTM E1394)</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newWorklistModal">
                <i class="bi bi-list-ol"></i> Build Worklist Sequence
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">Generated Sequence Worklists</span>
            <span class="badge bg-secondary"><?= count($worklists) ?> sequences</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Sequence Code</th>
                            <th>Worklist Name</th>
                            <th>Instrument</th>
                            <th>Driver Format</th>
                            <th>Vials / Entries</th>
                            <th>Status</th>
                            <th>Export Stream</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($worklists)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No instrument worklists generated yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($worklists as $w): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($w['sequence_code']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($w['instrument_name']) ?> (<?= htmlspecialchars($w['instrument_code']) ?>)</td>
                                    <td><span class="badge bg-info text-dark text-uppercase"><?= htmlspecialchars($w['worklist_format']) ?></span></td>
                                    <td>
                                        <?php $entries = json_decode($w['entries'] ?? '[]', true); ?>
                                        <span class="badge bg-secondary"><?= count($entries) ?> vials</span>
                                    </td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($w['status']) ?></span></td>
                                    <td>
                                        <a href="/instruments/worklists/<?= $w['id'] ?>/export" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i> Download Sequence
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

<!-- Modal: New Worklist -->
<div class="modal fade" id="newWorklistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/instruments/worklists" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Build Autosampler Sequence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Target Instrument</label>
                        <select name="instrument_id" class="form-select" required>
                            <option value="">-- Select Instrument --</option>
                            <?php foreach ($instruments as $i): ?>
                                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['instrument_name']) ?> (<?= htmlspecialchars($i['instrument_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sequence Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Assay Batch Run HPLC-01">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Export Protocol Format</label>
                    <select name="worklist_format" class="form-select">
                        <option value="csv">CDS Delimited CSV (Waters Empower / Dionex Chromeleon)</option>
                        <option value="astm">ASTM E1394 Standard Clinical Protocol</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Autosampler Sequence Vials (JSON Table)</label>
                    <textarea name="entries" class="form-control font-monospace" rows="5">[
  {"position": 1, "sample_code": "BLANK", "sample_type": "Blank", "injection_volume_ul": 10.0},
  {"position": 2, "sample_code": "CAL-STD-1", "sample_type": "Standard", "injection_volume_ul": 10.0},
  {"position": 3, "sample_code": "QC-CONTROL", "sample_type": "QC", "injection_volume_ul": 10.0},
  {"position": 4, "sample_code": "SMP-BATCH-01", "sample_type": "Sample", "injection_volume_ul": 10.0}
]</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Sequence</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
