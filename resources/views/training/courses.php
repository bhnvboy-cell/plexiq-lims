<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-collection me-2"></i>Training Courses</h4>
        <span class="text-muted small"><?= count($courses) ?> course(s)</span>
    </div>
    <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
    <a href="/training/courses/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Course</a>
    <?php endif; ?>
</div>

<?php if (empty($courses)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-collection"></i>
        <h5>No Courses Defined</h5>
        <p class="text-muted">Create your first training course to get started.</p>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/training/courses/create" class="btn btn-primary mt-2"><i class="bi bi-plus-lg me-1"></i>New Course</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Duration (hrs)</th>
                    <th>Provider</th>
                    <th>Certification</th>
                    <th>Active</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                <tr>
                    <td><span class="fw-medium"><?= e($c['course_code']) ?></span></td>
                    <td><strong><?= e($c['course_name']) ?></strong></td>
                    <td><?= e($c['duration_hours'] ?? '-') ?></td>
                    <td><?= e($c['provider'] ?? '-') ?></td>
                    <td><?= $c['requires_certification'] ? '<span class="badge bg-warning bg-opacity-10 text-warning">Required</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">No</span>' ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td class="text-end">
                        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
                        <a href="/training/courses/<?= $c['id'] ?>/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require __DIR__ . '/../partials/pagination.php'; ?>
</div>
<?php endif; ?>
