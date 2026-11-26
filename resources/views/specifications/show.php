<?php
layout('app');
$title = "Specification: " . htmlspecialchars($specification['spec_code']);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="/specifications" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                <h1 class="h3 mb-0 font-monospace"><?= htmlspecialchars($specification['spec_code']) ?></h1>
                <span class="badge bg-primary"><?= htmlspecialchars($specification['grade'] ?? 'Standard') ?></span>
            </div>
            <p class="text-muted mb-0 mt-1">
                Product: <strong><?= htmlspecialchars($specification['product_name']) ?></strong> (<code><?= htmlspecialchars($specification['product_code']) ?></code>)
                <?php if (!empty($specification['customer_name'])): ?>
                    | Customer: <span class="badge bg-light text-dark border"><?= htmlspecialchars($specification['customer_name']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newVersionModal">
                <i class="bi bi-file-earmark-plus"></i> New Draft Revision
            </button>
        </div>
    </div>

    <!-- Revision Tabs -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <ul class="nav nav-pills card-header-pills">
                <?php foreach ($specification['versions'] as $v): ?>
                    <?php
                    $isActiveTab = ($version && (int)$version['id'] === (int)$v['id']);
                    $statusClass = match ($v['status']) {
                        'Approved' => 'bg-success',
                        'Draft' => 'bg-warning text-dark',
                        'Superseded' => 'bg-secondary',
                        default => 'bg-dark'
                    };
                    ?>
                    <li class="nav-item me-2">
                        <a class="nav-link <?= $isActiveTab ? 'active' : '' ?>" href="/specifications/<?= $specification['id'] ?>?version=<?= $v['version_number'] ?>">
                            <strong>v<?= $v['version_number'] ?>.0</strong>
                            <span class="badge <?= $statusClass ?> ms-1"><?= $v['status'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div>
                <?php if ($version && $version['status'] === 'Approved'): ?>
                    <span class="badge bg-success p-2">
                        <i class="bi bi-shield-lock-fill me-1"></i> 21 CFR Part 11 Locked (Immutable)
                    </span>
                <?php elseif ($version && $version['status'] === 'Draft'): ?>
                    <span class="badge bg-warning text-dark p-2">
                        <i class="bi bi-pencil-square me-1"></i> Editable Draft Version
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <?php if (!$version): ?>
                <div class="alert alert-info mb-0">No version selected or found.</div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="text-muted small">Effective Range</label>
                        <div class="fw-bold">
                            <?= date('M d, Y', strtotime($version['effective_from'])) ?>
                            &rarr;
                            <?= !empty($version['effective_to']) ? date('M d, Y', strtotime($version['effective_to'])) : '<span class="text-success">Current / Open</span>' ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Lifecycle Status</label>
                        <div>
                            <span class="badge <?= $version['status'] === 'Approved' ? 'bg-success' : 'bg-warning text-dark' ?> fs-6">
                                <?= $version['status'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Approval / Sign-Off</label>
                        <div class="small">
                            <?php if ($version['status'] === 'Approved'): ?>
                                <strong><?= htmlspecialchars($version['approved_by_name'] ?? 'System') ?></strong><br>
                                <span class="text-muted"><?= date('M d, Y H:i', strtotime($version['approved_at'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Pending E-Signature</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Change Control Reference</label>
                        <div class="small">
                            <?= !empty($version['change_control_title']) ? htmlspecialchars($version['change_control_title']) : '<span class="text-muted">None (Direct Spec Release)</span>' ?>
                        </div>
                    </div>
                </div>

                <?php if ($version['status'] === 'Approved' && !empty($version['approval_signature_hash'])): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4">
                        <i class="bi bi-fingerprint fs-2 me-3"></i>
                        <div>
                            <div class="fw-bold">21 CFR Part 11 / EU Annex 11 Electronic Signature Hash</div>
                            <small class="font-monospace text-break"><?= htmlspecialchars($version['approval_signature_hash']) ?></small>
                            <div class="small text-muted mt-1">Reason: <em>"<?= htmlspecialchars($version['approval_reason'] ?? 'Routine approval') ?>"</em></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Limits Table -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-1"></i>Component Analytical Limits</h5>
                    <?php if ($version['status'] === 'Draft'): ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addLimitModal">
                                <i class="bi bi-plus-lg"></i> Add Component Limit
                            </button>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveVersionModal">
                                <i class="bi bi-check2-circle"></i> E-Sign & Approve v<?= $version['version_number'] ?>.0
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Component (Analyte)</th>
                                <th>Method / Analysis</th>
                                <th>Limit Rule</th>
                                <th>Specification Target / Range</th>
                                <th>Warning Band (Action)</th>
                                <th>Unit</th>
                                <th>Rounding / Sig Figs</th>
                                <th>OOT Rule</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($version['limits'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No analytical limits configured for this revision yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($version['limits'] as $lim): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($lim['component_name']) ?></div>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($lim['component_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($lim['analysis_name'] ?? 'General') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($lim['limit_type'])) ?></span>
                                        </td>
                                        <td class="fw-bold text-dark font-monospace">
                                            <?= htmlspecialchars($lim['display_specification'] ?: ($lim['min_limit'] . ' - ' . $lim['max_limit'])) ?>
                                        </td>
                                        <td class="small">
                                            <?php if ($lim['warn_min'] !== null || $lim['warn_max'] !== null): ?>
                                                <span class="text-warning fw-bold font-monospace">[<?= $lim['warn_min'] ?? '-' ?> to <?= $lim['warn_max'] ?? '-' ?>]</span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($lim['unit'] ?? '') ?></code></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($lim['rounding_method']) ?>
                                                (<?= $lim['decimal_places'] ?> dec)
                                            </small>
                                        </td>
                                        <td>
                                            <?= !empty($lim['oot_rule']) ? '<span class="badge bg-info text-dark">' . htmlspecialchars($lim['oot_rule']) . '</span>' : '<span class="text-muted">-</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: New Draft Version -->
<div class="modal fade" id="newVersionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/specifications/<?= $specification['id'] ?>/draft" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Specification Draft Revision</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Effective From Date</label>
                    <input type="date" name="effective_from" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Clone Limits from Existing Version</label>
                    <select name="clone_from_version_id" class="form-select">
                        <option value="">-- Start Clean (No cloned limits) --</option>
                        <?php foreach ($specification['versions'] as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($version && $version['id'] == $v['id']) ? 'selected' : '' ?>>
                                Clone from v<?= $v['version_number'] ?>.0 (<?= $v['status'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Revision Reason / Change Description</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Describe rationale for opening this new revision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Draft Revision</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Component Limit -->
<?php if ($version && $version['status'] === 'Draft'): ?>
<div class="modal fade" id="addLimitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="/specifications/versions/<?= $version['id'] ?>/limits" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add / Update Component Analytical Limit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Component (Analyte)</label>
                        <select name="component_id" class="form-select" required>
                            <option value="">-- Select Component --</option>
                            <?php foreach ($components as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['component_name']) ?> (<?= htmlspecialchars($c['component_code']) ?>) [<?= htmlspecialchars($c['unit'] ?? '') ?>]</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Limit Type</label>
                        <select name="limit_type" class="form-select" required>
                            <option value="range">Range (Min to Max)</option>
                            <option value="min">Minimum Only (&ge; Min)</option>
                            <option value="max">Maximum Only (&le; Max)</option>
                            <option value="text_match">Text Match (Exact String)</option>
                            <option value="pass_fail">Pass / Fail</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Limit</label>
                        <input type="number" step="any" name="min_limit" class="form-control" placeholder="e.g. 98.0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max Limit</label>
                        <input type="number" step="any" name="max_limit" class="form-control" placeholder="e.g. 102.0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Nominal</label>
                        <input type="number" step="any" name="target_value" class="form-control" placeholder="e.g. 100.0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warn Min (Action)</label>
                        <input type="number" step="any" name="warn_min" class="form-control" placeholder="e.g. 98.5">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Warn Max (Action)</label>
                        <input type="number" step="any" name="warn_max" class="form-control" placeholder="e.g. 101.5">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Display Specification Text (COA Formatting)</label>
                        <input type="text" name="display_specification" class="form-control" placeholder="e.g. 98.0 - 102.0 % (Ph. Eur.)">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save Specification Limit</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Approve Version E-Signature -->
<div class="modal fade" id="approveVersionModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="/specifications/versions/<?= $version['id'] ?>/approve" method="POST" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-pen-fill me-2"></i>21 CFR Part 11 Electronic Signature</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>GxP Lock Notice:</strong> Approving this specification version locks all limits permanently. Once approved, limits cannot be edited or deleted without creating a new draft revision.
                </div>
                <div class="mb-3">
                    <label class="form-label">Manifestation of Intent</label>
                    <div class="form-control bg-light small" readonly>
                        "I hereby certify and approve Specification v<?= $version['version_number'] ?>.0 as the legally binding analytical standard for <?= htmlspecialchars($specification['product_name']) ?>."
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Signing (Required under Part 11)</label>
                    <textarea name="signature_reason" class="form-control" required rows="2" placeholder="e.g. Annual specification review; updated assay acceptance criteria per change control CC-2026-004"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-shield-check me-1"></i>Execute E-Signature & Approve</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
