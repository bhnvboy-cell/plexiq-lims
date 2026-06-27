<?php $title = 'Profile'; ob_start(); ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-circle"></i> User Profile</div>
            <div class="card-body">
                <table class="table">
                    <tr><th>Username</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
                    <tr><th>Full Name</th><td><?= htmlspecialchars($user['full_name']) ?></td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
                    <tr><th>Role</th><td><span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span></td></tr>
                    <tr><th>Last Login</th><td><?= $user['last_login'] ?? 'Never' ?></td></tr>
                    <tr><th>Member Since</th><td><?= $user['created_at'] ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
