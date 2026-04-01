<?php
/**
 * Precision Ink ERP – Bootstrap / Front Controller
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Start session
session_start();

// ── Service Container ──────────────────────────────────────────────
// Build a shared PDO connection and instantiate core services once.
// Controllers access these via BaseController::setContainer().
try {
    $pdo = new \PDO(
        "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES    => false,
        ]
    );
} catch (\PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$container = [
    'db'            => $pdo,
    'audit'         => new \App\Services\AuditService($pdo),
    'email'         => new \App\Services\EmailService($pdo, $_SESSION['user']['id'] ?? null),
    'facility'      => new \App\Services\FacilityService($pdo),
    'attachments'   => new \App\Services\AttachmentService($pdo, __DIR__ . '/../storage/attachments'),
    'custom_fields' => new \App\Services\CustomFieldService($pdo),
];

\PrecisionInk\Controllers\BaseController::setContainer($container);

// Initialise router
$router = new \Bramus\Router\Router();

// ── Dashboard ──────────────────────────────────────────────────────
$router->get('/', 'PrecisionInk\\Controllers\\DashboardController@index');

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
    $c = 'PrecisionInk\\Controllers\\TransferController';
    $router->get('/',               "{$c}@index");
    $router->get('/create',         "{$c}@create");
    $router->post('/create',        "{$c}@store");
    $router->get('/search-items',   "{$c}@searchItems");
    $router->get('/item-packs',     "{$c}@itemPackExtensions");
    $router->get('/(\d+)',          "{$c}@view");
    $router->get('/(\d+)/edit',     "{$c}@edit");
    $router->post('/(\d+)/edit',    "{$c}@update");
    $router->get('/(\d+)/ship',     "{$c}@shipForm");
    $router->post('/(\d+)/ship',    "{$c}@ship");
    $router->get('/(\d+)/receive',  "{$c}@receiveForm");
    $router->post('/(\d+)/receive', "{$c}@receive");
    $router->post('/(\d+)/cancel',  "{$c}@cancel");
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

    // Notification Log
    $router->get('/notification-log',              "{$c}@notificationLog");

    // Audit Log
    $router->get('/audit-log',                     "{$c}@auditLog");
    $router->get('/audit-log/export',              "{$c}@auditLogExport");

    // Email Log
    $router->get('/email-log',                     "{$c}@emailLog");
    $router->post('/email-log/resend/(\d+)',       "{$c}@resendEmail");

    // User Activity
    $router->get('/user-activity',                 "{$c}@userActivity");
    $router->post('/user-activity/force-logout/(\d+)', "{$c}@forceLogout");
    $router->get('/user-activity/sessions-json',   "{$c}@activeSessionsJson");

    // Announcements
    $router->get('/announcements',                 "{$c}@announcements");
    $router->post('/announcements/save',           "{$c}@saveAnnouncement");
    $router->post('/announcements/delete/(\d+)',   "{$c}@deleteAnnouncement");

    // Custom Fields
    $router->get('/custom-fields',                 "{$c}@customFields");
    $router->get('/custom-fields/(\w+)',           "{$c}@customFields");
    $router->post('/custom-fields/save',           "{$c}@saveCustomField");
    $router->post('/custom-fields/deactivate/(\d+)', "{$c}@deactivateCustomField");

    // Import/Export
    $router->get('/import-export',                 "{$c}@importExport");
    $router->get('/import/template/([\w-]+)',      "{$c}@importTemplate");
    $router->post('/import/dry-run/([\w-]+)',      "{$c}@importDryRun");
    $router->post('/import/commit/([\w-]+)',       "{$c}@importCommit");
    $router->get('/export/([\w-]+)',               "{$c}@exportCsv");

    // System Health
    $router->get('/system-health',                 "{$c}@systemHealth");
    $router->post('/backup/run',                   "{$c}@runBackup");

    // Print Queue
    $router->get('/print-queue',                   "{$c}@printQueue");
    $router->post('/print-queue/print',            "{$c}@printQueuePrint");
    $router->post('/print-queue/clear',            "{$c}@printQueueClear");
    $router->post('/print-queue/remove',           "{$c}@printQueueRemove");
});

// ── Print Queue Add (outside /settings mount) ──────────────────
$router->post('/print-queue/add', 'PrecisionInk\\Controllers\\SettingsController@printQueueAdd');

// ── Announcement Dismiss (outside /settings mount) ─────────────
$router->post('/announcements/dismiss/(\d+)', 'PrecisionInk\\Controllers\\SettingsController@dismissAnnouncement');

// ── Facility Switch ────────────────────────────────────────────
$router->post('/facility/switch', 'PrecisionInk\\Controllers\\TransferController@switchFacility');

// ── Attachments ────────────────────────────────────────────────
$router->post('/attachments/upload', 'PrecisionInk\\Controllers\\AttachmentController@upload');
$router->get('/attachments/download/(\d+)', 'PrecisionInk\\Controllers\\AttachmentController@download');
$router->post('/attachments/delete/(\d+)', 'PrecisionInk\\Controllers\\AttachmentController@delete');

// Dispatch
$router->run();
