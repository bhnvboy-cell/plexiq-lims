<?php $title = 'Master Data Control Panel'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-sliders me-2"></i>Master Data Control Panel</h4>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2"><i class="bi bi-database me-1"></i><?= array_sum($stats) ?> Total Records</span>
        <button class="btn btn-outline-primary btn-sm" onclick="exportAll()"><i class="bi bi-download"></i> Export All</button>
    </div>
</div>

<?php $success = session_flash('success'); $error = session_flash('error'); ?>
<?php if ($success): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>

<!-- ============================================================ -->
<!-- GLOBAL SEARCH -->
<!-- ============================================================ -->
<div class="filter-section mb-4">
    <div class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="globalSearch" class="form-control border-start-0 ps-0" placeholder="Search across all master data (customers, products, tests, methods, chemicals, locations...)">
        <button class="btn btn-primary" onclick="doGlobalSearch()"><i class="bi bi-arrow-right"></i></button>
    </div>
    <div id="globalResults" class="mt-2 d-none"></div>
</div>

<!-- ============================================================ -->
<!-- SYSTEM HEALTH / STATS ROW -->
<!-- ============================================================ -->
<div class="row g-2 mb-4">
    <div class="col-md-2 col-4"><div class="stats-card stats-card-blue py-3" style="cursor:pointer;" onclick="location.href='/master/customers'"><i class="bi bi-building stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['customers'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Customers</div><small class="opacity-50"><?= $stats['customers_active'] ?? $stats['customers'] ?> active</small></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-green py-3" style="cursor:pointer;" onclick="location.href='/master/products'"><i class="bi bi-box-seam stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['products'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Products</div></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-purple py-3" style="cursor:pointer;" onclick="location.href='/master/tests'"><i class="bi bi-clipboard-check stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['tests'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Test Params</div></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-orange py-3" style="cursor:pointer;" onclick="location.href='/master/methods'"><i class="bi bi-flask stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['methods'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Methods</div></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-red py-3" style="cursor:pointer;" onclick="location.href='/master/chemical-inventory'"><i class="bi bi-droplet stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['chemicals'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Chemicals</div></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-dark py-3" style="cursor:pointer;" onclick="location.href='/master/calibrations'"><i class="bi bi-calendar-check stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['calibrations'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Calibrations</div></div></div>
    <div class="col-md-2 col-4"><div class="stats-card stats-card-teal py-3" style="cursor:pointer;" onclick="location.href='/master/manufacturers'"><i class="bi bi-building-gear stat-icon"></i><div class="stat-value" style="font-size:1.4rem;"><?= $stats['manufacturers'] ?? 0 ?></div><div class="stat-label" style="font-size:0.7rem;">Manufacturers</div></div></div>
</div>

<!-- ============================================================ -->
<!-- RECENT ACTIVITY + QUICK STATS -->
<!-- ============================================================ -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity me-1"></i>Recent Master Data Activity</span>
                <a href="/audit" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0" style="max-height:280px;overflow-y:auto;">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Action</th><th>Entity</th><th>User</th><th>When</th></tr></thead>
                    <tbody>
                        <?php if (!empty($recentActivity)): foreach ($recentActivity as $log): ?>
                        <tr>
                            <td><span class="badge bg-<?= str_contains($log['action'], 'Created')?'success':(str_contains($log['action'], 'Updated')?'primary':(str_contains($log['action'], 'Deleted')?'danger':'secondary')) ?> bg-opacity-10 text-dark"><?= e($log['action']) ?></span></td>
                            <td><small><?= e($log['entity_type'] ?? '-') ?> #<?= $log['entity_id'] ?? '' ?></small></td>
                            <td><small class="text-muted"><?= e($log['user_name'] ?? 'System') ?></small></td>
                            <td><small class="text-muted"><?= date('M d H:i', strtotime($log['created_at'])) ?></small></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3"><small>No recent activity</small></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>System Overview</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><div class="border rounded p-2 text-center"><div class="text-muted small">Sample Types</div><div class="fw-bold fs-5"><?= $stats['sample_types'] ?? 0 ?></div></div></div>
                    <div class="col-6"><div class="border rounded p-2 text-center"><div class="text-muted small">Locations</div><div class="fw-bold fs-5"><?= $stats['locations'] ?? 0 ?></div></div></div>
                    <div class="col-6"><div class="border rounded p-2 text-center"><div class="text-muted small">Units</div><div class="fw-bold fs-5"><?= $stats['units'] ?? 0 ?></div></div></div>
                    <div class="col-6"><div class="border rounded p-2 text-center"><div class="text-muted small">COA Templates</div><div class="fw-bold fs-5"><?= $stats['coa_templates'] ?? 0 ?></div></div></div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><i class="bi bi-shield-check me-1 text-success"></i>Data Integrity</span>
                    <span class="small fw-bold text-success">98%</span>
                </div>
                <div class="progress mt-1" style="height:4px;">
                    <div class="progress-bar bg-success" style="width:98%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- TILE GRID - Categorized -->
