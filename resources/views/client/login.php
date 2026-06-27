<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - PlexiQ LIMS</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="256x256" href="/assets/images/plexiq-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/lims.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="card">
            <div class="login-header" style="background: linear-gradient(135deg, #11998e, #0a8a7a);">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>
                        <path d="M12 8v4"/>
                        <path d="M12 16h.01"/>
                    </svg>
                </div>
                <h3>Client Portal</h3>
                <p>View your Certificate of Analysis</p>
            </div>
            <div class="login-body">
                <?php $error = session_flash('error'); ?>
                <?php $info = session_flash('info'); ?>
                <?php $success = session_flash('success'); ?>
                <?php if ($info): ?><div class="alert alert-info py-2"><?= htmlspecialchars($info) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" action="/client/login">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person me-1"></i>Username</label>
                        <input type="text" name="username" class="form-control form-control-lg" placeholder="Your username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
                        <input type="password" name="password" class="form-control form-control-lg" placeholder="Your password" required>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                    </button>
                </form>
                <p class="text-center mt-3 mb-0">
                    <a href="/client/register" class="text-decoration-none"><i class="bi bi-person-plus me-1"></i>New customer? Register here</a>
                </p>
                <p class="text-center mt-2">
                    <a href="/login" class="text-decoration-none small text-muted"><i class="bi bi-shield-lock me-1"></i>Staff Login</a>
                </p>
            </div>
            <div class="card-footer text-center text-muted small py-3 bg-white border-top">
                &copy; <?= date('Y') ?> PlexiQ LIMS v1.0
            </div>
        </div>
    </div>
</body>
</html>
