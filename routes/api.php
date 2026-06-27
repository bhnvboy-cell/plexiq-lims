<?php

use App\Router;
use App\Controllers\Api\SapApiController;

/** @var Router $router */

// ============================================================
// SAP HANA INTEGRATION API ENDPOINTS
// ============================================================

$router->post('/api/sap/push/sample', [SapApiController::class, 'pushSample']);
$router->post('/api/sap/push/result', [SapApiController::class, 'pushResult']);
$router->get('/api/sap/pull/customer', [SapApiController::class, 'pullCustomers']);
$router->get('/api/sap/pull/product', [SapApiController::class, 'pullProducts']);
$router->get('/api/sap/pull/specification', [SapApiController::class, 'pullSpecifications']);
$router->get('/api/sap/status', [SapApiController::class, 'status']);

// ============================================================
// GENERAL REST API (Token-Authenticated)
// ============================================================
$router->get('/api/status', [\App\Controllers\Api\GeneralApiController::class, 'status']);
$router->get('/api/samples', [\App\Controllers\Api\GeneralApiController::class, 'listSamples'], ['api']);
$router->get('/api/samples/{id}', [\App\Controllers\Api\GeneralApiController::class, 'getSample'], ['api']);
$router->post('/api/samples', [\App\Controllers\Api\GeneralApiController::class, 'createSample'], ['api']);
$router->get('/api/results', [\App\Controllers\Api\GeneralApiController::class, 'listResults'], ['api']);
$router->post('/api/results/{sampleTestId}', [\App\Controllers\Api\GeneralApiController::class, 'submitResult'], ['api']);
$router->get('/api/products', [\App\Controllers\Api\GeneralApiController::class, 'listProducts'], ['api']);
$router->get('/api/customers', [\App\Controllers\Api\GeneralApiController::class, 'listCustomers'], ['api']);
$router->get('/api/notifications', [\App\Controllers\Api\GeneralApiController::class, 'myNotifications'], ['api']);
$router->get('/api/notifications/unread', [\App\Controllers\Api\GeneralApiController::class, 'unreadCount'], ['api']);
$router->get('/api/barcode/lookup', [\App\Controllers\Api\GeneralApiController::class, 'barcodeLookup'], ['api']);
