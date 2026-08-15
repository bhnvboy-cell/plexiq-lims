<?php

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\SampleController;
use App\Controllers\TestResultController;
use App\Controllers\CoaController;
use App\Controllers\MasterDataController;
use App\Controllers\UserController;
use App\Controllers\AuditController;
use App\Controllers\SapController;
use App\Controllers\BatchController;
use App\Controllers\WorkspaceController;
use App\Controllers\LabelController;
use App\Controllers\InstallerController;
use App\Middleware\AuthMiddleware;

/** @var Router $router */

// ============================================================
// AUTH ROUTES
// ============================================================
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/login/2fa', [AuthController::class, 'showTwoFactor'], ['guest']);
$router->post('/login/2fa', [AuthController::class, 'verifyTwoFactor'], ['guest']);
$router->post('/login/2fa/cancel', [AuthController::class, 'cancelTwoFactor'], ['guest']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/profile', [AuthController::class, 'profile'], ['auth']);
$router->get('/profile/2fa/setup', [AuthController::class, 'setupTwoFactor'], ['auth']);
$router->post('/profile/2fa/enable', [AuthController::class, 'enableTwoFactor'], ['auth']);
$router->post('/profile/2fa/disable', [AuthController::class, 'disableTwoFactor'], ['auth']);

// ============================================================
// DASHBOARD
// ============================================================
$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// ============================================================
// SAMPLES
// ============================================================
$router->get('/samples', [SampleController::class, 'index'], ['auth']);
$router->get('/samples/create', [SampleController::class, 'create'], ['auth']);
$router->post('/samples', [SampleController::class, 'store'], ['auth']);
$router->get('/samples/{id}', [SampleController::class, 'show'], ['auth']);
$router->get('/samples/{id}/edit', [SampleController::class, 'edit'], ['auth']);
$router->post('/samples/{id}', [SampleController::class, 'update'], ['auth']);
$router->post('/samples/{id}/workflow', [SampleController::class, 'updateWorkflow'], ['auth']);
$router->post('/samples/{id}/assign-tests', [SampleController::class, 'assignTests'], ['auth']);

// ============================================================
// TEST & RESULTS
// ============================================================
$router->get('/tests/pending', [TestResultController::class, 'pendingResults'], ['auth']);
$router->get('/tests/{id}/result', [TestResultController::class, 'enterResult'], ['auth']);
$router->post('/tests/{id}/result', [TestResultController::class, 'saveResult'], ['auth']);
$router->get('/tests/review', [TestResultController::class, 'reviewResults'], ['auth']);
$router->post('/tests/{id}/review', [TestResultController::class, 'approveResult'], ['auth']);
$router->get('/tests/final-approval', [TestResultController::class, 'finalApproval'], ['auth']);
$router->post('/tests/{id}/final-approve', [TestResultController::class, 'finalApproveResult'], ['auth']);

// ============================================================
// INSTRUMENT INTEGRATION
// ============================================================
$router->get('/instruments', [\App\Controllers\InstrumentController::class, 'index'], ['auth']);
$router->get('/instruments/create', [\App\Controllers\InstrumentController::class, 'create'], ['auth']);
$router->post('/instruments/store', [\App\Controllers\InstrumentController::class, 'store'], ['auth']);
$router->get('/instruments/{id}/edit', [\App\Controllers\InstrumentController::class, 'edit'], ['auth']);
$router->post('/instruments/{id}/update', [\App\Controllers\InstrumentController::class, 'update'], ['auth']);
$router->post('/instruments/{id}/delete', [\App\Controllers\InstrumentController::class, 'delete'], ['auth']);
$router->get('/instruments/{id}/import', [\App\Controllers\InstrumentController::class, 'import'], ['auth']);
$router->post('/instruments/{id}/upload', [\App\Controllers\InstrumentController::class, 'upload'], ['auth']);
$router->get('/instruments/results', [\App\Controllers\InstrumentController::class, 'results'], ['auth']);
$router->post('/instruments/results/{id}/match', [\App\Controllers\InstrumentController::class, 'match'], ['auth']);
$router->post('/instruments/results/match-all', [\App\Controllers\InstrumentController::class, 'matchAll'], ['auth']);

// ============================================================
// COA (Certificate of Analysis)
// ============================================================
$router->get('/coa', [CoaController::class, 'index'], ['auth']);
$router->post('/coa/generate/{id}', [CoaController::class, 'generate'], ['auth']);
$router->get('/coa/{id}', [CoaController::class, 'view'], ['auth']);
$router->get('/coa/{id}/pdf', [CoaController::class, 'downloadPdf'], ['auth']);
$router->post('/coa/{id}/release', [CoaController::class, 'release'], ['auth']);

// ============================================================
// MASTER DATA CONTROL PANEL
// ============================================================
$router->get('/master', [MasterDataController::class, 'controlPanel'], ['auth']);
$router->get('/master/search', [MasterDataController::class, 'search'], ['auth']);
$router->get('/master/export/{table}', [MasterDataController::class, 'export'], ['auth']);

// Customers
$router->get('/master/customers', [MasterDataController::class, 'customers'], ['auth']);
$router->get('/master/customers/create', [MasterDataController::class, 'createCustomer'], ['auth']);
$router->post('/master/customers', [MasterDataController::class, 'storeCustomer'], ['auth']);
$router->get('/master/customers/{id}/edit', [MasterDataController::class, 'editCustomer'], ['auth']);
$router->post('/master/customers/{id}', [MasterDataController::class, 'updateCustomer'], ['auth']);
$router->post('/master/customers/{id}/delete', [MasterDataController::class, 'deleteCustomer'], ['auth']);

// Products
$router->get('/master/products', [MasterDataController::class, 'products'], ['auth']);
$router->get('/master/products/create', [MasterDataController::class, 'createProduct'], ['auth']);
$router->post('/master/products', [MasterDataController::class, 'storeProduct'], ['auth']);
$router->get('/master/products/{id}/edit', [MasterDataController::class, 'editProduct'], ['auth']);
$router->post('/master/products/{id}', [MasterDataController::class, 'updateProduct'], ['auth']);

// Test Parameters
$router->get('/master/tests', [MasterDataController::class, 'tests'], ['auth']);
$router->get('/master/tests/create', [MasterDataController::class, 'createTest'], ['auth']);
$router->post('/master/tests', [MasterDataController::class, 'storeTest'], ['auth']);
$router->get('/master/tests/{id}/edit', [MasterDataController::class, 'editTest'], ['auth']);
$router->post('/master/tests/{id}', [MasterDataController::class, 'updateTest'], ['auth']);

// Methods (Tile Format)
$router->get('/master/methods', [MasterDataController::class, 'methods'], ['auth']);
$router->post('/master/methods', [MasterDataController::class, 'createMethod'], ['auth']);
$router->get('/master/methods/{id}/edit', [MasterDataController::class, 'editMethodJson'], ['auth']);
$router->post('/master/methods/{id}', [MasterDataController::class, 'updateMethod'], ['auth']);
$router->post('/master/methods/{id}/delete', [MasterDataController::class, 'deleteMethod'], ['auth']);

// Units
$router->get('/master/units', [MasterDataController::class, 'units'], ['auth']);
$router->post('/master/units', [MasterDataController::class, 'createUnit'], ['auth']);

// Sample Types
$router->get('/master/sample-types', [MasterDataController::class, 'sampleTypes'], ['auth']);
$router->post('/master/sample-types', [MasterDataController::class, 'storeSampleType'], ['auth']);
$router->get('/master/sample-types/{id}/edit', [MasterDataController::class, 'editSampleTypeJson'], ['auth']);
$router->post('/master/sample-types/{id}', [MasterDataController::class, 'updateSampleType'], ['auth']);
$router->post('/master/sample-types/{id}/toggle', [MasterDataController::class, 'toggleSampleType'], ['auth']);

// Instrument Locations
$router->get('/master/instrument-locations', [MasterDataController::class, 'instrumentLocations'], ['auth']);
$router->post('/master/instrument-locations', [MasterDataController::class, 'storeInstrumentLocation'], ['auth']);
$router->get('/master/instrument-locations/{id}/edit', [MasterDataController::class, 'editInstrumentLocationJson'], ['auth']);
$router->post('/master/instrument-locations/{id}', [MasterDataController::class, 'updateInstrumentLocation'], ['auth']);
$router->post('/master/instrument-locations/{id}/toggle', [MasterDataController::class, 'toggleInstrumentLocation'], ['auth']);

// Instrument Calibrations
$router->get('/master/calibrations', [MasterDataController::class, 'calibrations'], ['auth']);
$router->get('/master/calibrations/create', [MasterDataController::class, 'createCalibration'], ['auth']);
$router->post('/master/calibrations', [MasterDataController::class, 'storeCalibration'], ['auth']);

// Chemical Inventory
$router->get('/master/chemical-inventory', [MasterDataController::class, 'chemicalInventory'], ['auth']);
$router->post('/master/chemical-inventory', [MasterDataController::class, 'storeChemical'], ['auth']);
$router->get('/master/chemical-inventory/{id}/edit', [MasterDataController::class, 'editChemicalJson'], ['auth']);
$router->post('/master/chemical-inventory/{id}', [MasterDataController::class, 'updateChemical'], ['auth']);
$router->post('/master/chemical-inventory/{id}/adjust', [MasterDataController::class, 'adjustChemical'], ['auth']);

// COA Template Customizer
$router->get('/master/coa-templates', [MasterDataController::class, 'coaTemplates'], ['auth']);
$router->post('/master/coa-templates', [MasterDataController::class, 'storeCoaTemplate'], ['auth']);
$router->get('/master/coa-templates/{id}/edit', [MasterDataController::class, 'editCoaTemplateJson'], ['auth']);
$router->post('/master/coa-templates/{id}', [MasterDataController::class, 'updateCoaTemplate'], ['auth']);
$router->post('/master/coa-templates/{id}/default', [MasterDataController::class, 'setDefaultCoaTemplate'], ['auth']);
$router->get('/master/coa-templates/{id}/preview', [MasterDataController::class, 'previewCoaTemplate'], ['auth']);

// Email Configuration
$router->get('/master/email-config', [MasterDataController::class, 'emailConfig'], ['auth']);
$router->post('/master/email-config', [MasterDataController::class, 'storeEmailConfig'], ['auth']);
$router->get('/master/email-config/{id}/edit', [MasterDataController::class, 'editEmailConfigJson'], ['auth']);
$router->post('/master/email-config/{id}', [MasterDataController::class, 'updateEmailConfig'], ['auth']);
$router->post('/master/email-config/{id}/default', [MasterDataController::class, 'setDefaultEmailConfig'], ['auth']);
$router->get('/master/email-config/{id}/test', [MasterDataController::class, 'testEmailConfig'], ['auth']);

// Manufacturers
$router->get('/master/manufacturers', [MasterDataController::class, 'manufacturers'], ['auth']);
$router->get('/master/manufacturers/create', [MasterDataController::class, 'createManufacturer'], ['auth']);
$router->post('/master/manufacturers', [MasterDataController::class, 'storeManufacturer'], ['auth']);
$router->get('/master/manufacturers/{id}/edit', [MasterDataController::class, 'editManufacturer'], ['auth']);
$router->post('/master/manufacturers/{id}', [MasterDataController::class, 'updateManufacturer'], ['auth']);
$router->post('/master/manufacturers/{id}/delete', [MasterDataController::class, 'deleteManufacturer'], ['auth']);

// ============================================================
// USER MANAGEMENT
// ============================================================
$router->get('/users', [UserController::class, 'index'], ['auth']);
$router->get('/users/create', [UserController::class, 'create'], ['auth']);
$router->post('/users', [UserController::class, 'store'], ['auth']);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], ['auth']);
$router->post('/users/{id}', [UserController::class, 'update'], ['auth']);

