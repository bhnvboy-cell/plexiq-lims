<?php
layout('app');
$title = 'AI Quality Advisor (Ishikawa 6M & 5-Whys)';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">AI Quality Advisor & Root Cause Diagnostics</h1>
            <p class="text-muted mb-0">Automated Ishikawa 6M root cause classification, 5-Whys diagnostic chains & human-in-the-loop CAPA drafting</p>
        </div>
        <div>
            <span class="badge bg-primary p-2"><i class="bi bi-robot"></i> 21 CFR Part 11 Human Sign-Off Enforced</span>
        </div>
    </div>

    <!-- Live Analysis Request Form -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white fw-bold">Incident AI Diagnostics Launcher</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Incident Type</label>
                    <select id="incidentType" class="form-select">
                        <option value="oos">Out of Specification (OOS)</option>
                        <option value="deviation">Planned / Unplanned Deviation</option>
                        <option value="complaint">Customer Quality Complaint</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Test / Method Name</label>
                    <input type="text" id="incidentTest" class="form-control" placeholder="e.g. Assay HPLC, Endotoxin LAL" value="Assay HPLC Finished Product">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Problem Narrative & Observation</label>
                    <input type="text" id="incidentDesc" class="form-control" placeholder="e.g. Column pressure spike observed, retention time drifted" value="High system backpressure spike and retention time peak drifted beyond tolerance">
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary" onclick="runAiAnalysis()">
                        <i class="bi bi-lightning-charge"></i> Run Ishikawa & 5-Whys Diagnostic
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Suggestions List -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold">AI Diagnostics & Human Review Log</span>
            <span class="badge bg-secondary"><?= count($suggestions) ?> suggestions</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Entity</th>
                            <th>Ishikawa 6M</th>
                            <th>Root Cause Hypothesis</th>
                            <th>Confidence</th>
                            <th>Review Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suggestions)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No quality suggestions logged yet. Use the launcher above to analyze an incident.</td></tr>
                        <?php else: ?>
                            <?php foreach ($suggestions as $s): ?>
                                <tr>
                                    <td>#<?= (int)$s['id'] ?></td>
                                    <td><span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($s['entity_type']) ?> #<?= (int)$s['entity_id'] ?></span></td>
                                    <td><span class="badge bg-info text-dark text-capitalize"><?= htmlspecialchars($s['ishikawa_category']) ?></span></td>
                                    <td><?= htmlspecialchars($s['root_cause_hypothesis']) ?></td>
                                    <td><strong><?= round((float)$s['confidence_score'] * 100) ?>%</strong></td>
                                    <td>
                                        <span class="badge <?= $s['review_status'] === 'accepted' ? 'bg-success' : ($s['review_status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                            <?= htmlspecialchars($s['review_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($s['review_status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="reviewSuggestion(<?= $s['id'] ?>, 'accepted')">Accept</button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="reviewSuggestion(<?= $s['id'] ?>, 'rejected')">Reject</button>
                                        <?php else: ?>
                                            <span class="small text-muted">Reviewed</span>
                                        <?php endif; ?>
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

<script>
function runAiAnalysis() {
    const type = document.getElementById('incidentType').value;
    const test = document.getElementById('incidentTest').value;
    const desc = document.getElementById('incidentDesc').value;

    fetch('/quality/ai-advisor/analyze', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `entity_type=${type}&entity_id=1&test_name=${encodeURIComponent(test)}&description=${encodeURIComponent(desc)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Analysis complete! Ishikawa Category: ' + d.analysis.ishikawa_category + '\nHypothesis: ' + d.analysis.root_cause_hypothesis);
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    });
}

function reviewSuggestion(id, decision) {
    fetch(`/quality/ai-advisor/review/${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `decision=${decision}&notes=Reviewed via quality web advisor`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    });
}
</script>
