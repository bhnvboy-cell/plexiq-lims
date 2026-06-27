<?php $title = 'Deployment & Cloud Settings'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-cloud me-2"></i>Deployment & Cloud Settings</h4>
    <div>
        <span class="badge bg-<?= deployment_config('cloud_enabled') === 'true' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= deployment_config('cloud_enabled') === 'true' ? 'success' : 'secondary' ?> px-3 py-2 me-2">
            <i class="bi bi-<?= deployment_config('cloud_enabled') === 'true' ? 'cloud-check' : 'cloud-slash' ?> me-1"></i>
            Cloud: <?= deployment_config('cloud_enabled') === 'true' ? 'Enabled' : 'Disabled' ?>
        </span>
        <a href="/deployment/toggle-mode" class="btn btn-outline-<?= deployment_config('cloud_enabled') === 'true' ? 'danger' : 'success' ?> btn-sm">
            <i class="bi bi-<?= deployment_config('cloud_enabled') === 'true' ? 'cloud-slash' : 'cloud-arrow-up' ?>"></i>
            <?= deployment_config('cloud_enabled') === 'true' ? 'Disable' : 'Enable' ?> Cloud Mode
        </a>
    </div>
</div>

<form method="POST" action="/deployment">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($grouped as $category => $settings): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear me-1"></i>
                    <?= ucwords(str_replace('_', ' ', $category)) ?> Settings
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($settings as $s): ?>
                        <?php
                            $isBool = in_array($s['setting_value'], ['true', 'false']);
                            $isNum = is_numeric($s['setting_value']);
                        ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold"><?= ucwords(str_replace('_', ' ', $s['setting_key'])) ?></label>
                            <?php if ($isBool): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="settings[<?= e($s['setting_key']) ?>]" value="true" <?= $s['setting_value'] === 'true' ? 'checked' : '' ?>>
                                <input type="hidden" name="settings[<?= e($s['setting_key']) ?>]" value="false">
                            </div>
                            <?php elseif ($isNum && (int)$s['setting_value'] > 10): ?>
                            <input type="number" class="form-control" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>">
                            <?php else: ?>
                            <input type="text" class="form-control" name="settings[<?= e($s['setting_key']) ?>]" value="<?= e($s['setting_value']) ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
    </div>
</form>

<!-- Cloud Mode Info -->
<?php if (deployment_config('cloud_enabled') === 'true'): ?>
<div class="alert alert-info mt-4">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-cloud-check fs-4"></i>
        <div>
            <strong>Cloud Mode Active</strong><br>
            <small>API endpoints are accessible at <code>/api/</code>. Mobile app connections are enabled.
            Configure rate limiting and token expiration in the settings above.</small>
        </div>
    </div>
</div>
<?php endif; ?>