// ============================================================
// AUDIT TRAIL
// ============================================================
$router->get('/audit', [AuditController::class, 'index'], ['auth']);
$router->get('/audit/login-history', [UserController::class, 'loginHistory'], ['auth']);

// ============================================================
// SAP HANA SYNC
// ============================================================
$router->get('/sap', [SapController::class, 'index'], ['auth']);
$router->post('/sap/config', [SapController::class, 'updateConfig'], ['auth']);
$router->post('/sap/sync/push/{type}', [SapController::class, 'syncPush'], ['auth']);
$router->post('/sap/sync/pull/{type}', [SapController::class, 'syncPull'], ['auth']);
$router->post('/sap/sync/push-all', [SapController::class, 'syncAllPush'], ['auth']);
$router->post('/sap/sync/pull-all', [SapController::class, 'syncAllPull'], ['auth']);
$router->get('/sap/status', [SapController::class, 'syncStatus'], ['auth']);

// ============================================================
// CLIENT PORTAL (Customer Self-Service)
// ============================================================
$router->get('/client/login', [\App\Controllers\ClientController::class, 'showLogin'], ['guest']);
$router->post('/client/login', [\App\Controllers\ClientController::class, 'login'], ['guest']);
$router->get('/client/logout', [\App\Controllers\ClientController::class, 'logout']);
$router->get('/client/register', [\App\Controllers\ClientController::class, 'register'], ['guest']);
$router->post('/client/register', [\App\Controllers\ClientController::class, 'store'], ['guest']);
$router->get('/client/dashboard', [\App\Controllers\ClientController::class, 'dashboard'], ['auth']);
$router->get('/client/coa/{id}', [\App\Controllers\ClientController::class, 'viewCoa'], ['auth']);
$router->get('/client/coa/{id}/pdf', [\App\Controllers\ClientController::class, 'downloadPdf'], ['auth']);