<!-- ============================================================ -->
<h5 class="fw-bold mb-3"><i class="bi bi-grid-3x3-gap me-1"></i>Master Data Modules</h5>

<div class="row g-3">
    <!-- Row 1: Core Business -->
    <div class="col-12"><small class="text-muted text-uppercase fw-bold ls-1">Core Business</small></div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/customers" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-building fs-1 mb-2" style="color:#2b7be4"></i><h6 class="fw-bold mb-1">Customers</h6><small class="text-muted">Manage client companies, contacts & addresses</small><div class="mt-2"><span class="badge bg-primary bg-opacity-10 text-primary"><?= $stats['customers'] ?? 0 ?> records</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/products" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-box-seam fs-1 mb-2" style="color:#11998e"></i><h6 class="fw-bold mb-1">Products</h6><small class="text-muted">Product catalog with categories & descriptions</small><div class="mt-2"><span class="badge bg-success bg-opacity-10 text-success"><?= $stats['products'] ?? 0 ?> products</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/tests" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-clipboard-check fs-1 mb-2" style="color:#764ba2"></i><h6 class="fw-bold mb-1">Test Parameters</h6><small class="text-muted">Define specs, limits, units & methods</small><div class="mt-2"><span class="badge bg-purple bg-opacity-10 text-purple"><?= $stats['tests'] ?? 0 ?> parameters</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/sample-types" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-tag fs-1 mb-2" style="color:#e74c3c"></i><h6 class="fw-bold mb-1">Sample Types</h6><small class="text-muted">Routine, Raw Material, In-Process, Stability</small><div class="mt-2"><span class="badge bg-danger bg-opacity-10 text-danger"><?= $stats['sample_types'] ?? 0 ?> types</span></div></div></a>
    </div>

    <!-- Row 2: Laboratory -->
    <div class="col-12 mt-2"><small class="text-muted text-uppercase fw-bold ls-1">Laboratory Configuration</small></div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/methods" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-flask fs-1 mb-2" style="color:#f5a623"></i><h6 class="fw-bold mb-1">Analytical Methods</h6><small class="text-muted">HPLC, GC, titrations & standard procedures</small><div class="mt-2"><span class="badge bg-warning bg-opacity-10 text-warning"><?= $stats['methods'] ?? 0 ?> methods</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/units" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-rulers fs-1 mb-2" style="color:#3498db"></i><h6 class="fw-bold mb-1">Units of Measure</h6><small class="text-muted">%, ppm, mg/L, NTU, mPa·s, ICUMSA</small><div class="mt-2"><span class="badge bg-info bg-opacity-10 text-info"><?= $stats['units'] ?? 0 ?> units</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/instruments" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-cpu fs-1 mb-2" style="color:#00d4aa"></i><h6 class="fw-bold mb-1">Instruments</h6><small class="text-muted">Lab equipment, interfaces & parsers</small><div class="mt-2"><span class="badge bg-teal bg-opacity-10 text-teal"><?= $stats['instruments'] ?? 0 ?> devices</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/instrument-locations" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-geo-alt fs-1 mb-2" style="color:#9b59b6"></i><h6 class="fw-bold mb-1">Instrument Locations</h6><small class="text-muted">Building, floor, room tracking</small><div class="mt-2"><span class="badge bg-purple bg-opacity-10 text-purple"><?= $stats['locations'] ?? 0 ?> locations</span></div></div></a>
    </div>

    <!-- Row 3: Quality & Compliance -->
    <div class="col-12 mt-2"><small class="text-muted text-uppercase fw-bold ls-1">Quality & Compliance</small></div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/calibrations" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-calendar-check fs-1 mb-2" style="color:#e67e22"></i><h6 class="fw-bold mb-1">Calibration Records</h6><small class="text-muted">Scheduled & completed calibrations</small><div class="mt-2"><span class="badge bg-orange bg-opacity-10 text-orange"><?= $stats['calibrations'] ?? 0 ?> records</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/chemical-inventory" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-droplet fs-1 mb-2" style="color:#1abc9c"></i><h6 class="fw-bold mb-1">Chemical Inventory</h6><small class="text-muted">Reagents, standards, CAS tracking</small><div class="mt-2"><span class="badge bg-teal bg-opacity-10 text-teal"><?= $stats['chemicals'] ?? 0 ?> items</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/coa-templates" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-file-earmark-text fs-1 mb-2" style="color:#e74c3c"></i><h6 class="fw-bold mb-1">COA Templates</h6><small class="text-muted">Certificate layout with QR/barcode</small><div class="mt-2"><span class="badge bg-danger bg-opacity-10 text-danger"><?= $stats['coa_templates'] ?? 0 ?> templates</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/email-config" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-envelope fs-1 mb-2" style="color:#3498db"></i><h6 class="fw-bold mb-1">Email Configuration</h6><small class="text-muted">SMTP server & notification settings</small><div class="mt-2"><span class="badge bg-info bg-opacity-10 text-info"><?= $stats['email_configs'] ?? 0 ?> configs</span></div></div></a>
    </div>

    <!-- Row 4: Administration -->
    <div class="col-12 mt-2"><small class="text-muted text-uppercase fw-bold ls-1">System Administration</small></div>
    <div class="col-md-4 col-lg-3">
        <a href="/users" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-people fs-1 mb-2" style="color:#2ecc71"></i><h6 class="fw-bold mb-1">User Roles & Access</h6><small class="text-muted">Admin, Analyst, Reviewer, Approver, Customer</small><div class="mt-2"><span class="badge bg-green bg-opacity-10 text-green"><?= $stats['users'] ?? 0 ?> users</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/sap" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-cloud-arrow-up fs-1 mb-2" style="color:#8e44ad"></i><h6 class="fw-bold mb-1">SAP HANA Integration</h6><small class="text-muted">ERP sync, OData, real-time data exchange</small><div class="mt-2"><span class="badge bg-purple bg-opacity-10 text-purple">Connected</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/audit" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-journal-text fs-1 mb-2" style="color:#95a5a6"></i><h6 class="fw-bold mb-1">Audit Trail</h6><small class="text-muted">Full traceability & compliance logging</small><div class="mt-2"><span class="badge bg-secondary bg-opacity-10 text-secondary">GDPR Ready</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/manufacturers" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-building-gear fs-1 mb-2" style="color:#00d4aa"></i><h6 class="fw-bold mb-1">Manufacturers</h6><small class="text-muted">Lab/company info, logo & contact details</small><div class="mt-2"><span class="badge bg-teal bg-opacity-10 text-teal"><?= $stats['manufacturers'] ?? 0 ?> records</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/spc" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-bar-chart-steps fs-1 mb-2" style="color:#00d4aa"></i><h6 class="fw-bold mb-1">SPC Control Charts</h6><small class="text-muted">Statistical process control & Cp/Cpk</small><div class="mt-2"><span class="badge bg-teal bg-opacity-10 text-teal">Real-time</span></div></div></a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="/master/product-tests" class="text-decoration-none"><div class="card text-center h-100 p-3 master-tile"><i class="bi bi-diagram-3 fs-1 mb-2" style="color:#8e44ad"></i><h6 class="fw-bold mb-1">Product-Test Mapping</h6><small class="text-muted">Assign tests & specs per product</small><div class="mt-2"><span class="badge bg-purple bg-opacity-10 text-purple"><?= $stats['product_tests'] ?? 0 ?> mappings</span></div></div></a>
    </div>
