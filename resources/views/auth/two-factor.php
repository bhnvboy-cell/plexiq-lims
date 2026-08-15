<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification - PlexiQ LIMS</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/lims.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="card">
            <div class="login-header">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5Zm-3 8V6a3 3 0 1 1 6 0v3H9Zm3 4a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0v-3a1 1 0 0 1 1-1Z"/>
                    </svg>
                </div>
                <h3>Two-Factor Verification</h3>
                <p>Enter the code from your authenticator app</p>
            </div>
            <div class="login-body">
                <?php $error = session_flash('error'); ?>
                <?php $info = session_flash('info'); ?>
                <?php if ($info): ?><div class="alert alert-info py-2"><?= htmlspecialchars($info) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" action="/login/2fa">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-shield-lock me-1"></i>Verification Code</label>
                        <input type="text" name="code" class="form-control form-control-lg" placeholder="6-digit code" autocomplete="one-time-code" inputmode="numeric" maxlength="8" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-shield-check me-1"></i>Verify
                    </button>
                </form>
                <form method="POST" action="/login/2fa/cancel" class="text-center mt-3">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-link text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to login
                    </button>
                </form>
            </div>
            <div class="card-footer text-center text-muted small py-3 bg-white border-top">
                &copy; <?= date('Y') ?> PlexiQ LIMS v1.0
            </div>
        </div>
    </div>
</body>
</html>