// ============================================================
// SPC (Statistical Process Control)
// ============================================================
$router->get('/spc', [\App\Controllers\SpcController::class, 'index'], ['auth']);
$router->get('/spc/{id}', [\App\Controllers\SpcController::class, 'detail'], ['auth']);
$router->get('/spc/{id}/calculate', [\App\Controllers\SpcController::class, 'calculate'], ['auth']);
$router->post('/spc/{id}/readings', [\App\Controllers\SpcController::class, 'storeReading'], ['auth']);

// ============================================================
// QUALITY CONTROL (Levey-Jennings / Westgard)
// ============================================================
$router->get('/qc', [\App\Controllers\QcController::class, 'index'], ['auth']);
$router->post('/qc/create', [\App\Controllers\QcController::class, 'create'], ['auth']);
$router->get('/qc/{id}', [\App\Controllers\QcController::class, 'detail'], ['auth']);
$router->post('/qc/{id}/results', [\App\Controllers\QcController::class, 'storeResult'], ['auth']);
$router->get('/qc/{id}/assess', [\App\Controllers\QcController::class, 'assess'], ['auth']);
$router->post('/qc/{id}/delete', [\App\Controllers\QcController::class, 'delete'], ['auth']);

// ============================================================
// CHAIN OF CUSTODY
// ============================================================
$router->get('/coc', [\App\Controllers\CocController::class, 'index'], ['auth']);
$router->post('/samples/{id}/coc', [\App\Controllers\CocController::class, 'store'], ['auth']);
$router->post('/coc/{id}/receive', [\App\Controllers\CocController::class, 'receive'], ['auth']);
$router->post('/coc/{id}/delete', [\App\Controllers\CocController::class, 'delete'], ['auth']);

// ============================================================
// PROJECTS
// ============================================================
$router->get('/projects', [\App\Controllers\ProjectController::class, 'index'], ['auth']);
$router->get('/projects/create', [\App\Controllers\ProjectController::class, 'create'], ['auth']);
$router->post('/projects/store', [\App\Controllers\ProjectController::class, 'store'], ['auth']);
$router->get('/projects/{id}', [\App\Controllers\ProjectController::class, 'show'], ['auth']);
$router->get('/projects/{id}/edit', [\App\Controllers\ProjectController::class, 'edit'], ['auth']);
$router->post('/projects/{id}/update', [\App\Controllers\ProjectController::class, 'update'], ['auth']);
$router->post('/projects/{id}/delete', [\App\Controllers\ProjectController::class, 'delete'], ['auth']);
$router->post('/projects/{id}/samples', [\App\Controllers\ProjectController::class, 'addSample'], ['auth']);
$router->post('/projects/{id}/samples/{sid}/remove', [\App\Controllers\ProjectController::class, 'removeSample'], ['auth']);

