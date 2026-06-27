<?php $title = 'Email Configuration'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="page-title mb-0"><i class="bi bi-envelope me-2"></i>Email Configuration</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="bi bi-plus"></i> Add Config</button>
</div>
<div class="row g-3">
    <?php foreach ($configs as $cfg): ?>
    <div class="col-md-6">
        <div class="card h-100 <?= $cfg['is_default'] ? 'border-success' : '' ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-envelope me-1"></i><?= e($cfg['config_name']) ?></span>
                <?php if ($cfg['is_default']): ?><span class="badge bg-success">Default</span><?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row mb-2"><div class="col-4 text-muted small">SMTP Host:</div><div class="col-8"><?= e($cfg['smtp_host']) ?>:<?= e($cfg['smtp_port']) ?></div></div>
                <div class="row mb-2"><div class="col-4 text-muted small">Encryption:</div><div class="col-8"><?= e($cfg['smtp_encryption']) ?></div></div>
                <div class="row mb-2"><div class="col-4 text-muted small">Username:</div><div class="col-8"><?= e($cfg['smtp_username'] ?? '(none)') ?></div></div>
                <div class="row mb-2"><div class="col-4 text-muted small">From:</div><div class="col-8"><?= e($cfg['from_name'] ?? '') ?> &lt;<?= e($cfg['from_address']) ?>&gt;</div></div>
                <div class="mt-3 d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="editEmail(<?= $cfg['id'] ?>)"><i class="bi bi-pencil"></i> Edit</button>
                    <form method="POST" action="/master/email-config/<?= $cfg['id'] ?>/default" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success" <?= $cfg['is_default']?'disabled':'' ?>><i class="bi bi-star"></i> Set Default</button>
                    </form>
                    <button class="btn btn-sm btn-outline-info" onclick="testEmail(<?= $cfg['id'] ?>)"><i class="bi bi-send"></i> Test</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="/master/email-config">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Email Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Config Name</label><input type="text" name="config_name" class="form-control" required></div>
                <div class="row"><div class="col-md-8 mb-3"><label class="form-label">SMTP Host *</label><input type="text" name="smtp_host" class="form-control" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Port</label><input type="number" name="smtp_port" class="form-control" value="587"></div></div>
                <div class="row"><div class="col-md-4 mb-3"><label class="form-label">Encryption</label><select name="smtp_encryption" class="form-select"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></div>
                <div class="col-md-8 mb-3"><label class="form-label">Username</label><input type="text" name="smtp_username" class="form-control"></div></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="smtp_password" class="form-control" placeholder="Leave blank to keep current"></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">From Address *</label><input type="email" name="from_address" class="form-control" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">From Name</label><input type="text" name="from_name" class="form-control"></div></div>
                <div class="form-check"><input type="checkbox" name="is_default" class="form-check-input" value="1" id="defEmail"><label class="form-check-label" for="defEmail">Set as default</label></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div></div>
</div>
<script>
function editEmail(id) {
    fetch('/master/email-config/'+id+'/edit').then(r=>r.json()).then(d=>{
        const m=document.getElementById('emailModal');
        m.querySelector('.modal-title').textContent='Edit Email Config';
        const f=m.querySelector('form'); f.action='/master/email-config/'+id;
        f.querySelector('[name=config_name]').value=d.config_name;
        f.querySelector('[name=smtp_host]').value=d.smtp_host;
        f.querySelector('[name=smtp_port]').value=d.smtp_port;
        f.querySelector('[name=smtp_encryption]').value=d.smtp_encryption;
        f.querySelector('[name=smtp_username]').value=d.smtp_username||'';
        f.querySelector('[name=smtp_password]').value='';
        f.querySelector('[name=from_address]').value=d.from_address;
        f.querySelector('[name=from_name]').value=d.from_name||'';
        f.querySelector('[name=is_default]').checked=d.is_default;
        new bootstrap.Modal(m).show();
    });
}
function testEmail(id) {
    fetch('/master/email-config/'+id+'/test').then(r=>r.json()).then(d=>{
        alert(d.message || d.error || 'Test complete');
    }).catch(()=>alert('Test failed'));
}
</script>
