<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'PlexiQ LIMS' ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="256x256" href="/assets/images/plexiq-icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/lims.css" rel="stylesheet">
</head>
<body>
    <?php if (!empty($auth['check'])): ?>

    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>
                    <path d="M12 8v4"/>
                    <path d="M12 16h.01"/>
                </svg>
            </div>
            <div class="brand-text">
                <span class="brand-name">PlexiQ</span>
                <span class="brand-sub">LIMS</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="/dashboard" class="nav-item <?= ($_SERVER['REQUEST_URI'] ?? '') === '/dashboard' || ($_SERVER['REQUEST_URI'] ?? '') === '/' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            </a>
            <a href="/workspace" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/workspace') ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap-fill"></i><span>Workspace</span>
            </a>

            <div class="nav-section-label">Quality Control</div>
            <a href="/batches" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/batches') ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i><span>Batch Management</span>
            </a>
            <a href="/samples" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/samples') ? 'active' : '' ?>">
                <i class="bi bi-collection"></i><span>Samples</span>
            </a>
            <a href="/spc" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/spc') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-steps"></i><span>SPC Charts</span>
            </a>
            <a href="/tests/pending" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/tests/pending') ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data"></i><span>Results Entry</span>
            </a>
            <?php if (in_array($auth['role'], ['Reviewer', 'Approver', 'Admin'])): ?>
            <a href="/tests/review" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/tests/review') ? 'active' : '' ?>">
                <i class="bi bi-check-circle"></i><span>Review</span>
            </a>
            <?php endif; ?>
            <?php if (in_array($auth['role'], ['Approver', 'Admin'])): ?>
            <a href="/tests/final-approval" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/tests/final-approval') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/tests/final') ? 'active' : '' ?>">
                <i class="bi bi-check-all"></i><span>Final Approval</span>
            </a>
            <?php endif; ?>

            <div class="nav-section-label">Instruments</div>
            <a href="/instruments" class="nav-item <?= $_SERVER['REQUEST_URI'] === '/instruments' ? 'active' : '' ?>">
                <i class="bi bi-cpu"></i><span>Instruments</span>
            </a>
            <a href="/instruments/results" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/instruments/results') ? 'active' : '' ?>">
                <i class="bi bi-arrow-down-circle"></i><span>Imported Results</span>
            </a>

            <div class="nav-section-label">Quality Events</div>
            <a href="/oos" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/oos') ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i><span>OOS</span>
            </a>
            <a href="/capa" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/capa') ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i><span>CAPA</span>
            </a>

            <div class="nav-section-label">Documents</div>
            <a href="/coa" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/coa') ? 'active' : '' ?>">
                <i class="bi bi-file-text"></i><span>Certificate of Analysis</span>
            </a>

            <a href="/projects" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/projects') ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i><span>Projects</span>
            </a>

            <div class="nav-section-label">Lab Operations</div>

            <a href="/notebooks" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/notebooks') ? 'active' : '' ?>">
                <i class="bi bi-journal-richtext"></i><span>ELN Notebooks</span>
            </a>
            <a href="/stability" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/stability') ? 'active' : '' ?>">
                <i class="bi bi-flask"></i><span>Stability Studies</span>
            </a>
            <a href="/environmental" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/environmental') ? 'active' : '' ?>">
                <i class="bi bi-thermometer-half"></i><span>Env. Monitoring</span>
            </a>
            <a href="/calibrations" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/calibrations') ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i><span>Calibrations</span>
            </a>
            <a href="/deviations" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/deviations') ? 'active' : '' ?>">
                <i class="bi bi-exclamation-octagon"></i><span>Deviation</span>
            </a>
            <a href="/training" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/training') ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i><span>Training</span>
            </a>

            <div class="nav-section-label">Business</div>
            <a href="/suppliers" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/suppliers') ? 'active' : '' ?>">
                <i class="bi bi-truck"></i><span>Suppliers</span>
            </a>
            <a href="/billing" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/billing') ? 'active' : '' ?>">
                <i class="bi bi-currency-dollar"></i><span>Billing</span>
            </a>

            <?php if ($auth['role'] === 'Admin'): ?>
            <div class="nav-section-label">Administration</div>
            <div class="nav-sub">
                <a href="/master" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/master') ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i><span>Master Data</span>
                </a>
                <a href="/users" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/users') ? 'active' : '' ?>">
                    <i class="bi bi-people"></i><span>Users</span>
                </a>
                <a href="/audit" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/audit') ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i><span>Audit Trail</span>
                </a>
                <a href="/sap" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/sap') ? 'active' : '' ?>">
                    <i class="bi bi-cloud-arrow-up"></i><span>SAP Integration</span>
                </a>
                <a href="/installer/builder" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/installer') ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i><span>Installer Builder</span>
                </a>
                <div class="nav-section-label mt-2" style="font-size:0.6rem;">Integrations</div>
                <a href="/api-management/tokens" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api-management') ? 'active' : '' ?>">
                    <i class="bi bi-key"></i><span>API Tokens</span>
                </a>
                <a href="/sso" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/sso') ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock"></i><span>SSO / LDAP</span>
                </a>
                <a href="/plugins" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/plugins') ? 'active' : '' ?>">
                    <i class="bi bi-puzzle"></i><span>Plugins</span>
                </a>
                <div class="nav-section-label mt-2" style="font-size:0.6rem;">System</div>
                <a href="/languages" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/languages') ? 'active' : '' ?>">
                    <i class="bi bi-globe"></i><span>Languages</span>
                </a>
                <a href="/compliance" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/compliance') ? 'active' : '' ?>">
                    <i class="bi bi-shield-check"></i><span>Compliance</span>
                </a>
                <a href="/bi" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/bi') ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i><span>BI Analytics</span>
                </a>
                <a href="/dashboard/customize" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/dashboard/customize') ? 'active' : '' ?>">
                    <i class="bi bi-layout-wtf"></i><span>Dashboard Config</span>
                </a>
                <a href="/deployment" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/deployment') ? 'active' : '' ?>">
                    <i class="bi bi-cloud"></i><span>Cloud Settings</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($auth['user']['full_name'] ?? $auth['user']['username'] ?? 'U', 0, 2)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($auth['user']['full_name'] ?? $auth['user']['username'] ?? 'User') ?></div>
                    <div class="user-role"><?= $auth['role'] ?></div>
                </div>
                <a href="/logout" class="logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </aside>

    <div class="main-wrapper" id="mainWrapper">
        <header class="top-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('mainWrapper').classList.toggle('expanded')">
                    <i class="bi bi-list"></i>
                </button>
                <button class="mobile-toggle" id="mobileToggle" onclick="toggleMobileSidebar()" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <div class="header-breadcrumb">
                    <span class="breadcrumb-item">PlexiQ</span>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-item active"><?= $title ?? 'Dashboard' ?></span>
                </div>
            </div>
            <div class="header-right">
                <a href="/notifications" class="header-btn position-relative" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="font-size:0.55rem;display:none;">0</span>
                </a>
                <a href="/profile" class="header-btn" title="Profile"><i class="bi bi-person"></i></a>
                <a href="/logout" class="header-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </header>

        <main class="content">
            <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($flash['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($flash['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($flash['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($flash['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show"><?= htmlspecialchars($flash['warning']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (!empty($flash['info'])): ?>
            <div class="alert alert-info alert-dismissible fade show"><?= htmlspecialchars($flash['info']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>
    </div>

    <?php else: ?>

    <div class="content"><?= $content ?? '' ?></div>

    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/lims.js"></script>
    <script>
    // Notification poller
    (function() {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;
        function checkNotifs() {
            fetch('/api/notifications/unread')
                .then(r => r.json())
                .then(d => {
                    if (d.unread_count > 0) {
                        badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count;
                        badge.style.display = '';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }
        checkNotifs();
        setInterval(checkNotifs, 30000);
    })();
    </script>
</body>
</html>
