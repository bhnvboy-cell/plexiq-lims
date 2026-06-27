<?php $title = $entry ? 'Edit Entry' : 'New Entry'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-file-earmark-text me-2"></i><?= $entry ? 'Edit' : 'New' ?> Entry</h4>
    <a href="/notebooks/<?= $notebook['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Notebook</a>
</div>
<div class="row justify-content-center">
<div class="col-lg-10">
<div class="card shadow-sm">
<div class="card-header">
    <h5 class="mb-0"><?= e($notebook['notebook_name'] ?? 'Notebook') ?> — <?= $entry ? 'Edit Entry' : 'New Entry' ?></h5>
</div>
<div class="card-body">
<form method="POST" action="<?= $entry ? '/entries/' . $entry['id'] . '/update' : '/notebooks/' . ($notebook['id'] ?? '') . '/entries' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="<?= e($entry['title'] ?? '') ?>" placeholder="Entry title">
        </div>
        <div class="col-md-4">
            <label class="form-label">Entry Type</label>
            <select name="entry_type" class="form-select">
                <option value="General" <?= ($entry['entry_type'] ?? '') === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Protocol" <?= ($entry['entry_type'] ?? '') === 'Protocol' ? 'selected' : '' ?>>Protocol</option>
                <option value="Observation" <?= ($entry['entry_type'] ?? '') === 'Observation' ? 'selected' : '' ?>>Observation</option>
                <option value="Result" <?= ($entry['entry_type'] ?? '') === 'Result' ? 'selected' : '' ?>>Result</option>
                <option value="Conclusion" <?= ($entry['entry_type'] ?? '') === 'Conclusion' ? 'selected' : '' ?>>Conclusion</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Content <span class="text-danger">*</span></label>
            <textarea name="content" class="form-control" rows="12" required placeholder="Write your entry content here..."><?= e($entry['content'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Tags</label>
            <input type="text" name="tags" class="form-control" value="<?= e($entry['tags'] ?? '') ?>" placeholder="Comma-separated tags">
            <div class="form-text">e.g. HPLC, Stability, Batch-001</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Draft" <?= ($entry['status'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Submitted" <?= ($entry['status'] ?? '') === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="Reviewed" <?= ($entry['status'] ?? '') === 'Reviewed' ? 'selected' : '' ?>>Reviewed</option>
                <option value="Approved" <?= ($entry['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">File Attachment</label>
            <input type="file" name="attachment" class="form-control">
            <div class="form-text">PDF, Word, Excel, or image files. Max 10 MB.</div>
            <?php if (!empty($entry['attachment'])): ?>
            <div class="mt-2">
                <span class="badge bg-info bg-opacity-10 text-info"><i class="bi bi-paperclip me-1"></i><?= e(basename($entry['attachment'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $entry ? 'Update Entry' : 'Create Entry' ?></button>
        <a href="/notebooks/<?= $notebook['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div></div></div>
