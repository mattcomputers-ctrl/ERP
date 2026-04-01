<?php
/**
 * Precision Ink ERP – Bootstrap / Front Controller
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Start session
session_start();

// Initialise router
$router = new \Bramus\Router\Router();

// ── Auth ────────────────────────────────────────────────────────────
$router->mount('/auth', function () use ($router) {
    // TODO: login, logout, password reset
});

// ── Items ───────────────────────────────────────────────────────────
$router->mount('/items', function () use ($router) {
    // TODO: CRUD for raw materials, finished goods, packaging
});

// ── Recipes ─────────────────────────────────────────────────────────
$router->mount('/recipes', function () use ($router) {
    // TODO: formulation / bill-of-materials management
});

// ── Suppliers ───────────────────────────────────────────────────────
$router->mount('/suppliers', function () use ($router) {
    // TODO: supplier master data
});

// ── Customers ───────────────────────────────────────────────────────
$router->mount('/customers', function () use ($router) {
    // TODO: customer master data
});

// ── Inventory ───────────────────────────────────────────────────────
$router->mount('/inventory', function () use ($router) {
    // TODO: stock levels, adjustments, lot tracking
});

// ── Purchase Requisitions ───────────────────────────────────────────
$router->mount('/purchase-requisitions', function () use ($router) {
    // TODO: internal purchase requests
});

// ── Purchase Orders ─────────────────────────────────────────────────
$router->mount('/purchase-orders', function () use ($router) {
    // TODO: PO lifecycle
});

// ── Sales Orders ────────────────────────────────────────────────────
$router->mount('/sales-orders', function () use ($router) {
    // TODO: SO lifecycle
});

// ── Quotes ──────────────────────────────────────────────────────────
$router->mount('/quotes', function () use ($router) {
    // TODO: quotation management
});

// ── Shipments ───────────────────────────────────────────────────────
$router->mount('/shipments', function () use ($router) {
    // TODO: outbound shipment tracking
});

// ── Invoices ────────────────────────────────────────────────────────
$router->mount('/invoices', function () use ($router) {
    // TODO: AR/AP invoicing
});

// ── Pick Lists ──────────────────────────────────────────────────────
$router->mount('/pick-lists', function () use ($router) {
    // TODO: warehouse pick-list generation
});

// ── Batches ─────────────────────────────────────────────────────────
$router->mount('/batches', function () use ($router) {
    // TODO: production batch records
});

// ── Repack ──────────────────────────────────────────────────────────
$router->mount('/repack', function () use ($router) {
    // TODO: repackaging operations
});

// ── QC ──────────────────────────────────────────────────────────────
$router->mount('/qc', function () use ($router) {
    // TODO: quality-control inspections
});

// ── Transfers ───────────────────────────────────────────────────────
$router->mount('/transfers', function () use ($router) {
    // TODO: inter-facility stock transfers
});

// ── RMA ─────────────────────────────────────────────────────────────
$router->mount('/rma', function () use ($router) {
    // TODO: return-merchandise authorisations
});

// ── Consignment ─────────────────────────────────────────────────────
$router->mount('/consignment', function () use ($router) {
    // TODO: consignment inventory tracking
});

// ── CRM ─────────────────────────────────────────────────────────────
$router->mount('/crm', function () use ($router) {
    // TODO: contacts, activities, follow-ups
});

// ── Reports ─────────────────────────────────────────────────────────
$router->mount('/reports', function () use ($router) {
    // TODO: dashboards, exports, scheduled reports
});

// ── API ─────────────────────────────────────────────────────────────
$router->mount('/api', function () use ($router) {
    // TODO: RESTful JSON endpoints
});

// ── Settings ────────────────────────────────────────────────────────
$router->mount('/settings', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\SettingsController';

    $router->get('/',                  "{$c}@index");

    // Company
    $router->get('/company',           "{$c}@company");
    $router->post('/company',          "{$c}@saveCompany");

    // Thresholds & Defaults
    $router->get('/thresholds',        "{$c}@thresholds");
    $router->post('/thresholds',       "{$c}@saveThresholds");

    // Dropdown Lists
    $router->get('/uom',               "{$c}@uom");
    $router->post('/uom',              "{$c}@saveUom");

    $router->get('/ship-via',          "{$c}@shipVia");
    $router->post('/ship-via',         "{$c}@saveShipVia");

    $router->get('/payment-terms',     "{$c}@paymentTerms");
    $router->post('/payment-terms',    "{$c}@savePaymentTerms");

    $router->get('/surcharge-types',   "{$c}@surchargeTypes");
    $router->post('/surcharge-types',  "{$c}@saveSurchargeTypes");

    $router->get('/rma-reasons',       "{$c}@rmaReasons");
    $router->post('/rma-reasons',      "{$c}@saveRmaReasons");

    $router->get('/lost-quote-reasons',  "{$c}@lostQuoteReasons");
    $router->post('/lost-quote-reasons', "{$c}@saveLostQuoteReasons");

    $router->get('/landed-cost-types',   "{$c}@landedCostTypes");
    $router->post('/landed-cost-types',  "{$c}@saveLandedCostTypes");

    $router->get('/reason-codes',      "{$c}@reasonCodes");
    $router->post('/reason-codes',     "{$c}@saveReasonCodes");

    $router->get('/industry-segments',   "{$c}@industrySegments");
    $router->post('/industry-segments',  "{$c}@saveIndustrySegments");

    // Facilities
    $router->get('/facilities',          "{$c}@facilities");
    $router->post('/facilities',         "{$c}@saveFacility");

    // Equipment
    $router->get('/equipment',           "{$c}@equipment");
    $router->post('/equipment',          "{$c}@saveEquipment");

    // Item Prototypes
    $router->get('/item-prototypes',          "{$c}@itemPrototypes");
    $router->post('/item-prototypes',         "{$c}@saveItemPrototype");
    $router->get('/item-prototypes/form',     "{$c}@itemPrototypeForm");

    // Batch Templates
    $router->get('/batch-templates',     "{$c}@batchTemplates");
    $router->post('/batch-templates',    "{$c}@saveBatchTemplate");

    // Document Numbering
    $router->get('/document-numbering',  "{$c}@documentNumbering");
    $router->post('/document-numbering', "{$c}@saveDocumentNumbering");

    // API Keys
    $router->get('/api-keys',            "{$c}@apiKeys");
    $router->post('/api-keys',           "{$c}@saveApiKey");

    // SMTP Email Settings
    $router->get('/smtp',                "{$c}@smtp");
    $router->post('/smtp',               "{$c}@saveSmtp");
    $router->post('/smtp/test',          "{$c}@testSmtp");

    // Email Templates
    $router->get('/email-templates',              "{$c}@emailTemplates");
    $router->get('/email-templates/edit/(\w+)',   "{$c}@emailTemplateEdit");
    $router->post('/email-templates/save/(\w+)',  "{$c}@saveEmailTemplate");
    $router->post('/email-templates/signature',   "{$c}@saveEmailSignature");

    // Password Policy
    $router->get('/password-policy',     "{$c}@passwordPolicy");
    $router->post('/password-policy',    "{$c}@savePasswordPolicy");

    // Notification Settings
    $router->get('/notifications',       "{$c}@notifications");
    $router->post('/notifications',      "{$c}@saveNotifications");

    // Scheduled Report Delivery
    $router->get('/scheduled-reports',             "{$c}@scheduledReports");
    $router->post('/scheduled-reports/save',       "{$c}@saveScheduledReport");
    $router->post('/scheduled-reports/delete/(\d+)', "{$c}@deleteScheduledReport");
});

// Dispatch
$router->run();
