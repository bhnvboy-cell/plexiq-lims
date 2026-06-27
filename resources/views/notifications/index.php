<?php $title = 'Notifications'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-bell me-2"></i>Notifications</h4>
    <div>
        <button class="btn btn-sm btn-outline-primary" onclick="markAllRead()"><i class="bi bi-check-all"></i> Mark All Read</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-funnel me-1"></i>Filter</h6></div>
            <div class="list-group list-group-flush">
                <a href="?type=all" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($filterType ?? 'all') === 'all' ? 'active' : '' ?>">
                    <span><i class="bi bi-envelope me-2"></i>All</span>
                    <span class="badge bg-secondary rounded-pill"><?= $counts['all'] ?? 0 ?></span>
                </a>
                <a href="?type=unread" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($filterType ?? '') === 'unread' ? 'active' : '' ?>">
                    <span><i class="bi bi-envelope-open me-2"></i>Unread</span>
                    <span class="badge bg-primary rounded-pill"><?= $counts['unread'] ?? 0 ?></span>
                </a>
                <a href="?type=alert" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($filterType ?? '') === 'alert' ? 'active' : '' ?>">
                    <span><i class="bi bi-exclamation-triangle me-2"></i>Alerts</span>
                    <span class="badge bg-danger rounded-pill"><?= $counts['alert'] ?? 0 ?></span>
                </a>
                <a href="?type=approval" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($filterType ?? '') === 'approval' ? 'active' : '' ?>">
                    <span><i class="bi bi-check-circle me-2"></i>Approvals</span>
                    <span class="badge bg-warning rounded-pill"><?= $counts['approval'] ?? 0 ?></span>
                </a>
                <a href="?type=system" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($filterType ?? '') === 'system' ? 'active' : '' ?>">
                    <span><i class="bi bi-gear me-2"></i>System</span>
                    <span class="badge bg-info rounded-pill"><?= $counts['system'] ?? 0 ?></span>
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <?php if (($filterType ?? 'all') === 'all'): ?><i class="bi bi-envelope me-1"></i>All Notifications
                    <?php elseif (($filterType ?? '') === 'unread'): ?><i class="bi bi-envelope-open me-1"></i>Unread
                    <?php elseif (($filterType ?? '') === 'alert'): ?><i class="bi bi-exclamation-triangle me-1"></i>Alerts
                    <?php elseif (($filterType ?? '') === 'approval'): ?><i class="bi bi-check-circle me-1"></i>Approvals
                    <?php else: ?><i class="bi bi-gear me-1"></i>System<?php endif; ?>
                </h6>
                <small class="text-muted"><?= count($notifications) ?> notifications</small>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-bell-slash display-5 d-block mb-2"></i>
                    <p>No notifications found.</p>
                </div>
                <?php else: foreach ($notifications as $n): ?>
                <div class="list-group-item list-group-item-action <?= empty($n['read_at']) ? 'fw-bold bg-light' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex gap-2">
                            <div class="mt-1">
                                <?php $icons = ['alert'=>'bi-exclamation-triangle text-danger','approval'=>'bi-check-circle text-warning','system'=>'bi-gear text-info','info'=>'bi-info-circle text-primary']; ?>
                                <i class="bi <?= $icons[$n['type']] ?? 'bi-bell text-secondary' ?> fs-5"></i>
                            </div>
                            <div>
                                <div class="mb-1"><?= e($n['message']) ?></div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i><?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                                    <?php if (!empty($n['related_entity'])): ?>
                                    &middot; <i class="bi bi-link-45deg me-1"></i><?= e($n['related_entity']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if (!empty($n['action_url'])): ?>
                            <a href="<?= e($n['action_url']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <?php endif; ?>
                            <?php if (empty($n['read_at'])): ?>
                            <form method="POST" action="/notifications/<?= $n['id'] ?>/read" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-success" title="Mark read"><i class="bi bi-check"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function markAllRead() {
    if (!confirm('Mark all notifications as read?')) return;
    fetch('/notifications/mark-all-read', { method: 'POST', headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' } })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); })
        .catch(() => alert('Failed to mark all as read.'));
}
</script>
