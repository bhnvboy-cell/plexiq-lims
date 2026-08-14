<?php $title = 'Notification Settings'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-gear me-2"></i>Notification Settings</h4>
    <a href="/notifications" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Notifications</a>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-bell me-1"></i>Preferences</h6></div>
            <div class="card-body">
                <form method="POST" action="/notifications/settings">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <h6 class="fw-bold">Delivery Channels</h6>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="email_notifications" value="1" <?= !empty($settings['email_notifications']) ? 'checked' : '' ?> id="emailNotif">
                            <label class="form-check-label" for="emailNotif">Email notifications</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="browser_notifications" value="1" <?= !empty($settings['browser_notifications']) ? 'checked' : '' ?> id="browserNotif">
                            <label class="form-check-label" for="browserNotif">Browser notifications</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold">Digest</h6>
                        <select name="digest_frequency" class="form-select" style="max-width:300px;">
                            <option value="daily" <?= ($settings['digest_frequency'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= ($settings['digest_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="realtime" <?= ($settings['digest_frequency'] ?? '') === 'realtime' ? 'selected' : '' ?>>Real-time</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold">Notify Me When</h6>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="notify_on_sample_status" value="1" <?= !empty($settings['notify_on_sample_status']) ? 'checked' : '' ?> id="nsSample">
                            <label class="form-check-label" for="nsSample">A sample status changes</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="notify_on_result_entry" value="1" <?= !empty($settings['notify_on_result_entry']) ? 'checked' : '' ?> id="nsResult">
                            <label class="form-check-label" for="nsResult">A test result is entered</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="notify_on_certificate" value="1" <?= !empty($settings['notify_on_certificate']) ? 'checked' : '' ?> id="nsCert">
                            <label class="form-check-label" for="nsCert">A certificate is generated</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="notify_on_deviation" value="1" <?= !empty($settings['notify_on_deviation']) ? 'checked' : '' ?> id="nsDev">
                            <label class="form-check-label" for="nsDev">A deviation is reported</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold">Quiet Hours</h6>
                        <div class="row g-2" style="max-width:360px;">
                            <div class="col">
                                <label class="form-label small mb-1">From</label>
                                <input type="time" name="quiet_hours_start" class="form-control" value="<?= e($settings['quiet_hours_start'] ?? '') ?>">
                            </div>
                            <div class="col">
                                <label class="form-label small mb-1">To</label>
                                <input type="time" name="quiet_hours_end" class="form-control" value="<?= e($settings['quiet_hours_end'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="fetch('/notifications/test', {method:'POST', headers:{'X-CSRF-Token': document.querySelector('[name=_csrf_token]').value}}).then(()=>alert('Test notification logged.'))"><i class="bi bi-send"></i> Send Test</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
