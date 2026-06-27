<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-calendar-week me-2"></i>Calibration Schedules</h4>
        <span class="text-muted small"><?= count($schedules) ?> schedule(s)</span>
    </div>
    <div class="d-flex gap-2">
        <a href="/calibrations" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php if ($auth['role'] === 'Admin'): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal"><i class="bi bi-plus-lg me-1"></i>New Schedule</button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($schedules)): ?>
<div class="card">
    <div class="empty-state">
        <i class="bi bi-calendar-week"></i>
        <h5>No Schedules</h5>
        <p class="text-muted">No calibration schedules have been defined.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Instrument</th>
                    <th>Standard</th>
                    <th>Frequency (days)</th>
                    <th>Next Due</th>
                    <th>Assigned To</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><strong><?= e($s['instrument_name'] ?? '-') ?></strong></td>
                    <td><?= e($s['standard_name'] ?? '-') ?></td>
                    <td><?= e($s['frequency_days'] ?? '-') ?></td>
                    <td><?php $d = $s['next_due_date'] ?? null; if($d): $days = (strtotime($d)-time())/86400; $bg = $days<0?'danger':($days<30?'warning':'success'); ?><span class="badge bg-<?= $bg ?>"><?= e($d) ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    <td><?= e($s['assigned_name'] ?? 'Unassigned') ?></td>
                    <td class="text-end">
                        <?php if ($auth['role'] === 'Admin'): ?>
                        <a href="/calibrations/schedules?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- New Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/calibrations/schedules">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-1"></i>New Calibration Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Instrument <span class="text-danger">*</span></label>
                        <select name="instrument_id" class="form-select" required>
                            <option value="">-- Select Instrument --</option>
                            <?php foreach ($instruments as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= e($inst['instrument_name']) ?> (<?= e($inst['instrument_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Calibration Standard <span class="text-danger">*</span></label>
                        <select name="standard_id" class="form-select" required>
                            <option value="">-- Select Standard --</option>
                            <?php foreach ($standards as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= e($st['standard_code']) ?> - <?= e($st['standard_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Frequency (days) <span class="text-danger">*</span></label>
                        <input name="frequency_days" type="number" class="form-control" required value="365">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Next Due Date</label>
                        <input name="next_due_date" type="date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['full_name'] ?: $u['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>
