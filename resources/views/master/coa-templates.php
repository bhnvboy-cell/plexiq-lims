<?php $title = 'COA Template Customizer'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>COA Template Customizer</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal"><i class="bi bi-plus"></i> New Template</button>
</div>
<div class="row g-3 mb-4">
    <?php foreach ($templates as $t): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 <?= $t['is_default'] ? 'border-success' : '' ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-text me-1"></i><?= e($t['template_name']) ?></span>
                <?php if ($t['is_default']): ?><span class="badge bg-success">Default</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="coa-mini-preview border rounded p-2 mb-3" style="font-size:10px;max-height:150px;overflow:auto;background:#fafafa;">
                    <?= $t['template_html'] ? substr(strip_tags($t['template_html']), 0, 300) . '...' : '<span class="text-muted">Empty template</span>' ?>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?= $t['id'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                    <form method="POST" action="/master/coa-templates/<?= $t['id'] ?>/default" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success" <?= $t['is_default']?'disabled':'' ?>><i class="bi bi-star"></i> Set Default</button>
                    </form>
                    <button class="btn btn-sm btn-outline-danger" onclick="previewTemplate(<?= $t['id'] ?>)"><i class="bi bi-eye"></i> Preview</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <form method="POST" action="/master/coa-templates">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">COA Template Editor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Template Name</label><input type="text" name="template_name" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Default?</label><select name="is_default" class="form-select"><option value="0">No</option><option value="1">Yes</option></select></div>
                    <div class="col-md-3"><label class="form-label">Active?</label><select name="is_active" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><label class="form-label">Page Size</label><select name="page_size" class="form-select"><option value="A4">A4</option><option value="LETTER">Letter</option><option value="LEGAL">Legal</option></select></div>
                    <div class="col-md-3"><label class="form-label">Orientation</label><select name="orientation" class="form-select"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
                    <div class="col-md-2"><label class="form-label">Top Margin</label><input type="number" name="margin_top" class="form-control" value="15" min="5" max="50"></div>
                    <div class="col-md-2"><label class="form-label">Bottom Margin</label><input type="number" name="margin_bottom" class="form-control" value="15" min="5" max="50"></div>
                    <div class="col-md-1"><label class="form-label">Left</label><input type="number" name="margin_left" class="form-control" value="15" min="5" max="50"></div>
                    <div class="col-md-1"><label class="form-label">Right</label><input type="number" name="margin_right" class="form-control" value="15" min="5" max="50"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><label class="form-label">Logo Path (relative to storage/app/public/)</label><input type="text" name="logo_path" class="form-control" placeholder="e.g. logo/company-logo.png"></div>
                    <div class="col-md-6"><label class="form-label">SCADA Logo Path</label><input type="text" name="scada_logo_path" class="form-control" placeholder="e.g. logo/scada-logo.png"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><label class="form-label">Watermark Text</label><input type="text" name="watermark_text" class="form-control" placeholder="e.g. DRAFT"></div>
                    <div class="col-md-2"><label class="form-label">Show QR?</label><select name="show_qr_code" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Show Barcode?</label><select name="show_barcode" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Show Signature?</label><select name="show_signature" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                </div>
                <div class="mb-2"><label class="form-label">HTML Template</label>
                    <small class="text-muted d-block mb-2">Available placeholders: <code>[[COMPANY_NAME]], [[COA_NUMBER]], [[SAMPLE_CODE]], [[CUSTOMER_NAME]], [[PRODUCT_NAME]], [[BATCH_NUMBER]], [[MANUFACTURE_DATE]], [[EXPIRY_DATE]], [[RESULTS_ROWS]]</code></small>
                    <textarea name="template_html" class="form-control font-monospace" rows="20" style="font-size:12px;">&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;&lt;style&gt;
  body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
  .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
  .header h1 { font-size: 18px; margin: 0; }
  .header p { margin: 2px 0; color: #666; }
  table { width: 100%; border-collapse: collapse; margin: 15px 0; }
  th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
  th { background: #eee; font-weight: bold; }
  .info-table td { border: none; padding: 3px 8px; }
  .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #999; border-top: 1px solid #ccc; padding-top: 10px; }
&lt;/style&gt;&lt;/head&gt;
&lt;body&gt;
&lt;div class="header"&gt;
  &lt;h1&gt;[[COMPANY_NAME]]&lt;/h1&gt;
  &lt;p&gt;Certificate of Analysis&lt;/p&gt;
  &lt;p&gt;COA #: [[COA_NUMBER]]&lt;/p&gt;
&lt;/div&gt;
&lt;table class="info-table"&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Customer:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[CUSTOMER_NAME]]&lt;/td&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Product:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[PRODUCT_NAME]]&lt;/td&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Batch:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[BATCH_NUMBER]]&lt;/td&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Sample:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[SAMPLE_CODE]]&lt;/td&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Mfg Date:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[MANUFACTURE_DATE]]&lt;/td&gt;&lt;/tr&gt;
  &lt;tr&gt;&lt;td&gt;&lt;strong&gt;Expiry Date:&lt;/strong&gt;&lt;/td&gt;&lt;td&gt;[[EXPIRY_DATE]]&lt;/td&gt;&lt;/tr&gt;
