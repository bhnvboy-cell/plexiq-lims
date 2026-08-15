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

        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-shield-lock"></i> Two-Factor Authentication
                <span class="badge <?= !empty($user['totp_enabled']) ? 'bg-success' : 'bg-secondary' ?> float-end">
                    <?= !empty($user['totp_enabled']) ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Two-factor authentication adds an extra layer of security by requiring a code from your
                    authenticator app (e.g. Google Authenticator, Microsoft Authenticator) at sign-in.
                </p>
                <?php if (!empty($user['totp_enabled'])): ?>
                    <form method="POST" action="/profile/2fa/disable" class="d-flex gap-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="password" name="password" class="form-control" placeholder="Confirm password" required>
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-shield-x"></i> Disable 2FA</button>
                    </form>
                <?php else: ?>
                    <a href="/profile/2fa/setup" class="btn btn-primary"><i class="bi bi-shield-plus"></i> Enable 2FA</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