</div>

<!-- ============================================================ -->
<!-- SEARCH JAVASCRIPT -->
<!-- ============================================================ -->
<script>
let searchTimeout;
document.getElementById('globalSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('globalResults').classList.add('d-none'); return; }
    searchTimeout = setTimeout(() => doGlobalSearch(q), 300);
});

function doGlobalSearch(q) {
    const query = q || document.getElementById('globalSearch').value.trim();
    if (query.length < 2) return;
    const el = document.getElementById('globalResults');
    el.classList.remove('d-none');
    el.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div> Searching...</div>';

    fetch('/master/search?q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                el.innerHTML = '<div class="text-muted small py-2 text-center">No results found</div>';
                return;
            }
            let html = '<div class="list-group list-group-flush">';
            data.forEach(r => {
                const iconMap = {customers:'bi-building',products:'bi-box-seam',tests:'bi-clipboard-check',methods:'bi-flask',units:'bi-rulers','chemical_inventory':'bi-droplet','instrument_locations':'bi-geo-alt'};
                const icon = iconMap[r.table] || 'bi-file';
                const label = r.table.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
                html += '<a href="' + r.url + '" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">';
                html += '<i class="' + icon + ' text-muted"></i>';
                html += '<div><strong>' + r.label + '</strong><br><small class="text-muted">' + label + ' &middot; ' + r.subtitle + '</small></div>';
                html += '</a>';
            });
            html += '</div>';
            el.innerHTML = html;
        })
        .catch(() => el.innerHTML = '<div class="text-danger small py-2">Search failed</div>');
}

function exportAll() {
    const tables = ['customers','products','tests','methods','units','sample_types','instrument_locations','chemical_inventory','manufacturers'];
    if (confirm('Download all master data as CSV? This will download ' + tables.length + ' files.')) {
        tables.forEach(t => {
            const a = document.createElement('a');
            a.href = '/master/export/' + t;
            a.download = t + '.csv';
            a.click();
        });
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#globalResults') && !e.target.closest('#globalSearch')) {
        document.getElementById('globalResults').classList.add('d-none');
    }
});
</script>

<style>
.master-tile { transition: all .25s cubic-bezier(0.4,0,0.2,1); border: 2px solid transparent; border-radius: 12px; }
.master-tile:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0,0,0,0.12); }
.master-tile .badge { font-size: 0.65rem; }
.ls-1 { letter-spacing: 1px; font-size: 0.7rem; }
#globalResults { position: absolute; z-index: 1050; background: #fff; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); max-height: 350px; overflow-y: auto; width: 100%; }
.filter-section { position: relative; }
.list-group-item { border-left: none; border-right: none; }
.list-group-item:first-child { border-top: none; }
.list-group-item:last-child { border-bottom: none; }
</style>
