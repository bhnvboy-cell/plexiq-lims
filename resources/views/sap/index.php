<?php $title = 'SAP HANA Integration'; ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-cloud-arrow-up"></i> SAP HANA Integration</h2>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-gear"></i> SAP HANA Configuration</div>
            <div class="card-body">
                <form method="POST" action="/sap/config">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">SAP HANA Host</label>
                        <input type="text" name="sap_hana_host" class="form-control" value="<?= htmlspecialchars($config['sap_hana_host']['config_value'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="text" name="sap_hana_port" class="form-control" value="<?= htmlspecialchars($config['sap_hana_port']['config_value'] ?? '30015') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="sap_hana_username" class="form-control" value="<?= htmlspecialchars($config['sap_hana_username']['config_value'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="sap_hana_password" class="form-control" value="<?= htmlspecialchars($config['sap_hana_password']['config_value'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">OData URL</label>
                        <input type="url" name="sap_odata_url" class="form-control" value="<?= htmlspecialchars($config['sap_odata_url']['config_value'] ?? '') ?>" placeholder="http://host:8000/sap/opu/odata/">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sync Enabled</label>
                        <select name="sap_sync_enabled" class="form-select">
                            <option value="true" <?= ($config['sap_sync_enabled']['config_value'] ?? '') === 'true' ? 'selected' : '' ?>>Enabled</option>
                            <option value="false" <?= ($config['sap_sync_enabled']['config_value'] ?? '') !== 'true' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sync Interval (minutes)</label>
                        <input type="number" name="sap_sync_interval_minutes" class="form-control" value="<?= htmlspecialchars($config['sap_sync_interval_minutes']['config_value'] ?? '5') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Configuration</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-activity"></i> Sync Status</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Status</th><td><?= ($config['sap_sync_enabled']['config_value'] ?? '') === 'true' ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-danger">Disabled</span>' ?></td></tr>
                    <tr><th>Host</th><td><?= htmlspecialchars($config['sap_hana_host']['config_value'] ?? 'N/A') ?>:<?= htmlspecialchars($config['sap_hana_port']['config_value'] ?? '') ?></td></tr>
                    <tr><th>Last Sync</th><td><?= htmlspecialchars($config['sap_last_sync_at']['config_value'] ?? 'Never') ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-up-circle"></i> Push to SAP HANA</div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="/sap/sync/push/sample" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-primary">Push Samples</button></form>
                    <form method="POST" action="/sap/sync/push/result" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-primary">Push Results</button></form>
                    <form method="POST" action="/sap/sync/push/coa" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-primary">Push COA Status</button></form>
                    <form method="POST" action="/sap/sync/push-all" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-success">Push All</button></form>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-down-circle"></i> Pull from SAP HANA</div>
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="/sap/sync/pull/customer" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-info">Pull Customers</button></form>
                    <form method="POST" action="/sap/sync/pull/product" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-info">Pull Products</button></form>
                    <form method="POST" action="/sap/sync/pull/specification" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-outline-info">Pull Specs</button></form>
                    <form method="POST" action="/sap/sync/pull-all" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-success">Pull All</button></form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Sync Logs</div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Entity</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><span class="badge bg-<?= $l['sync_type'] === 'Push' ? 'primary' : 'info' ?>"><?= $l['sync_type'] ?></span></td>
                            <td><?= htmlspecialchars($l['entity_type']) ?></td>
                            <td><span class="badge bg-<?= ['Success'=>'success','Failed'=>'danger','Pending'=>'secondary','In Progress'=>'info'][$l['status']] ?? 'secondary' ?>"><?= $l['status'] ?></span></td>
                            <td><small><?= $l['created_at'] ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php require __DIR__ . '/../partials/pagination.php'; ?>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/app.php'; ?>
