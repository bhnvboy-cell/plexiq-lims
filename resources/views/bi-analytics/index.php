<?php $title = 'BI & Analytics'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>BI & Analytics</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#connectionModal"><i class="bi bi-plug"></i> Connections</button>
        <button class="btn btn-primary btn-sm" onclick="newReport()"><i class="bi bi-plus-lg"></i> New Report</button>
    </div>
</div>

<div class="row g-3">
    <!-- Reports List -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-1"></i>Saved Reports</h6></div>
            <div class="list-group list-group-flush" style="max-height:500px;overflow-y:auto;">
                <?php if (empty($reports)): ?>
                <div class="text-center text-muted py-4"><small>No reports created yet.</small></div>
                <?php else: foreach ($reports as $r): ?>
                <a href="?report=<?= $r['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= ($selectedReportId ?? '') == $r['id'] ? 'active' : '' ?>">
                    <div>
                        <div class="fw-bold small"><?= e($r['report_name']) ?></div>
                        <small class="text-muted"><?= e($r['category'] ?? 'Uncategorized') ?></small>
                    </div>
                    <small><?= date('M d', strtotime($r['updated_at'] ?? $r['created_at'])) ?></small>
                </a>
                <?php endforeach; endif; ?>
                <button class="list-group-item list-group-item-action text-primary text-center fw-bold" onclick="newReport()">
                    <i class="bi bi-plus-circle me-1"></i>Create New Report
                </button>
            </div>
        </div>
    </div>

    <!-- Report Editor & Preview -->
    <div class="col-md-8">
        <?php if ($selectedReport ?? false): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-eye me-1"></i>Report Preview: <?= e($selectedReport['report_name'] ?? '') ?></h6>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="editReport(<?= $selectedReport['id'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                    <button class="btn btn-sm btn-outline-success" onclick="exportReport(<?= $selectedReport['id'] ?>)"><i class="bi bi-download"></i> Export</button>
                </div>
            </div>
            <div class="card-body text-center py-5">
                <div id="chartPreview">
                    <canvas id="reportChart" height="250"></canvas>
                </div>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i>Report type: <?= e($selectedReport['chart_type'] ?? 'Table') ?>
                    &middot; Data source: <?= e($selectedReport['data_source'] ?? 'Internal') ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-table me-1"></i>Report Data</h6></div>
            <div class="table-responsive" style="max-height:300px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php if (!empty($reportData)): ?>
                            <?php foreach (array_keys($reportData[0]) as $col): ?>
                            <th><?= e($col) ?></th>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No data for this report.</td></tr>
                        <?php else: foreach ($reportData as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                            <td><?= e($cell ?? '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-bar-chart-line display-4 d-block mb-3 text-muted"></i>
                <h5>Select a Report</h5>
                <p class="text-muted">Choose a report from the list or create a new one.</p>
                <button class="btn btn-primary" onclick="newReport()"><i class="bi bi-plus-lg"></i> Create Report</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST" action="/bi/reports" id="reportForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="reportId" value="">
    <div class="modal-header"><h5 class="modal-title" id="reportModalTitle"><i class="bi bi-file-earmark-bar-graph me-1"></i>New Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Report Name <span class="text-danger">*</span></label>
                <input type="text" name="report_name" id="reportName" class="form-control" required placeholder="e.g. Monthly QC Summary">
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category" id="reportCategory" class="form-select">
                    <option value="">— Select —</option>
                    <option value="QC">Quality Control</option>
                    <option value="Production">Production</option>
                    <option value="Stability">Stability</option>
                    <option value="Compliance">Compliance</option>
                    <option value="Custom">Custom</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Chart Type</label>
                <select name="chart_type" id="reportChartType" class="form-select">
                    <option value="bar">Bar Chart</option>
                    <option value="line">Line Chart</option>
                    <option value="pie">Pie Chart</option>
                    <option value="doughnut">Doughnut Chart</option>
                    <option value="table">Table</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Data Source</label>
                <select name="data_source" id="reportDataSource" class="form-select">
                    <option value="internal">Internal Database</option>
                    <?php foreach ($connections ?? [] as $c): ?>
                    <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Aggregation</label>
                <select name="aggregation" id="reportAggregation" class="form-select">
                    <option value="count">Count</option>
                    <option value="sum">Sum</option>
                    <option value="avg">Average</option>
                    <option value="min">Minimum</option>
                    <option value="max">Maximum</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Query / SQL (optional)</label>
                <textarea name="query" id="reportQuery" class="form-control font-monospace" rows="4" placeholder="SELECT ... FROM ... WHERE ..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" id="reportDescription" class="form-control" rows="2" placeholder="Optional description..."></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Report</button>
    </div>
</form>
</div></div></div>

<!-- BI Connections Panel -->
<div class="modal fade" id="connectionModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-plug me-1"></i>BI Connections</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <?php if (empty($connections)): ?>
    <div class="text-center text-muted py-3">No external connections configured.</div>
    <?php else: foreach ($connections as $c): ?>
    <div class="card mb-2">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
            <div>
                <strong><?= e($c['name']) ?></strong>
                <small class="text-muted ms-2"><?= e($c['type']) ?> &middot; <?= e($c['host'] ?? '') ?></small>
            </div>
            <span class="badge bg-<?= $c['is_connected'] ? 'success' : 'danger' ?>"><?= $c['is_connected'] ? 'Connected' : 'Disconnected' ?></span>
        </div>
    </div>
    <?php endforeach; endif; ?>
    <div class="mt-3">
        <button class="btn btn-outline-primary btn-sm w-100" onclick="addConnection()"><i class="bi bi-plus"></i> Add Connection</button>
    </div>
</div>
</div></div></div>

<script>
let chartInstance = null;

function newReport() {
    document.getElementById('reportId').value = '';
    document.getElementById('reportName').value = '';
    document.getElementById('reportCategory').value = '';
    document.getElementById('reportChartType').value = 'bar';
    document.getElementById('reportDataSource').value = 'internal';
    document.getElementById('reportAggregation').value = 'count';
    document.getElementById('reportQuery').value = '';
    document.getElementById('reportDescription').value = '';
    document.getElementById('reportModalTitle').innerHTML = '<i class="bi bi-file-earmark-bar-graph me-1"></i>New Report';
    document.getElementById('reportForm').action = '/bi/reports';
    new bootstrap.Modal(document.getElementById('reportModal')).show();
}

function editReport(id) {
    fetch('/bi/reports/' + id + '/edit')
        .then(r => r.json())
        .then(data => {
            document.getElementById('reportId').value = data.id;
            document.getElementById('reportName').value = data.report_name;
            document.getElementById('reportCategory').value = data.category || '';
            document.getElementById('reportChartType').value = data.chart_type || 'bar';
            document.getElementById('reportDataSource').value = data.data_source || 'internal';
            document.getElementById('reportAggregation').value = data.aggregation || 'count';
            document.getElementById('reportQuery').value = data.query || '';
            document.getElementById('reportDescription').value = data.description || '';
            document.getElementById('reportModalTitle').innerHTML = '<i class="bi bi-pencil me-1"></i>Edit Report';
            document.getElementById('reportForm').action = '/bi/reports/' + id + '/update';
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        });
}

function exportReport(id) {
    window.location.href = '/bi/reports/' + id + '/export?format=csv';
}

function addConnection() {
    alert('Connection configuration form would appear here. This allows connecting to external databases like PostgreSQL, MySQL, or data warehouses for BI reporting.');
}
</script>

<?php if ($selectedReport ?? false): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($chartLabels) && !empty($chartData)): ?>
const ctx = document.getElementById('reportChart');
if (ctx) {
    chartInstance = new Chart(ctx, {
        type: '<?= e($selectedReport['chart_type'] ?? 'bar') ?>',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: '<?= e($selectedReport['report_name']) ?>',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0','#6f42c1','#fd7e14','#20c997'],
                borderWidth: 1
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}
<?php endif; ?>
</script>
<?php endif; ?>