// ============================================================
// OOS (OUT OF SPECIFICATION)
// ============================================================
$router->get('/oos', [\App\Controllers\OosController::class, 'index'], ['auth']);
$router->get('/oos/create', [\App\Controllers\OosController::class, 'create'], ['auth']);
$router->post('/oos/store', [\App\Controllers\OosController::class, 'store'], ['auth']);
$router->get('/oos/{id}', [\App\Controllers\OosController::class, 'show'], ['auth']);
$router->get('/oos/{id}/edit', [\App\Controllers\OosController::class, 'edit'], ['auth']);
$router->post('/oos/{id}/update', [\App\Controllers\OosController::class, 'update'], ['auth']);
$router->post('/oos/{id}/investigate', [\App\Controllers\OosController::class, 'investigate'], ['auth']);
$router->post('/oos/{id}/review', [\App\Controllers\OosController::class, 'review'], ['auth']);
$router->post('/oos/{id}/close', [\App\Controllers\OosController::class, 'close'], ['auth']);
$router->post('/oos/{id}/delete', [\App\Controllers\OosController::class, 'delete'], ['auth']);

// ============================================================
// ANALYSIS PARAMETERS, RESULT WORKFLOW & INSTRUMENT MAPPING
// ============================================================
$router->get('/analysis-parameters', [\App\Controllers\AnalysisParameterController::class, 'index'], ['auth']);
$router->get('/analysis-parameters/create', [\App\Controllers\AnalysisParameterController::class, 'create'], ['auth']);
$router->post('/analysis-parameters', [\App\Controllers\AnalysisParameterController::class, 'store'], ['auth']);
$router->get('/analysis-parameters/{id}/edit', [\App\Controllers\AnalysisParameterController::class, 'edit'], ['auth']);
$router->post('/analysis-parameters/{id}', [\App\Controllers\AnalysisParameterController::class, 'update'], ['auth']);
$router->post('/analysis-parameters/{id}/delete', [\App\Controllers\AnalysisParameterController::class, 'delete'], ['auth']);
$router->get('/samples/{id}/parameters', [\App\Controllers\AnalysisParameterController::class, 'assignPage'], ['auth']);
$router->post('/samples/{id}/parameters', [\App\Controllers\AnalysisParameterController::class, 'assign'], ['auth']);
$router->get('/samples/{id}/parameters/entries', [\App\Controllers\AnalysisParameterController::class, 'samplePage'], ['auth']);
$router->post('/analysis-results/{id}/record', [\App\Controllers\AnalysisParameterController::class, 'recordResult'], ['auth']);
$router->post('/analysis-results/{id}/review', [\App\Controllers\AnalysisParameterController::class, 'review'], ['auth']);
$router->post('/analysis-results/{id}/approve', [\App\Controllers\AnalysisParameterController::class, 'approve'], ['auth']);
$router->get('/instruments/{id}/mappings', [\App\Controllers\AnalysisParameterController::class, 'mappings'], ['auth']);
$router->post('/instruments/{id}/mappings', [\App\Controllers\AnalysisParameterController::class, 'storeMapping'], ['auth']);
$router->post('/instruments/mappings/{id}/delete', [\App\Controllers\AnalysisParameterController::class, 'deleteMapping'], ['auth']);
$router->post('/instruments/{id}/import-async', [\App\Controllers\AnalysisParameterController::class, 'upload'], ['auth']);
$router->post('/instruments/scan', [\App\Controllers\AnalysisParameterController::class, 'scanWatchPaths'], ['auth']);
$router->get('/instruments/imports', [\App\Controllers\AnalysisParameterController::class, 'importedResults'], ['auth']);

// ============================================================
// CAPA (CORRECTIVE & PREVENTIVE ACTION)
// ============================================================
$router->get('/capa', [\App\Controllers\CapaController::class, 'index'], ['auth']);
$router->get('/capa/create', [\App\Controllers\CapaController::class, 'create'], ['auth']);
$router->post('/capa/store', [\App\Controllers\CapaController::class, 'store'], ['auth']);
$router->get('/capa/{id}', [\App\Controllers\CapaController::class, 'show'], ['auth']);
$router->get('/capa/{id}/edit', [\App\Controllers\CapaController::class, 'edit'], ['auth']);
$router->post('/capa/{id}/update', [\App\Controllers\CapaController::class, 'update'], ['auth']);
$router->post('/capa/{id}/status', [\App\Controllers\CapaController::class, 'updateStatus'], ['auth']);
$router->post('/capa/{id}/delete', [\App\Controllers\CapaController::class, 'delete'], ['auth']);

// ============================================================
// BATCH MANAGEMENT
// ============================================================
$router->get('/batches', [BatchController::class, 'index'], ['auth']);
$router->get('/batches/create', [BatchController::class, 'create'], ['auth']);
$router->post('/batches', [BatchController::class, 'store'], ['auth']);
$router->get('/batches/{id}', [BatchController::class, 'show'], ['auth']);
$router->get('/batches/{id}/edit', [BatchController::class, 'edit'], ['auth']);
$router->post('/batches/{id}', [BatchController::class, 'update'], ['auth']);
$router->post('/batches/{id}/workflow', [BatchController::class, 'updateWorkflow'], ['auth']);
$router->post('/batches/{id}/add-sample', [BatchController::class, 'addSample'], ['auth']);
$router->post('/batches/{id}/add-tests', [BatchController::class, 'addTests'], ['auth']);
$router->post('/batches/retest/{sampleTestId}', [BatchController::class, 'retest'], ['auth']);
$router->post('/batches/remove-test/{sampleTestId}', [BatchController::class, 'removeTest'], ['auth']);
$router->get('/batches/product-tests/{productId}', [BatchController::class, 'getProductTestsJson'], ['auth']);

