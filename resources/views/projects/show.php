<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= e($project['project_name']) ?></h4>
    <div>
        <a href="/projects" class="btn btn-outline-secondary btn-sm me-1"><i class="bi bi-arrow-left"></i> Back</a>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/projects/<?= $project['id'] ?>/edit" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
    </div>
</div>
<div class="row g-3 mb-4">
<div class="col-md-8">
<div class="card shadow-sm">
<div class="card-header bg-white"><strong>Details</strong></div>
<div class="card-body">
<div class="row mb-2"><div class="col-4 text-muted">Code</div><div class="col-8"><?= e($project['project_code']) ?></div></div>
<div class="row mb-2"><div class="col-4 text-muted">Status</div><div class="col-8"><?php
$badge = match ($project['status']) { 'Active'=>'success', 'Completed'=>'primary', 'On Hold'=>'warning', 'Cancelled'=>'danger', default=>'secondary' };
echo "<span class=\"badge bg-{$badge}\">" . e($project['status']) . '</span>';
?></div></div>
<div class="row mb-2"><div class="col-4 text-muted">Priority</div><div class="col-8"><?php
$pbadge = match ($project['priority']) { 'Critical'=>'danger', 'High'=>'warning', 'Medium'=>'info', 'Low'=>'secondary', default=>'secondary' };
echo "<span class=\"badge bg-{$pbadge}\">" . e($project['priority']) . '</span>';
?></div></div>
<div class="row mb-2"><div class="col-4 text-muted">Start Date</div><div class="col-8"><?= e($project['start_date'] ?? '-') ?></div></div>
<div class="row mb-2"><div class="col-4 text-muted">Target End</div><div class="col-8"><?= e($project['target_end_date'] ?? '-') ?></div></div>
<div class="row mb-2"><div class="col-4 text-muted">Actual End</div><div class="col-8"><?= e($project['actual_end_date'] ?? '-') ?></div></div>
<div class="row mb-0"><div class="col-4 text-muted">Description</div><div class="col-8"><?= e($project['description'] ?? 'No description') ?></div></div>
</div>
</div>
</div>
<div class="col-md-4">
<div class="card shadow-sm mb-3">
<div class="card-header bg-white"><strong>Assigned Samples</strong> <span class="badge bg-secondary"><?= count($project['samples']) ?></span></div>
<ul class="list-group list-group-flush">
<?php if (empty($project['samples'])): ?>
<li class="list-group-item text-muted small">No samples assigned.</li>
<?php else: ?>
<?php foreach ($project['samples'] as $s): ?>
<li class="list-group-item d-flex justify-content-between align-items-center">
<a href="/samples/<?= $s['id'] ?>"><?= e($s['sample_code']) ?></a>
<?php if ($auth['role'] === 'Admin'): ?>
<form method="POST" action="/projects/<?= $project['id'] ?>/samples/<?= $s['id'] ?>/remove" class="d-inline" onsubmit="return confirm('Remove sample?')">
<input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
<button class="btn btn-sm btn-outline-danger py-0 px-1">&times;</button>
</form>
<?php endif; ?>
</li>
<?php endforeach; ?>
<?php endif; ?>
</ul>
</div>
<?php if ($auth['role'] === 'Admin'): ?>
<div class="card shadow-sm">
<div class="card-header bg-white"><strong>Add Sample</strong></div>
<div class="card-body">
<form method="POST" action="/projects/<?= $project['id'] ?>/samples">
<input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
<div class="mb-2"><input name="sample_id" class="form-control form-control-sm" placeholder="Sample ID" type="number" required></div>
<div class="mb-2"><input name="notes" class="form-control form-control-sm" placeholder="Notes (optional)"></div>
<button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus"></i> Add</button>
</form>
</div>
</div>
<?php endif; ?>
</div>
</div>
