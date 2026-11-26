<?php
layout('app');
$title = 'Sample Templates Master';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-box-seam me-2"></i>Sample Templates</h1>
            <p class="text-muted mb-0">Commercial Pre-Configured Templates for Sample Login (Storage Conditions, Labels, Receipt Checks)</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTemplateModal">
            <i class="bi bi-plus-lg me-1"></i> New Sample Template
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-list-check me-2"></i>Active Sample Templates</span>
            <span class="badge bg-secondary"><?= count($templates) ?> templates</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Template Code</th>
                            <th>Template Name</th>
                            <th>Sample Type</th>
                            <th>Default Stage</th>
                            <th>Storage Condition</th>
                            <th>Label Format</th>
                            <th>Receipt Check</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No sample templates defined. Click "New Sample Template" to configure one.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $tmpl): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($tmpl['template_code']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($tmpl['template_name']) ?></td>
                                    <td><?= htmlspecialchars($tmpl['sample_type_name'] ?? 'General') ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($tmpl['default_stage']) ?></span></td>
                                    <td><i class="bi bi-thermometer-half text-danger me-1"></i><?= htmlspecialchars($tmpl['storage_condition']) ?></td>
                                    <td><code><?= htmlspecialchars($tmpl['label_format']) ?></code></td>
                                    <td>
                                        <?php if (!empty($tmpl['requires_receipt_check'])): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-check-circle me-1"></i>Mandatory</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted">Optional</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: New Template -->
<div class="modal fade" id="newTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/sample-templates">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i> New Sample Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Template Code *</label>
                        <input type="text" name="template_code" class="form-control" placeholder="e.g. TMPL-COLD-CHAIN" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template Name *</label>
                        <input type="text" name="template_name" class="form-control" placeholder="e.g. Cold Chain Biological Sample" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sample Type</label>
                        <select name="sample_type_id" class="form-select">
                            <option value="">-- General / Any --</option>
                            <?php foreach ($sampleTypes as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Lifecycle Stage *</label>
                        <select name="default_stage" class="form-select" required>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?= $stage ?>"><?= $stage ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard Storage Condition *</label>
                        <select name="storage_condition" class="form-select" required>
                            <option value="Ambient 15-25°C">Ambient (15-25°C)</option>
                            <option value="Refrigerated 2-8°C">Refrigerated (2-8°C)</option>
                            <option value="Freezer -20°C">Freezer (-20°C)</option>
                            <option value="Ultra-Low Freezer -80°C">Ultra-Low Freezer (-80°C)</option>
                            <option value="Liquid Nitrogen Cryo">Liquid Nitrogen Cryo (-196°C)</option>
                            <option value="Desiccated Dry Cabinet">Desiccated Dry Cabinet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Label Format *</label>
                        <select name="label_format" class="form-select" required>
                            <option value="ZPL_2X1">Zebra ZPL (2" x 1" Barcode)</option>
                            <option value="ZPL_4X2">Zebra ZPL (4" x 2" 2D Datamatrix)</option>
                            <option value="CRYOTAG_1X0.5">Cryo-Tag Cryogenic Resistant</option>
                            <option value="EPL_STANDARD">Eltron EPL Standard</option>
                        </select>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="requires_receipt_check" id="reqReceiptCheck" value="1" checked>
                        <label class="form-check-label" for="reqReceiptCheck">Requires Formal Receipt Check (Integrity, Seals, Temp)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>
