<?php
layout('app');
$title = 'Enterprise ERP Quality Connectors';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Enterprise ERP Quality Connectors</h1>
            <p class="text-muted mb-0">Bidirectional inspection lot pull, master data sync, and usage decision push for SAP S/4HANA, Oracle Cloud & Microsoft Dynamics 365</p>
        </div>
        <div>
            <span class="badge bg-success p-2"><i class="bi bi-shield-check"></i> 21 CFR Part 11 Validated Bus</span>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($connectors as $conn): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-start border-4 <?= $conn['is_active'] ? 'border-primary' : 'border-secondary' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold text-dark mb-0"><?= htmlspecialchars($conn['name']) ?></h5>
                            <span class="badge <?= $conn['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $conn['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-2">Type: <code><?= htmlspecialchars($conn['erp_type']) ?></code></p>
                        <p class="text-truncate small text-secondary mb-3"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars($conn['base_url']) ?></p>
                        
                        <div class="mb-3">
                            <span class="badge bg-light text-dark border">Auth: <?= htmlspecialchars($conn['auth_type']) ?></span>
                            <?php if ($conn['last_synced_at']): ?>
                                <span class="badge bg-light text-secondary border">Synced: <?= htmlspecialchars($conn['last_synced_at']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="pingErp(<?= $conn['id'] ?>)">
                                <i class="bi bi-activity"></i> Test Connectivity
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="pullLots(<?= $conn['id'] ?>)">
                                <i class="bi bi-cloud-arrow-down"></i> Pull Inspection Lots
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">ERP Communication & Synchronization Log</h5>
        </div>
        <div class="card-body">
            <div id="erpResponseArea" class="bg-dark text-light p-3 rounded font-monospace small" style="min-height: 150px; max-height: 300px; overflow-y: auto;">
                // Ready to execute enterprise ERP synchronization actions. Output will appear here.
            </div>
        </div>
    </div>
</div>

<script>
function pingErp(id) {
    const area = document.getElementById('erpResponseArea');
    area.innerHTML = `[${new Date().toISOString()}] Pinging ERP connector ID ${id}...\n`;
    fetch(`/erp/connectors/${id}/ping`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        area.innerHTML += JSON.stringify(data, null, 2);
    })
    .catch(err => {
        area.innerHTML += `ERROR: ${err.message}`;
    });
}

function pullLots(id) {
    const area = document.getElementById('erpResponseArea');
    area.innerHTML = `[${new Date().toISOString()}] Pulling inspection lots from connector ID ${id}...\n`;
    fetch(`/erp/connectors/${id}/pull`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        area.innerHTML += JSON.stringify(data, null, 2);
    })
    .catch(err => {
        area.innerHTML += `ERROR: ${err.message}`;
    });
}
</script>
