<?php $title = $user ? 'Edit User' : 'New User'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= $user ? '<i class="bi bi-pencil"></i> Edit User' : '<i class="bi bi-person-plus"></i> New User' ?></h2>
    <a href="/users" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<form method="POST" action="/users<?= $user ? '/' . $user['id'] : '' ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role *</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($user['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $user ? 'New Password (leave blank to keep)' : 'Password *' ?></label>
                    <input type="password" name="password" class="form-control" <?= $user ? '' : 'required' ?>>
                    <small class="text-muted">Default: welcome123</small>
                </div>
                <?php if ($user): ?>
                <div class="col-md-6 mb-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= $user ? 'Update' : 'Create' ?> User</button>
        </div>
    </div>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
