<?php $title = $report ? 'Edit Report' : 'Create Report'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i><?= $title ?></h4>
    <a href="/bi" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Reports</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= $report ? '/bi/reports/' . $report['id'] . '/run' : '/bi/reports' ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Report Name <span class="text-danger">*</span></label>
                    <input type="text" name="report_name" class="form-control" value="<?= e($report['report_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Connection</label>
                    <select name="connection_id" class="form-select">
                        <option value="">Local Database</option>
                        <?php foreach ($connections as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($report['connection_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= e($report['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">SQL Query <span class="text-danger">*</span></label>
                    <textarea name="query_sql" class="form-control font-monospace" rows="8" required><?= e($report['query_sql'] ?? 'SELECT 1') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chart Type</label>
                    <select name="chart_type" class="form-select">
                        <option value="table" <?= ($report['chart_type'] ?? 'table') === 'table' ? 'selected' : '' ?>>Table</option>
                        <option value="bar" <?= ($report['chart_type'] ?? '') === 'bar' ? 'selected' : '' ?>>Bar Chart</option>
                        <option value="line" <?= ($report['chart_type'] ?? '') === 'line' ? 'selected' : '' ?>>Line Chart</option>
                        <option value="pie" <?= ($report['chart_type'] ?? '') === 'pie' ? 'selected' : '' ?>>Pie Chart</option>
                        <option value="area" <?= ($report['chart_type'] ?? '') === 'area' ? 'selected' : '' ?>>Area Chart</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Refresh Interval</label>
                    <select name="refresh_interval" class="form-select">
                        <option value="0" <?= ($report['refresh_interval'] ?? 0) == 0 ? 'selected' : '' ?>>Manual</option>
                        <option value="60" <?= ($report['refresh_interval'] ?? '') == 60 ? 'selected' : '' ?>>1 minute</option>
                        <option value="300" <?= ($report['refresh_interval'] ?? '') == 300 ? 'selected' : '' ?>>5 minutes</option>
                        <option value="900" <?= ($report['refresh_interval'] ?? '') == 900 ? 'selected' : '' ?>>15 minutes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !isset($report['is_active']) || $report['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $report ? 'Update' : 'Create' ?> Report</button>
                </div>
            </div>
        </form>
    </div>
</div>
