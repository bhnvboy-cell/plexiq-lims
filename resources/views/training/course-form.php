<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $course ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $course ? 'Edit Course' : 'New Course' ?></h4>
    <a href="/training/courses" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $course ? '/training/courses/' . $course['id'] : '/training/courses' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">Course Code <span class="text-danger">*</span></label>
                    <input name="course_code" class="form-control" required value="<?= e($course['course_code'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Course Name <span class="text-danger">*</span></label>
                    <input name="course_name" class="form-control" required value="<?= e($course['course_name'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($course['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course Type</label>
                    <select name="course_type" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Internal" <?= ($course['course_type'] ?? '') === 'Internal' ? 'selected' : '' ?>>Internal</option>
                        <option value="External" <?= ($course['course_type'] ?? '') === 'External' ? 'selected' : '' ?>>External</option>
                        <option value="Online" <?= ($course['course_type'] ?? '') === 'Online' ? 'selected' : '' ?>>Online</option>
                        <option value="Certification" <?= ($course['course_type'] ?? '') === 'Certification' ? 'selected' : '' ?>>Certification</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Duration (hours)</label>
                    <input name="duration_hours" type="number" step="0.5" class="form-control" value="<?= e($course['duration_hours'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Provider</label>
                    <input name="provider" class="form-control" value="<?= e($course['provider'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Validity (days)</label>
                    <input name="validity_days" type="number" class="form-control" value="<?= e($course['validity_days'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requires_certification" id="requires_certification" value="1" <?= ($course['requires_certification'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="requires_certification">Requires Certification</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= ($course['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $course ? 'Update' : 'Create' ?> Course</button>
            </div>
        </form>
    </div>
</div>
