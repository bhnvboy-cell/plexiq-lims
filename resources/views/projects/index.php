<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Projects</h4>
    <div>
        <span class="badge bg-secondary me-2"><?= count($projects) ?> total</span>
        <?php if ($auth['role'] === 'Admin'): ?>
        <a href="/projects/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Project</a>
        <?php endif; ?>
    </div>
</div>
<?php if (empty($projects)): ?>
<div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-folder2-open display-4 d-block mb-3"></i>No projects found.</div></div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($projects as $p): ?>
<div class="col-md-6 col-lg-4">
<div class="card shadow-sm h-100">
<div class="card-body">
<div class="d-flex justify-content-between align-items-start mb-2">
<h5 class="card-title mb-0"><?= e($p['project_name']) ?></h5>
<?php
$badge = match ($p['status']) {
    'Active' => 'success', 'Completed' => 'primary', 'On Hold' => 'warning', 'Cancelled' => 'danger', default => 'secondary'
};
?>
<span class="badge bg-<?= $badge ?>"><?= e($p['status']) ?></span>
</div>
<h6 class="card-subtitle text-muted mb-2"><?= e($p['project_code']) ?></h6>
<p class="card-text small"><?= e(mb_substr($p['description'] ?? 'No description', 0, 120)) ?></p>
<div class="d-flex justify-content-between align-items-center mt-auto">
<small class="text-muted"><i class="bi bi-flag me-1"></i><?= e($p['priority']) ?></small>
<a href="/projects/<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm">View</a>
</div>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