&lt;/table&gt;
&lt;table&gt;
  &lt;tr&gt;&lt;th&gt;Test&lt;/th&gt;&lt;th&gt;Method&lt;/th&gt;&lt;th&gt;Specification&lt;/th&gt;&lt;th&gt;Result&lt;/th&gt;&lt;th&gt;Unit&lt;/th&gt;&lt;th&gt;Status&lt;/th&gt;&lt;/tr&gt;
  [[RESULTS_ROWS]]
&lt;/table&gt;
&lt;p&gt;&lt;strong&gt;Conclusion:&lt;/strong&gt; The above product meets the specified quality standards.&lt;/p&gt;
&lt;div class="footer"&gt;
  &lt;p&gt;Generated by PlexiQ LIMS | Authorized by [[APPROVED_BY]]&lt;/p&gt;
&lt;/div&gt;
&lt;/body&gt;
&lt;/html&gt;</textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">Add QR / Barcode</label>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertPlaceholder('&lt;img src=\'https://api.qrserver.com/v1/create-qr-code/?size=100x100&amp;data=[[COA_NUMBER]]\' style=\'float:right;\'&gt;')"><i class="bi bi-qr-code"></i> QR (External)</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertPlaceholder('&lt;div id="barcode-[[COA_NUMBER]]" style="float:right;"&gt;&lt;script&gt;JsBarcode("#barcode-[[COA_NUMBER]]", "[[COA_NUMBER]]", {format:"CODE128",width:2,height:60});&lt;\/script&gt;&lt;\/div&gt;')"><i class="bi bi-upc-scan"></i> Barcode (Local)</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertPlaceholder('&lt;div id="qr-[[COA_NUMBER]]" style="float:right;"&gt;&lt;script&gt;QRCode.toCanvas(document.getElementById("qr-[[COA_NUMBER]]"), "[[COA_NUMBER]]", {width:100});&lt;\/script&gt;&lt;\/div&gt;')"><i class="bi bi-qr-code"></i> QR (Local)</button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="insertPlaceholder('&lt;svg class="barcode-inline" data-code="[[COA_NUMBER]]" style="float:right;"&gt;&lt;/svg&gt;')"><i class="bi bi-upc-scan"></i> Barcode SVG</button>
                    </div>
                    <small class="text-muted d-block mt-1">Local barcode requires <code>JsBarcode</code> library; Local QR requires <code>qrcode.js</code> — both loaded via CDN.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('[name=template_html]').value=atob(document.querySelector('[name=template_html]').value)">Decode Base64</button>
                <button type="submit" class="btn btn-primary">Save Template</button>
            </div>
        </form>
    </div></div>
</div>
<script>
function insertPlaceholder(html) {
    const ta = document.querySelector('[name=template_html]');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + html + ta.value.substring(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + html.length;
}
function editTemplate(id) {
    fetch('/master/coa-templates/'+id+'/edit').then(r=>r.json()).then(d=>{
        const m=document.getElementById('templateModal');
        m.querySelector('.modal-title').textContent='Edit Template';
        const f=m.querySelector('form'); f.action='/master/coa-templates/'+id;
        f.querySelector('[name=template_name]').value=d.template_name||'';
        f.querySelector('[name=template_html]').value=d.template_html||'';
        f.querySelector('[name=is_default]').value=d.is_default?'1':'0';
        f.querySelector('[name=is_active]').value=d.is_active?'1':'0';
        f.querySelector('[name=page_size]').value=d.page_size||'A4';
        f.querySelector('[name=orientation]').value=d.orientation||'portrait';
        f.querySelector('[name=margin_top]').value=d.margin_top||15;
        f.querySelector('[name=margin_bottom]').value=d.margin_bottom||15;
        f.querySelector('[name=margin_left]').value=d.margin_left||15;
        f.querySelector('[name=margin_right]').value=d.margin_right||15;
        f.querySelector('[name=logo_path]').value=d.logo_path||'';
        f.querySelector('[name=scada_logo_path]').value=d.scada_logo_path||'';
        f.querySelector('[name=watermark_text]').value=d.watermark_text||'';
        f.querySelector('[name=show_qr_code]').value=d.show_qr_code?'1':'0';
        f.querySelector('[name=show_barcode]').value=d.show_barcode?'1':'0';
        f.querySelector('[name=show_signature]').value=d.show_signature?'1':'0';
        new bootstrap.Modal(m).show();
    });
}
function previewTemplate(id) {
    window.open('/master/coa-templates/'+id+'/preview','_blank','width=800,height=600');
}
</script>