// ============================================================
// PRODUCT-TEST MAPPING (Master Data)
// ============================================================
$router->get('/master/product-tests', [MasterDataController::class, 'productTests'], ['auth']);
$router->post('/master/product-tests', [MasterDataController::class, 'storeProductTest'], ['auth']);
$router->get('/master/product-tests/{id}/edit', [MasterDataController::class, 'editProductTestJson'], ['auth']);
$router->post('/master/product-tests/{id}', [MasterDataController::class, 'updateProductTest'], ['auth']);
$router->post('/master/product-tests/{id}/delete', [MasterDataController::class, 'deleteProductTest'], ['auth']);

// ============================================================
// WORKSPACE (Shortcut Tiles)
// ============================================================
$router->get('/workspace', [WorkspaceController::class, 'index'], ['auth']);
$router->post('/workspace/shortcuts', [WorkspaceController::class, 'store'], ['auth']);
$router->post('/workspace/shortcuts/reorder', [WorkspaceController::class, 'reorder'], ['auth']);
$router->post('/workspace/shortcuts/{id}/delete', [WorkspaceController::class, 'destroy'], ['auth']);
$router->get('/workspace/icons', [WorkspaceController::class, 'icons'], ['auth']);

// ============================================================
// LABELS (Printing)
// ============================================================
$router->get('/labels/sample/{id}', [LabelController::class, 'printSampleLabel'], ['auth']);
$router->get('/labels/batch/{id}', [LabelController::class, 'printBatchLabels'], ['auth']);

// ============================================================
// INSTALLER BUILDER (Inno Setup)
// ============================================================
$router->get('/installer/builder', [InstallerController::class, 'builder'], ['auth']);
$router->post('/installer/build', [InstallerController::class, 'build'], ['auth']);
$router->get('/installer/download', [InstallerController::class, 'download'], ['auth']);
$router->get('/installer/log/{buildId}', [InstallerController::class, 'log'], ['auth']);
$router->get('/installer/history', [InstallerController::class, 'history'], ['auth']);

// ============================================================
// ELN (ELECTRONIC LAB NOTEBOOK)
// ============================================================
$router->get('/notebooks', [\App\Controllers\NotebookController::class, 'index'], ['auth']);
$router->get('/notebooks/create', [\App\Controllers\NotebookController::class, 'create'], ['auth']);
$router->post('/notebooks', [\App\Controllers\NotebookController::class, 'store'], ['auth']);
$router->get('/notebooks/{id}', [\App\Controllers\NotebookController::class, 'show'], ['auth']);
$router->get('/notebooks/{id}/edit', [\App\Controllers\NotebookController::class, 'edit'], ['auth']);
$router->post('/notebooks/{id}', [\App\Controllers\NotebookController::class, 'update'], ['auth']);
$router->post('/notebooks/{id}/delete', [\App\Controllers\NotebookController::class, 'deleteEntry'], ['auth']);
$router->get('/notebooks/{id}/entries/create', [\App\Controllers\NotebookController::class, 'createEntry'], ['auth']);
$router->post('/notebooks/{id}/entries', [\App\Controllers\NotebookController::class, 'storeEntry'], ['auth']);
$router->get('/entries/{id}/edit', [\App\Controllers\NotebookController::class, 'editEntry'], ['auth']);
$router->post('/entries/{id}', [\App\Controllers\NotebookController::class, 'updateEntry'], ['auth']);

// ============================================================
// NOTIFICATIONS
// ============================================================
$router->get('/notifications', [\App\Controllers\NotificationController::class, 'index'], ['auth']);
$router->post('/notifications/{id}/read', [\App\Controllers\NotificationController::class, 'markRead'], ['auth']);
$router->post('/notifications/read-all', [\App\Controllers\NotificationController::class, 'markAllRead'], ['auth']);
$router->get('/notifications/settings', [\App\Controllers\NotificationController::class, 'settings'], ['auth']);
$router->post('/notifications/settings', [\App\Controllers\NotificationController::class, 'updateSettings'], ['auth']);
$router->post('/notifications/test', [\App\Controllers\NotificationController::class, 'sendTest'], ['auth']);

// ============================================================
// API INTEGRATION (Tokens & Webhooks)
// ============================================================
$router->get('/api-management/tokens', [\App\Controllers\ApiIntegrationController::class, 'tokens'], ['auth']);
$router->post('/api-management/tokens', [\App\Controllers\ApiIntegrationController::class, 'createToken'], ['auth']);
$router->post('/api-management/tokens/{id}/revoke', [\App\Controllers\ApiIntegrationController::class, 'revokeToken'], ['auth']);
$router->get('/api-management/webhooks', [\App\Controllers\ApiIntegrationController::class, 'webhooks'], ['auth']);
$router->post('/api-management/webhooks', [\App\Controllers\ApiIntegrationController::class, 'createWebhook'], ['auth']);
$router->post('/api-management/webhooks/{id}/toggle', [\App\Controllers\ApiIntegrationController::class, 'toggleWebhook'], ['auth']);
$router->get('/api-management/webhooks/{id}/logs', [\App\Controllers\ApiIntegrationController::class, 'webhookLogs'], ['auth']);

