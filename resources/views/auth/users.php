<?php $title = 'User Management'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-people"></i> Users</h2>
    <a href="/users/create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New User</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Last Login</th><th>Active</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge bg-<?= ['Admin'=>'danger','Analyst'=>'primary','Reviewer'=>'warning','Approver'=>'success','Customer'=>'info'][$u['role_name']] ?? 'secondary' ?>"><?= $u['role_name'] ?></span></td>
                    <td><?= $u['last_login'] ?? 'Never' ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td><a href="/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
