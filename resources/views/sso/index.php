<?php $title = 'SSO / LDAP Configuration'; layout('app'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="page-title mb-0"><i class="bi bi-shield-lock me-2"></i>SSO / LDAP Configuration</h4>
</div>

<ul class="nav nav-tabs mb-4" id="ssoTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= ($activeTab ?? 'saml') === 'saml' ? 'active' : '' ?>" id="saml-tab" data-bs-toggle="tab" data-bs-target="#saml" type="button" role="tab"><i class="bi bi-shield-check me-1"></i>SAML</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= ($activeTab ?? '') === 'ldap' ? 'active' : '' ?>" id="ldap-tab" data-bs-toggle="tab" data-bs-target="#ldap" type="button" role="tab"><i class="bi bi-server me-1"></i>LDAP</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= ($activeTab ?? '') === 'oauth' ? 'active' : '' ?>" id="oauth-tab" data-bs-toggle="tab" data-bs-target="#oauth" type="button" role="tab"><i class="bi bi-google me-1"></i>OAuth</button>
    </li>
</ul>

<div class="tab-content">
    <!-- SAML -->
    <div class="tab-pane fade <?= ($activeTab ?? 'saml') === 'saml' ? 'show active' : '' ?>" id="saml" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check me-1"></i>SAML Configuration</h5></div>
            <div class="card-body">
                <form method="POST" action="/sso/config/saml">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Entity ID <span class="text-danger">*</span></label>
                            <input type="text" name="saml_entity_id" class="form-control" value="<?= e($config['saml_entity_id'] ?? '') ?>" placeholder="https://your-lims.com/saml/metadata">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ACS URL <span class="text-danger">*</span></label>
                            <input type="url" name="saml_acs_url" class="form-control" value="<?= e($config['saml_acs_url'] ?? '') ?>" placeholder="https://your-lims.com/saml/acs">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IdP SSO URL <span class="text-danger">*</span></label>
                            <input type="url" name="saml_idp_sso_url" class="form-control" value="<?= e($config['saml_idp_sso_url'] ?? '') ?>" placeholder="https://idp.company.com/saml2/sso">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IdP Entity ID</label>
                            <input type="text" name="saml_idp_entity_id" class="form-control" value="<?= e($config['saml_idp_entity_id'] ?? '') ?>" placeholder="https://idp.company.com/metadata">
                        </div>
                        <div class="col-12">
                            <label class="form-label">IdP Certificate (x509)</label>
                            <textarea name="saml_idp_cert" class="form-control" rows="5" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"><?= e($config['saml_idp_cert'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SP Private Key</label>
                            <textarea name="saml_sp_private_key" class="form-control" rows="5" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"><?= e($config['saml_sp_private_key'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">SP Certificate</label>
                            <textarea name="saml_sp_cert" class="form-control" rows="5" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"><?= e($config['saml_sp_cert'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Name ID Format</label>
                            <select name="saml_nameid_format" class="form-select">
                                <option value="emailAddress" <?= ($config['saml_nameid_format'] ?? '') === 'emailAddress' ? 'selected' : '' ?>>Email Address</option>
                                <option value="unspecified" <?= ($config['saml_nameid_format'] ?? '') === 'unspecified' ? 'selected' : '' ?>>Unspecified</option>
                                <option value="persistent" <?= ($config['saml_nameid_format'] ?? '') === 'persistent' ? 'selected' : '' ?>>Persistent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Auto-Provision Users</label>
                            <select name="saml_auto_provision" class="form-select">
                                <option value="1" <?= ($config['saml_auto_provision'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['saml_auto_provision'] ?? '') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enabled</label>
                            <select name="saml_enabled" class="form-select">
                                <option value="1" <?= ($config['saml_enabled'] ?? '') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['saml_enabled'] ?? '0') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save SAML Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LDAP -->
    <div class="tab-pane fade <?= ($activeTab ?? '') === 'ldap' ? 'show active' : '' ?>" id="ldap" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-server me-1"></i>LDAP Configuration</h5></div>
            <div class="card-body">
                <form method="POST" action="/sso/config/ldap">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">LDAP Host <span class="text-danger">*</span></label>
                            <input type="text" name="ldap_host" class="form-control" value="<?= e($config['ldap_host'] ?? '') ?>" placeholder="ldap.company.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Port</label>
                            <input type="number" name="ldap_port" class="form-control" value="<?= e($config['ldap_port'] ?? '389') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Encryption</label>
                            <select name="ldap_encryption" class="form-select">
                                <option value="none" <?= ($config['ldap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="ssl" <?= ($config['ldap_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (LDAPS)</option>
                                <option value="tls" <?= ($config['ldap_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>Start TLS</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Base DN <span class="text-danger">*</span></label>
                            <input type="text" name="ldap_base_dn" class="form-control" value="<?= e($config['ldap_base_dn'] ?? '') ?>" placeholder="dc=company,dc=com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bind DN</label>
                            <input type="text" name="ldap_bind_dn" class="form-control" value="<?= e($config['ldap_bind_dn'] ?? '') ?>" placeholder="cn=admin,dc=company,dc=com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bind Password</label>
                            <input type="password" name="ldap_bind_password" class="form-control" placeholder="(leave blank to keep existing)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">User Search Filter</label>
                            <input type="text" name="ldap_user_filter" class="form-control" value="<?= e($config['ldap_user_filter'] ?? '(objectClass=person)') ?>" placeholder="(objectClass=person)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Login Attribute</label>
                            <input type="text" name="ldap_login_attribute" class="form-control" value="<?= e($config['ldap_login_attribute'] ?? 'uid') ?>" placeholder="uid / sAMAccountName / mail">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Auto-Provision Users</label>
                            <select name="ldap_auto_provision" class="form-select">
                                <option value="1" <?= ($config['ldap_auto_provision'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['ldap_auto_provision'] ?? '') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Role</label>
                            <select name="ldap_default_role" class="form-select">
                                <option value="Analyst" <?= ($config['ldap_default_role'] ?? '') === 'Analyst' ? 'selected' : '' ?>>Analyst</option>
                                <option value="Reviewer" <?= ($config['ldap_default_role'] ?? '') === 'Reviewer' ? 'selected' : '' ?>>Reviewer</option>
                                <option value="Approver" <?= ($config['ldap_default_role'] ?? '') === 'Approver' ? 'selected' : '' ?>>Approver</option>
                                <option value="Admin" <?= ($config['ldap_default_role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enabled</label>
                            <select name="ldap_enabled" class="form-select">
                                <option value="1" <?= ($config['ldap_enabled'] ?? '') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['ldap_enabled'] ?? '0') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save LDAP Configuration</button>
                        <button type="button" class="btn btn-outline-info" onclick="testLdapConnection()"><i class="bi bi-plug"></i> Test Connection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- OAuth -->
    <div class="tab-pane fade <?= ($activeTab ?? '') === 'oauth' ? 'show active' : '' ?>" id="oauth" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-google me-1"></i>OAuth Configuration</h5></div>
            <div class="card-body">
                <form method="POST" action="/sso/config/oauth">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Provider Name</label>
                            <select name="oauth_provider" class="form-select">
                                <option value="google" <?= ($config['oauth_provider'] ?? '') === 'google' ? 'selected' : '' ?>>Google</option>
                                <option value="microsoft" <?= ($config['oauth_provider'] ?? '') === 'microsoft' ? 'selected' : '' ?>>Microsoft Azure AD</option>
                                <option value="github" <?= ($config['oauth_provider'] ?? '') === 'github' ? 'selected' : '' ?>>GitHub</option>
                                <option value="okta" <?= ($config['oauth_provider'] ?? '') === 'okta' ? 'selected' : '' ?>>Okta</option>
                                <option value="custom" <?= ($config['oauth_provider'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom OIDC</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client ID <span class="text-danger">*</span></label>
                            <input type="text" name="oauth_client_id" class="form-control" value="<?= e($config['oauth_client_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client Secret <span class="text-danger">*</span></label>
                            <input type="password" name="oauth_client_secret" class="form-control" placeholder="(leave blank to keep existing)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Redirect URI</label>
                            <input type="url" name="oauth_redirect_uri" class="form-control" value="<?= e($config['oauth_redirect_uri'] ?? '') ?>" placeholder="https://your-lims.com/sso/oauth/callback">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Authorization URL (custom OIDC)</label>
                            <input type="url" name="oauth_auth_url" class="form-control" value="<?= e($config['oauth_auth_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token URL (custom OIDC)</label>
                            <input type="url" name="oauth_token_url" class="form-control" value="<?= e($config['oauth_token_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User Info URL (custom OIDC)</label>
                            <input type="url" name="oauth_userinfo_url" class="form-control" value="<?= e($config['oauth_userinfo_url'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scopes</label>
                            <input type="text" name="oauth_scopes" class="form-control" value="<?= e($config['oauth_scopes'] ?? 'openid email profile') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Auto-Provision Users</label>
                            <select name="oauth_auto_provision" class="form-select">
                                <option value="1" <?= ($config['oauth_auto_provision'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['oauth_auto_provision'] ?? '') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Role</label>
                            <select name="oauth_default_role" class="form-select">
                                <option value="Analyst" <?= ($config['oauth_default_role'] ?? '') === 'Analyst' ? 'selected' : '' ?>>Analyst</option>
                                <option value="Reviewer" <?= ($config['oauth_default_role'] ?? '') === 'Reviewer' ? 'selected' : '' ?>>Reviewer</option>
                                <option value="Approver" <?= ($config['oauth_default_role'] ?? '') === 'Approver' ? 'selected' : '' ?>>Approver</option>
                                <option value="Admin" <?= ($config['oauth_default_role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enabled</label>
                            <select name="oauth_enabled" class="form-select">
                                <option value="1" <?= ($config['oauth_enabled'] ?? '') == '1' ? 'selected' : '' ?>>Enabled</option>
                                <option value="0" <?= ($config['oauth_enabled'] ?? '0') == '0' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save OAuth Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function testLdapConnection() {
    fetch('/sso/config/ldap/test', { method: 'POST', headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' } })
        .then(r => r.json())
        .then(d => alert(d.success ? 'Connection successful!' : 'Connection failed: ' + d.message))
        .catch(() => alert('Test failed.'));
}
</script>