// ============================================================
// BARCODE SCANNING
// ============================================================
$router->get('/barcode/scan', [\App\Controllers\BarcodeController::class, 'scan'], ['auth']);
$router->get('/barcode/lookup', [\App\Controllers\BarcodeController::class, 'lookup'], ['auth']);
$router->get('/barcode/logs', [\App\Controllers\BarcodeController::class, 'scanLog'], ['auth']);
$router->get('/barcode/print/{entityType}/{id}', [\App\Controllers\BarcodeController::class, 'printLabel'], ['auth']);

// ============================================================
// ELECTRONIC SIGNATURES (21 CFR Part 11)
// ============================================================
$router->post('/esign/sign/{entityType}/{entityId}', [\App\Controllers\ESignatureController::class, 'sign'], ['auth']);
$router->get('/esign/verify/{id}', [\App\Controllers\ESignatureController::class, 'verify'], ['auth']);
$router->get('/esign/audit', [\App\Controllers\ESignatureController::class, 'audit'], ['auth']);

// ============================================================
// SSO / LDAP / AUTHENTICATION
// ============================================================
$router->get('/sso', [\App\Controllers\SsoController::class, 'index'], ['auth']);
$router->post('/sso/config/{id}', [\App\Controllers\SsoController::class, 'updateConfig'], ['auth']);
$router->post('/sso/test/{id}', [\App\Controllers\SsoController::class, 'testConnection'], ['auth']);

// ============================================================
// LANGUAGE / I18N
// ============================================================
$router->get('/languages', [\App\Controllers\LanguageController::class, 'index'], ['auth']);
$router->post('/languages/switch/{code}', [\App\Controllers\LanguageController::class, 'switchLang'], ['auth']);
$router->post('/languages/translations', [\App\Controllers\LanguageController::class, 'createTranslation'], ['auth']);
$router->post('/languages/translations/{id}', [\App\Controllers\LanguageController::class, 'updateTranslation'], ['auth']);
$router->get('/languages/export/{id}', [\App\Controllers\LanguageController::class, 'export'], ['auth']);

// ============================================================
// PLUGINS
// ============================================================
$router->get('/plugins', [\App\Controllers\PluginController::class, 'index'], ['auth']);
$router->post('/plugins/install', [\App\Controllers\PluginController::class, 'install'], ['auth']);
$router->post('/plugins/{id}/toggle', [\App\Controllers\PluginController::class, 'toggle'], ['auth']);
$router->post('/plugins/{id}/uninstall', [\App\Controllers\PluginController::class, 'uninstall'], ['auth']);
$router->get('/plugins/{id}/settings', [\App\Controllers\PluginController::class, 'settings'], ['auth']);

// ============================================================
// DASHBOARD CUSTOMIZATION
// ============================================================
$router->get('/dashboard/customize', [\App\Controllers\DashboardCustomController::class, 'customize'], ['auth']);
$router->post('/dashboard/widgets', [\App\Controllers\DashboardCustomController::class, 'saveWidgets'], ['auth']);
$router->post('/dashboard/filters', [\App\Controllers\DashboardCustomController::class, 'saveFilter'], ['auth']);
$router->post('/dashboard/filters/{id}/delete', [\App\Controllers\DashboardCustomController::class, 'deleteFilter'], ['auth']);

// ============================================================
// COMPLIANCE (GDPR / HIPAA)
// ============================================================
$router->get('/compliance', [\App\Controllers\ComplianceController::class, 'index'], ['auth']);
$router->post('/compliance/retention', [\App\Controllers\ComplianceController::class, 'storeRetention'], ['auth']);
$router->get('/compliance/privacy-logs', [\App\Controllers\ComplianceController::class, 'privacyLogs'], ['auth']);
$router->get('/compliance/consent-logs', [\App\Controllers\ComplianceController::class, 'consentLogs'], ['auth']);
$router->post('/compliance/export/{userId}', [\App\Controllers\ComplianceController::class, 'exportData'], ['auth']);
$router->post('/compliance/anonymize/{userId}', [\App\Controllers\ComplianceController::class, 'anonymize'], ['auth']);

// ============================================================
// BI ANALYTICS & REPORTS
// ============================================================
$router->get('/bi', [\App\Controllers\BiAnalyticsController::class, 'index'], ['auth']);
$router->post('/bi/reports', [\App\Controllers\BiAnalyticsController::class, 'createReport'], ['auth']);
$router->get('/bi/reports/{id}/edit', [\App\Controllers\BiAnalyticsController::class, 'editReport'], ['auth']);
$router->post('/bi/reports/{id}/run', [\App\Controllers\BiAnalyticsController::class, 'runReport'], ['auth']);
$router->get('/bi/connections', [\App\Controllers\BiAnalyticsController::class, 'biConnections'], ['auth']);
$router->post('/bi/connections/test/{id}', [\App\Controllers\BiAnalyticsController::class, 'testConnection'], ['auth']);

// ============================================================
// STABILITY STUDIES
// ============================================================
$router->get('/stability', [\App\Controllers\StabilityController::class, 'index'], ['auth']);
$router->get('/stability/create', [\App\Controllers\StabilityController::class, 'create'], ['auth']);
$router->post('/stability', [\App\Controllers\StabilityController::class, 'store'], ['auth']);
$router->get('/stability/{id}', [\App\Controllers\StabilityController::class, 'show'], ['auth']);
$router->get('/stability/{id}/edit', [\App\Controllers\StabilityController::class, 'edit'], ['auth']);
$router->post('/stability/{id}', [\App\Controllers\StabilityController::class, 'update'], ['auth']);
$router->post('/stability/{id}/timepoints', [\App\Controllers\StabilityController::class, 'addTimepoint'], ['auth']);
$router->post('/stability/timepoints/{id}/result', [\App\Controllers\StabilityController::class, 'addResult'], ['auth']);
$router->post('/stability/{id}/close', [\App\Controllers\StabilityController::class, 'closeStudy'], ['auth']);

