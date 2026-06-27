<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-book me-2"></i>Training Management</h4>
        <span class="text-muted small">Training overview and assignments</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/training/courses" class="btn btn-outline-primary btn-sm"><i class="bi bi-collection me-1"></i>Courses</a>
        <a href="/training/assignments" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-check me-1"></i>Assignments</a>
        <?php if (in_array($auth['role'], ['Admin', 'Analyst'])): ?>
        <a href="/training/courses/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Course</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stats-card stats-card-blue">
            <i class="bi bi-collection stat-icon"></i>
            <div class="stat-value"><?= $stats['total_courses'] ?? 0 ?></div>
            <div class="stat-label">Total Courses</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-orange">
            <i class="bi bi-person-check stat-icon"></i>
            <div class="stat-value"><?= $stats['active_assignments'] ?? 0 ?></div>
            <div class="stat-label">Active Assignments</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-green">
            <i class="bi bi-check-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['completed'] ?? 0 ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card stats-card-red">
            <i class="bi bi-clock stat-icon"></i>
            <div class="stat-value"><?= $stats['overdue'] ?? 0 ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book me-1"></i>Courses</span>
                <a href="/training/courses" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Code</th><th>Course Name</th><th>Duration</th><th>Certification</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><span class="fw-medium"><?= e($c['course_code']) ?></span></td>
                            <td><a href="/training/courses/<?= $c['id'] ?>/edit" class="text-decoration-none"><?= e($c['course_name']) ?></a></td>
                            <td><?= e($c['duration_hours'] ?? '-') ?>h</td>
                            <td><?= $c['requires_certification'] ? '<span class="badge bg-warning bg-opacity-10 text-warning">Required</span>' : '<span class="badge bg-secondary bg-opacity-10 text-secondary">No</span>' ?></td>
                            <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No courses defined yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-check me-1"></i>Recent Assignments</span>
                <a href="/training/assignments" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Employee</th><th>Course</th><th>Status</th><th>Due</th></tr></thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td><?= e($a['user_name'] ?? 'N/A') ?></td>
                            <td><small><?= e($a['course_name'] ?? 'N/A') ?></small></td>
                            <td><?php $sbadge = match ($a['status']) { 'Assigned'=>'secondary', 'In Progress'=>'info', 'Completed'=>'success', 'Overdue'=>'danger', default=>'secondary' }; ?>
                                <span class="badge bg-<?= $sbadge ?>"><?= e($a['status']) ?></span>
                            </td>
                            <td><small class="text-muted"><?= e($a['due_date'] ?? '-') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assignments)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No assignments yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
