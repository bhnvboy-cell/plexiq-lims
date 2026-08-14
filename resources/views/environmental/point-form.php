<?php $title = 'New Monitoring Point'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-geo-alt me-2"></i>New Monitoring Point</h4>
    <a href="/environmental/points" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-pencil-square me-1"></i>Point Details</h6></div>
            <div class="card-body">
                <form method="POST" action="/environmental/points">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Point Name <span class="text-danger">*</span></label>
                            <input type="text" name="point_name" class="form-control" required placeholder="e.g. Cold Storage Room 1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location Name</label>
                            <input type="text" name="location_name" class="form-control" placeholder="e.g. Warehouse A">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monitoring Type</label>
                            <select name="monitoring_type" class="form-select">
                                <option>Temperature</option>
                                <option>Humidity</option>
                                <option>Pressure</option>
                                <option>Light</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <input type="text" name="unit" class="form-control" placeholder="e.g. °C" value="°C">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Min Threshold</label>
                            <input type="number" name="min_threshold" class="form-control" step="any" placeholder="e.g. 2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Threshold</label>
                            <input type="number" name="max_threshold" class="form-control" step="any" placeholder="e.g. 8">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <a href="/environmental/points" class="btn btn-outline-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Point</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