// ============================================================
// ENVIRONMENTAL MONITORING
// ============================================================
$router->get('/environmental', [\App\Controllers\EnvironmentalController::class, 'index'], ['auth']);
$router->get('/environmental/points', [\App\Controllers\EnvironmentalController::class, 'points'], ['auth']);
$router->get('/environmental/points/create', [\App\Controllers\EnvironmentalController::class, 'createPoint'], ['auth']);
$router->post('/environmental/points', [\App\Controllers\EnvironmentalController::class, 'storePoint'], ['auth']);
$router->get('/environmental/points/{id}/readings', [\App\Controllers\EnvironmentalController::class, 'readings'], ['auth']);
$router->post('/environmental/points/{id}/readings', [\App\Controllers\EnvironmentalController::class, 'addReading'], ['auth']);
$router->get('/environmental/alerts', [\App\Controllers\EnvironmentalController::class, 'alerts'], ['auth']);

// ============================================================
// TRAINING MANAGEMENT
// ============================================================
$router->get('/training', [\App\Controllers\TrainingController::class, 'index'], ['auth']);
$router->get('/training/courses', [\App\Controllers\TrainingController::class, 'courses'], ['auth']);
$router->get('/training/courses/create', [\App\Controllers\TrainingController::class, 'createCourse'], ['auth']);
$router->post('/training/courses', [\App\Controllers\TrainingController::class, 'storeCourse'], ['auth']);
$router->get('/training/courses/{id}/edit', [\App\Controllers\TrainingController::class, 'editCourse'], ['auth']);
$router->post('/training/courses/{id}', [\App\Controllers\TrainingController::class, 'updateCourse'], ['auth']);
$router->get('/training/assignments', [\App\Controllers\TrainingController::class, 'assignments'], ['auth']);
$router->post('/training/assignments', [\App\Controllers\TrainingController::class, 'assignUser'], ['auth']);
$router->post('/training/assignments/{id}/complete', [\App\Controllers\TrainingController::class, 'recordCompletion'], ['auth']);

// ============================================================
// SUPPLIER / VENDOR MANAGEMENT
// ============================================================
$router->get('/suppliers', [\App\Controllers\SupplierController::class, 'index'], ['auth']);
$router->get('/suppliers/create', [\App\Controllers\SupplierController::class, 'create'], ['auth']);
$router->post('/suppliers', [\App\Controllers\SupplierController::class, 'store'], ['auth']);
$router->get('/suppliers/{id}', [\App\Controllers\SupplierController::class, 'show'], ['auth']);
$router->get('/suppliers/{id}/edit', [\App\Controllers\SupplierController::class, 'edit'], ['auth']);
$router->post('/suppliers/{id}', [\App\Controllers\SupplierController::class, 'update'], ['auth']);
$router->get('/suppliers/{id}/qualifications', [\App\Controllers\SupplierController::class, 'qualifications'], ['auth']);
$router->post('/suppliers/{id}/qualifications', [\App\Controllers\SupplierController::class, 'addQualification'], ['auth']);
$router->get('/suppliers/{id}/products', [\App\Controllers\SupplierController::class, 'products'], ['auth']);
$router->post('/suppliers/{id}/products', [\App\Controllers\SupplierController::class, 'linkProduct'], ['auth']);

// ============================================================
// DEVIATION / NON-CONFORMANCE
// ============================================================
$router->get('/deviations', [\App\Controllers\DeviationController::class, 'index'], ['auth']);
$router->get('/deviations/create', [\App\Controllers\DeviationController::class, 'create'], ['auth']);
$router->post('/deviations', [\App\Controllers\DeviationController::class, 'store'], ['auth']);
$router->get('/deviations/{id}', [\App\Controllers\DeviationController::class, 'show'], ['auth']);
$router->get('/deviations/{id}/edit', [\App\Controllers\DeviationController::class, 'edit'], ['auth']);
$router->post('/deviations/{id}', [\App\Controllers\DeviationController::class, 'update'], ['auth']);
$router->post('/deviations/{id}/actions', [\App\Controllers\DeviationController::class, 'addAction'], ['auth']);
$router->post('/deviations/actions/{id}/status', [\App\Controllers\DeviationController::class, 'updateActionStatus'], ['auth']);
$router->post('/deviations/{id}/close', [\App\Controllers\DeviationController::class, 'closeDeviation'], ['auth']);

// ============================================================
// CALIBRATION (Enhanced)
// ============================================================
$router->get('/calibrations', [\App\Controllers\CalibrationEnhancedController::class, 'index'], ['auth']);
$router->get('/calibrations/standards', [\App\Controllers\CalibrationEnhancedController::class, 'standards'], ['auth']);
$router->get('/calibrations/standards/create', [\App\Controllers\CalibrationEnhancedController::class, 'createStandard'], ['auth']);
$router->get('/calibrations/standards/{id}/edit', [\App\Controllers\CalibrationEnhancedController::class, 'editStandard'], ['auth']);
$router->post('/calibrations/standards', [\App\Controllers\CalibrationEnhancedController::class, 'storeStandard'], ['auth']);
$router->post('/calibrations/standards/{id}', [\App\Controllers\CalibrationEnhancedController::class, 'updateStandard'], ['auth']);
$router->get('/calibrations/schedules', [\App\Controllers\CalibrationEnhancedController::class, 'schedules'], ['auth']);
$router->post('/calibrations/schedules', [\App\Controllers\CalibrationEnhancedController::class, 'createSchedule'], ['auth']);
$router->get('/calibrations/records/{instrumentId}', [\App\Controllers\CalibrationEnhancedController::class, 'records'], ['auth']);
$router->post('/calibrations/records', [\App\Controllers\CalibrationEnhancedController::class, 'addRecord'], ['auth']);
$router->get('/calibrations/overdue', [\App\Controllers\CalibrationEnhancedController::class, 'getOverdue'], ['auth']);

