<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-<?= $instrument ? 'pencil' : 'plus-lg' ?> me-2"></i><?= $instrument ? 'Edit' : 'Add' ?> Instrument</h4>
    <a href="/instruments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $instrument ? '/instruments/' . $instrument['id'] . '/update' : '/instruments/store' ?>">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Instrument Code <span class="text-danger">*</span></label>
                    <input name="instrument_code" class="form-control" required value="<?= e($instrument['instrument_code'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Instrument Name <span class="text-danger">*</span></label>
                    <input name="instrument_name" class="form-control" required value="<?= e($instrument['instrument_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input name="model" class="form-control" value="<?= e($instrument['model'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Manufacturer</label>
                    <input name="manufacturer" class="form-control" value="<?= e($instrument['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Interface Type <span class="text-danger">*</span></label>
                    <select name="interface_type" class="form-select" required>
                        <option value="XML" <?= ($instrument['interface_type'] ?? '') === 'XML' ? 'selected' : '' ?>>XML</option>
                        <option value="CSV" <?= ($instrument['interface_type'] ?? '') === 'CSV' ? 'selected' : '' ?>>CSV</option>
                        <option value="TEXT" <?= ($instrument['interface_type'] ?? '') === 'TEXT' ? 'selected' : '' ?>>Text</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Host</label>
                    <input name="host" class="form-control" value="<?= e($instrument['host'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Port</label>
                    <input name="port" type="number" class="form-control" value="<?= e($instrument['port'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">File Watch Path</label>
                    <input name="file_watch_path" class="form-control" value="<?= e($instrument['file_watch_path'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="auto_import" value="1" class="form-check-input" id="auto_import" <?= ($instrument['auto_import'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_import">Auto-import results on upload (automatically match to samples)</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Parser Config (JSON)</label>
                    <textarea name="parser_config" class="form-control font-monospace" rows="3"><?= e($instrument['parser_config'] ?? '{}') ?></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $instrument ? 'Update' : 'Create' ?> Instrument</button>
                <a href="/instruments" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
