<?php layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="page-title mb-1"><i class="bi bi-upload me-2"></i>Import Data</h4>
        <span class="text-muted small"><?= e($instrument['instrument_name']) ?> (<?= e($instrument['instrument_code']) ?>)</span>
    </div>
    <a href="/instruments" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Instruments</a>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stats-card stats-card-blue px-3 py-2 rounded-3">
                        <i class="bi bi-cpu fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= e($instrument['instrument_name']) ?></div>
                        <div class="text-muted small"><?= e($instrument['interface_type']) ?> Interface</div>
                    </div>
                </div>
                <div class="mb-2"><span class="stat-label">Interface Type</span><br><span class="fw-medium"><?= e($instrument['interface_type']) ?></span></div>
                <div class="mb-2"><span class="stat-label">Auto-Import</span><br><?= $instrument['auto_import'] ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-secondary">Disabled</span>' ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="fw-semibold mb-3"><i class="bi bi-upload me-2"></i>Upload File</h6>
                <form method="POST" action="/instruments/<?= $instrument['id'] ?>/upload" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="instrument_file" class="form-control" accept=".<?= strtolower(e($instrument['interface_type'])) ?>,.txt,.dat,.csv,.xml,.tsv" required>
                        <div class="form-text small">
                            Supported: <?php
                                $exts = match ($instrument['interface_type']) {
                                    'XML' => '.xml',
                                    'CSV' => '.csv, .tsv',
                                    'TEXT' => '.txt, .dat, .prn',
                                };
                                echo $exts;
                            ?>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Upload & Parse</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white"><strong>Sample File Formats</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-code-slash me-1 text-info"></i>XML Format</h6>
                            <pre class="mb-0 small text-muted" style="font-size:0.75rem;">&lt;?xml version="1.0"?&gt;
&lt;Results&gt;
  &lt;Result&gt;
    &lt;SampleID&gt;S001&lt;/SampleID&gt;
    &lt;TestID&gt;T001&lt;/TestID&gt;
    &lt;Result&gt;1.234&lt;/Result&gt;
    &lt;Unit&gt;mg/L&lt;/Unit&gt;
  &lt;/Result&gt;
&lt;/Results&gt;</pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-table me-1 text-success"></i>CSV Format</h6>
                            <pre class="mb-0 small text-muted" style="font-size:0.75rem;">sample_code,test_code,result,unit
S001,T001,1.234,mg/L
S001,T002,5.678,ppm</pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-file-text me-1 text-warning"></i>Text Format</h6>
                            <pre class="mb-0 small text-muted" style="font-size:0.75rem;">Sample: S001
TestID: T001
Result: 1.234
Unit: mg/L</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