// ============================================================
// BILLING & INVOICING
// ============================================================
$router->get('/billing', [\App\Controllers\BillingController::class, 'index'], ['auth']);
$router->get('/billing/create', [\App\Controllers\BillingController::class, 'create'], ['auth']);
$router->post('/billing/store', [\App\Controllers\BillingController::class, 'store'], ['auth']);
$router->post('/billing', [\App\Controllers\BillingController::class, 'store'], ['auth']);
$router->get('/billing/{id}', [\App\Controllers\BillingController::class, 'show'], ['auth']);
$router->get('/billing/{id}/edit', [\App\Controllers\BillingController::class, 'edit'], ['auth']);
$router->post('/billing/{id}', [\App\Controllers\BillingController::class, 'update'], ['auth']);
$router->post('/billing/{id}/update', [\App\Controllers\BillingController::class, 'update'], ['auth']);
$router->post('/billing/{id}/items', [\App\Controllers\BillingController::class, 'addItem'], ['auth']);
$router->post('/billing/{id}/payments', [\App\Controllers\BillingController::class, 'recordPayment'], ['auth']);
$router->get('/billing/{id}/pdf', [\App\Controllers\BillingController::class, 'downloadPdf'], ['auth']);

// ============================================================
// DEPLOYMENT / CLOUD SETTINGS
// ============================================================
$router->get('/deployment', [\App\Controllers\DeploymentController::class, 'index'], ['auth']);
$router->post('/deployment', [\App\Controllers\DeploymentController::class, 'update'], ['auth']);
$router->get('/deployment/toggle-mode', [\App\Controllers\DeploymentController::class, 'toggleMode'], ['auth']);

// ============================================================
// BACKUP & RESTORE
// ============================================================
$router->get('/backups', [\App\Controllers\BackupController::class, 'index'], ['auth']);
$router->post('/backups', [\App\Controllers\BackupController::class, 'create'], ['auth']);
$router->get('/backups/download/{file}', [\App\Controllers\BackupController::class, 'download'], ['auth']);
$router->get('/backups/restore/{file}', [\App\Controllers\BackupController::class, 'confirmRestore'], ['auth']);
$router->post('/backups/restore/{file}', [\App\Controllers\BackupController::class, 'restore'], ['auth']);
$router->post('/backups/delete/{file}', [\App\Controllers\BackupController::class, 'delete'], ['auth']);
$router->post('/backups/settings', [\App\Controllers\BackupController::class, 'updateSettings'], ['auth']);

// ============================================================
// MISSING ROUTES FOR BROKEN LINKS IN VIEWS
// ============================================================
$router->post('/environmental/alerts/{id}/acknowledge', [\App\Controllers\EnvironmentalController::class, 'acknowledgeAlert'], ['auth']);
$router->get('/api-management/webhooks/{id}/edit', [\App\Controllers\ApiIntegrationController::class, 'editWebhookJson'], ['auth']);
$router->post('/api-management/webhooks/{id}/update', [\App\Controllers\ApiIntegrationController::class, 'updateWebhook'], ['auth']);
$router->post('/api-management/webhooks/{id}/delete', [\App\Controllers\ApiIntegrationController::class, 'deleteWebhook'], ['auth']);
$router->post('/dashboard/widgets/reset', [\App\Controllers\DashboardCustomController::class, 'resetWidgets'], ['auth']);
$router->post('/dashboard/widgets/{id}/remove', [\App\Controllers\DashboardCustomController::class, 'removeWidget'], ['auth']);
$router->get('/compliance/data-retention', [\App\Controllers\ComplianceController::class, 'dataRetention'], ['auth']);
$router->post('/compliance/retention/{id}', [\App\Controllers\ComplianceController::class, 'deleteRetention'], ['auth']);
$router->post('/entries/{id}/update', [\App\Controllers\NotebookController::class, 'updateEntry'], ['auth']);
$router->post('/stability/timepoints/{id}/start', [\App\Controllers\StabilityController::class, 'startTimepoint'], ['auth']);
$router->post('/stability/timepoints/{id}/complete', [\App\Controllers\StabilityController::class, 'completeTimepoint'], ['auth']);
$router->get('/stability/results/{id}', [\App\Controllers\StabilityController::class, 'showResult'], ['auth']);
$router->post('/suppliers/{id}/qualifications/{qid}', [\App\Controllers\SupplierController::class, 'deleteQualification'], ['auth']);
$router->post('/sso/config/saml', [\App\Controllers\SsoController::class, 'updateConfig'], ['auth']);
$router->post('/sso/config/ldap', [\App\Controllers\SsoController::class, 'updateConfig'], ['auth']);
$router->post('/sso/config/oauth', [\App\Controllers\SsoController::class, 'updateConfig'], ['auth']);
$router->post('/sso/config/ldap/test', [\App\Controllers\SsoController::class, 'testConnection'], ['auth']);
