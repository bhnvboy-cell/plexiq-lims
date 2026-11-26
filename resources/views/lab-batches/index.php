<?php
layout('app');
$title = 'Lab Analytical Batches & Worklists';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-flask me-2"></i>Lab Analytical Batches</h1>
            <p class="text-muted mb-0">Instrument Sequences, Controls (Blank, Standard, QC), and Worklist Exporters (Empower, Chromeleon, OpenLab)</p>
        </div>
        <div>
            <a href="/lab-batches/create" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Lab Batch / Run
            </a>
            <a href="/production-lots" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-boxes me-1"></i> Production Lots
            </a>
        </div>
    </div>

    <!-- Batches Registry -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Analytical Worklist Batches</span>
            <span class="badge bg-secondary"><?= count($batches) ?> runs</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Batch / Run Code</th>
                            <th>Analysis / Test</th>
                            <th>Instrument</th>
                            <th>Analyst</th>
                            <th>Run Date</th>
                            <th>Sequence Composition</th>
                            <th>QC Status</th>
                            <th>Batch Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No analytical lab batches found. Click "New Lab Batch / Run" to create an instrument sequence.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td>
                                        <a href="/lab-batches/<?= $b['id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($b['batch_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><strong><?= htmlspecialchars($b['analysis_code'] ?? '-') ?></strong></div>
                                        <small class="text-muted"><?= htmlspecialchars($b['analysis_name'] ?? '') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($b['instrument_name'] ?? 'Unassigned') ?></td>
                                    <td><?= htmlspecialchars($b['analyst_name'] ?? '-') ?></td>
                                    <td><?= $b['run_date'] ?? '-' ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= $b['control_count'] ?> Control(s) &bull; <?= $b['sample_count'] ?> Sample(s)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($b['qc_status'] === 'Passed'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>QC Passed</span>
                                        <?php elseif ($b['qc_status'] === 'Failed'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>QC Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending QC</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match ($b['status']) {
                                            'QC_Passed' => 'bg-success',
                                            'QC_Failed' => 'bg-danger',
                                            'Prepared' => 'bg-info text-dark',
                                            'Running' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= $b['status'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="/lab-batches/<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Sequence Grid
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
