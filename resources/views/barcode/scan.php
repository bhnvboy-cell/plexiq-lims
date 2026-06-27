<?php $title = 'Barcode Scanner'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-upc-scan me-2"></i>Barcode Scanner</h4>
    <a href="/barcode/logs" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> Scan Logs</a>
</div>

<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-upc-scan display-1 text-primary"></i>
                </div>
                <h5 class="mb-3">Scan a Barcode</h5>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="barcodeInput" class="form-control form-control-lg" placeholder="Scan or type barcode..." autofocus>
                    <button class="btn btn-primary" onclick="lookupBarcode()"><i class="bi bi-search"></i> Lookup</button>
                </div>
                <div class="mt-2 text-muted small">
                    <i class="bi bi-info-circle me-1"></i>Supports QR codes, Code 128, Code 39, Data Matrix, and EAN-13 formats.
                </div>
            </div>
        </div>
    </div>
</div>

<div id="scanResult" class="d-none">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm" id="resultCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0" id="resultTitle"><i class="bi bi-info-circle me-1"></i>Scan Result</h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearScan()"><i class="bi bi-x"></i></button>
                </div>
                <div class="card-body" id="resultBody">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('barcodeInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); lookupBarcode(); }
});

function lookupBarcode() {
    const code = document.getElementById('barcodeInput').value.trim();
    if (!code) { alert('Please enter or scan a barcode.'); return; }

    const resultDiv = document.getElementById('scanResult');
    const resultBody = document.getElementById('resultBody');
    const resultTitle = document.getElementById('resultTitle');
    resultDiv.classList.remove('d-none');
    resultBody.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Looking up...</div>';
    resultTitle.innerHTML = '<i class="bi bi-search me-1"></i>Searching...';

    fetch('/barcode/lookup?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                resultBody.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + e_html(data.error) + '</div>';
                resultTitle.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>Not Found';
                return;
            }
            resultTitle.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>' + e_html(data.entity_type) + ': ' + e_html(data.label);
            let html = '<table class="table table-sm table-borderless mb-0">';
            for (const [key, val] of Object.entries(data.fields)) {
                html += '<tr><td class="text-muted" style="width:140px">' + e_html(key) + '</td><td class="fw-bold">' + e_html(val ?? '—') + '</td></tr>';
            }
            html += '</table>';
            if (data.action_url) {
                html += '<div class="mt-3"><a href="' + e_html(data.action_url) + '" class="btn btn-primary btn-sm"><i class="bi bi-eye"></i> View Details</a></div>';
            }
            resultBody.innerHTML = html;
        })
        .catch(err => {
            resultBody.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Lookup failed. Please try again.</div>';
            resultTitle.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>Error';
        });
}

function clearScan() {
    document.getElementById('barcodeInput').value = '';
    document.getElementById('scanResult').classList.add('d-none');
    document.getElementById('barcodeInput').focus();
}

function e_html(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
