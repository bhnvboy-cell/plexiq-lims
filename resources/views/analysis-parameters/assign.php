<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-clipboard2-plus me-2"></i>Assign Parameters &mdash; <?= e($sample['sample_code']) ?></h4>
    <a href="/samples/<?= $sample['id'] ?>/parameters/entries" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>View Results</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/samples/<?= $sample['id'] ?>/parameters">
            <?= csrf_field() ?>
            <div class="mb-3 text-muted small">
                Check the parameters to be tested for sample <strong><?= e($sample['sample_code']) ?></strong>
                (batch <strong><?= e($sample['batch_number'] ?? '-') ?></strong>). Spec limits are snapshotted from the parameter master.
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th style="width:40px;"></th><th>Code</th><th>Name</th><th>Unit</th><th>Specification</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parameters as $p): ?>
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" name="parameter_ids[]" value="<?= $p['id'] ?>"
                                       <?= in_array($p['id'], $assignedIds) ? 'checked' : '' ?>>
                            </td>
                            <td><span class="fw-medium"><?= e($p['parameter_code']) ?></span></td>
                            <td><?= e($p['parameter_name']) ?></td>
                            <td><?= e($p['unit'] ?? '-') ?></td>
                            <td><small><?= e($p['specification_text'] ?? (($p['spec_min'] ?? '') . ' - ' . ($p['spec_max'] ?? ''))) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($parameters)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No active parameters. Create one under Analysis Parameters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Assignment</button>
            </div>
        </form>
    </div>
</div>
