<?php
layout('app');
$title = 'Create Lab Analytical Batch';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-flask me-2"></i>New Lab Analytical Batch</h1>
            <p class="text-muted mb-0">Prepare an analytical sequence run with controls and specimens for instrument analysis.</p>
        </div>
        <a href="/lab-batches" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-gear me-1"></i> Run Parameters
                </div>
                <div class="card-body">
                    <form method="POST" action="/lab-batches">
                        <?= csrf_field() ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Batch Code (Leave blank for auto-generation)</label>
                                <input type="text" name="batch_code" class="form-control" placeholder="e.g. RUN-202611-HPLC01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Run Date *</label>
                                <input type="date" name="run_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Analysis (Method) *</label>
                            <select name="analysis_id" class="form-select" required>
                                <option value="">-- Select Analysis --</option>
                                <?php foreach ($analyses as $ana): ?>
                                    <option value="<?= $ana['id'] ?>"><?= htmlspecialchars($ana['analysis_code']) ?> - <?= htmlspecialchars($ana['analysis_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Instrument *</label>
                                <select name="instrument_id" class="form-select" required>
                                    <option value="">-- Select Calibrated Instrument --</option>
                                    <?php foreach ($instruments as $inst): ?>
                                        <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['instrument_name']) ?> (<?= htmlspecialchars($inst['instrument_code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Analyst *</label>
                                <select name="analyst_id" class="form-select" required>
                                    <?php foreach ($analysts as $an): ?>
                                        <option value="<?= $an['id'] ?>" <?= $an['id'] === ($auth['user']['id'] ?? 1) ? 'selected' : '' ?>><?= htmlspecialchars($an['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Worklist Exporter Target</label>
                            <select name="worklist_format" class="form-select">
                                <option value="empower_csv" selected>Waters Empower Sample Set (.csv)</option>
                                <option value="chromeleon_csv">Thermo Scientific Chromeleon Sequence (.csv)</option>
                                <option value="openlab_csv">Agilent OpenLab CDS Sequence (.csv)</option>
                                <option value="labsolutions_txt">Shimadzu LabSolutions Batch Table (.txt)</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-1"></i> Create Analytical Batch</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
