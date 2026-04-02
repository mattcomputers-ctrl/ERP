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
    'supplier'      => new \App\Services\SupplierService($pdo),
    'credit'        => new \App\Services\CreditService($pdo),
    'customer_res'  => new \App\Services\CustomerResolutionService($pdo),
    'fifo'          => new \App\Services\FIFOService($pdo),
];
$container['reservation'] = new \App\Services\ReservationService($pdo, $container['fifo']);
$container['batch_cost'] = new \App\Services\BatchCostService($pdo, $container['fifo']);
$container['pricing'] = new \App\Services\PricingService($pdo);
$container['traceability'] = new \App\Services\LotTraceabilityService($pdo);
$container['notification'] = new \App\Services\NotificationService($pdo, $container['email']);
$container['renderer'] = new \App\Services\DocumentRenderer($pdo);

\PrecisionInk\Controllers\BaseController::setContainer($container);

// Initialise router
$router = new \Bramus\Router\Router();

// ── Dashboard ──────────────────────────────────────────────────────
$router->get('/', 'PrecisionInk\\Controllers\\DashboardController@index');
$router->get('/search', 'PrecisionInk\\Controllers\\DashboardController@globalSearch');

// ── Auth ────────────────────────────────────────────────────────────
$router->get('/auth/login', 'PrecisionInk\\Controllers\\AuthController@loginForm');
$router->post('/auth/login', 'PrecisionInk\\Controllers\\AuthController@login');
$router->get('/auth/logout', 'PrecisionInk\\Controllers\\AuthController@logout');

// ── Auth Guard — redirect to login if not authenticated ─────────────
$publicPaths = ['/auth/login', '/api/v1/health', '/api/docs'];
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isPublic = false;
foreach ($publicPaths as $pp) {
    if ($requestPath === $pp || strpos($requestPath, '/api/v1/') === 0) {
        $isPublic = true;
        break;
    }
}
if (!$isPublic && empty($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

// ── Users & Groups ──────────────────────────────────────────────────
$router->mount('/users', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\UserController';
    $router->get('/',                      "{$c}@index");
    $router->get('/create',                "{$c}@createForm");
    $router->post('/create',               "{$c}@store");
    $router->get('/(\d+)/edit',            "{$c}@editForm");
    $router->post('/(\d+)/edit',           "{$c}@update");
    $router->post('/(\d+)/deactivate',     "{$c}@deactivate");
    $router->post('/(\d+)/reset-password', "{$c}@resetPassword");
});
$router->mount('/groups', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\UserController';
    $router->get('/',                      "{$c}@groupIndex");
    $router->get('/create',                "{$c}@groupCreateForm");
    $router->post('/create',               "{$c}@groupStore");
    $router->get('/(\d+)/edit',            "{$c}@groupEditForm");
    $router->post('/(\d+)/edit',           "{$c}@groupUpdate");
    $router->post('/(\d+)/delete',         "{$c}@groupDelete");
});

// ── Items ───────────────────────────────────────────────────────────
$router->mount('/items', function () use ($router) {
    $router->get('/',                           'PrecisionInk\\Controllers\\ItemController@index');
    $router->get('/create',                     'PrecisionInk\\Controllers\\ItemController@create');
    $router->post('/create',                    'PrecisionInk\\Controllers\\ItemController@store');
    $router->get('/search',                     'PrecisionInk\\Controllers\\ItemController@search');
    $router->get('/customers-list',             'PrecisionInk\\Controllers\\ItemController@customersList');
    $router->get('/(\d+)',                      'PrecisionInk\\Controllers\\ItemController@view');
    $router->get('/(\d+)/edit',                 'PrecisionInk\\Controllers\\ItemController@editForm');
    $router->post('/(\d+)/edit',                'PrecisionInk\\Controllers\\ItemController@update');
    $router->post('/(\d+)/deactivate',          'PrecisionInk\\Controllers\\ItemController@deactivate');
    $router->post('/(\d+)/clone',               'PrecisionInk\\Controllers\\ItemController@cloneItem');
    $router->post('/(\d+)/packs',               'PrecisionInk\\Controllers\\ItemController@savePack');
    $router->post('/(\d+)/packs/(\d+)/deactivate', 'PrecisionInk\\Controllers\\ItemController@deactivatePack');
    $router->post('/(\d+)/pack-overrides/(\d+)', 'PrecisionInk\\Controllers\\ItemController@savePackOverride');
    $router->post('/(\d+)/aliases',             'PrecisionInk\\Controllers\\ItemController@saveAlias');
    $router->post('/(\d+)/aliases/(\d+)/deactivate', 'PrecisionInk\\Controllers\\ItemController@deactivateAlias');
    $router->post('/(\d+)/substitutions',       'PrecisionInk\\Controllers\\ItemController@saveSubstitution');
    $router->post('/(\d+)/substitutions/(\d+)/deactivate', 'PrecisionInk\\Controllers\\ItemController@deactivateSubstitution');
    $router->post('/(\d+)/location',            'PrecisionInk\\Controllers\\ItemController@saveLocation');

    // Recipe routes nested under items
    $router->get('/(\d+)/recipes',                          'PrecisionInk\\Controllers\\RecipeController@index');
    $router->get('/(\d+)/recipes/create',                   'PrecisionInk\\Controllers\\RecipeController@create');
    $router->post('/(\d+)/recipes/create',                  'PrecisionInk\\Controllers\\RecipeController@store');
    $router->get('/(\d+)/recipes/(\d+)',                    'PrecisionInk\\Controllers\\RecipeController@view');
    $router->get('/(\d+)/recipes/(\d+)/edit',               'PrecisionInk\\Controllers\\RecipeController@editForm');
    $router->post('/(\d+)/recipes/(\d+)/edit',              'PrecisionInk\\Controllers\\RecipeController@update');
    $router->post('/(\d+)/recipes/(\d+)/activate',          'PrecisionInk\\Controllers\\RecipeController@activate');
    $router->post('/(\d+)/recipes/(\d+)/deactivate',        'PrecisionInk\\Controllers\\RecipeController@deactivate');
    $router->post('/(\d+)/recipes/(\d+)/set-default',       'PrecisionInk\\Controllers\\RecipeController@setDefault');
    $router->get('/(\d+)/recipes/(\d+)/clone',              'PrecisionInk\\Controllers\\RecipeController@cloneVersion');
    $router->get('/(\d+)/recipe-versions',                   'PrecisionInk\\Controllers\\RecipeController@recipeVersionsJson');
});

// ── Suppliers ───────────────────────────────────────────────────────
$router->mount('/suppliers', function () use ($router) {
    $router->get('/',                              'PrecisionInk\\Controllers\\SupplierController@index');
    $router->get('/create',                        'PrecisionInk\\Controllers\\SupplierController@create');
    $router->post('/create',                       'PrecisionInk\\Controllers\\SupplierController@store');
    $router->get('/search',                        'PrecisionInk\\Controllers\\SupplierController@search');
    $router->get('/(\d+)',                         'PrecisionInk\\Controllers\\SupplierController@view');
    $router->get('/(\d+)/edit',                    'PrecisionInk\\Controllers\\SupplierController@editForm');
    $router->post('/(\d+)/edit',                   'PrecisionInk\\Controllers\\SupplierController@update');
    $router->post('/(\d+)/deactivate',             'PrecisionInk\\Controllers\\SupplierController@deactivate');
    $router->post('/(\d+)/contacts',               'PrecisionInk\\Controllers\\SupplierController@saveContact');
    $router->post('/(\d+)/contacts/(\d+)/deactivate', 'PrecisionInk\\Controllers\\SupplierController@deactivateContact');
    $router->post('/(\d+)/avl',                    'PrecisionInk\\Controllers\\SupplierController@saveAvl');
    $router->post('/(\d+)/avl/(\d+)/deactivate',   'PrecisionInk\\Controllers\\SupplierController@deactivateAvl');
});

// ── Customers ───────────────────────────────────────────────────────
$router->mount('/customers', function () use ($router) {
    $router->get('/',                                  'PrecisionInk\\Controllers\\CustomerController@index');
    $router->get('/create',                            'PrecisionInk\\Controllers\\CustomerController@create');
    $router->post('/create',                           'PrecisionInk\\Controllers\\CustomerController@store');
    $router->get('/search',                            'PrecisionInk\\Controllers\\CustomerController@search');
    $router->get('/(\d+)',                             'PrecisionInk\\Controllers\\CustomerController@view');
    $router->get('/(\d+)/edit',                        'PrecisionInk\\Controllers\\CustomerController@editForm');
    $router->post('/(\d+)/edit',                       'PrecisionInk\\Controllers\\CustomerController@update');
    $router->post('/(\d+)/deactivate',                 'PrecisionInk\\Controllers\\CustomerController@deactivate');
    $router->post('/(\d+)/contacts',                   'PrecisionInk\\Controllers\\CustomerController@saveContact');
    $router->post('/(\d+)/contacts/(\d+)/deactivate',  'PrecisionInk\\Controllers\\CustomerController@deactivateContact');
    $router->get('/(\d+)/ship-to/create',              'PrecisionInk\\Controllers\\CustomerController@createShipTo');
    $router->post('/(\d+)/ship-to/create',             'PrecisionInk\\Controllers\\CustomerController@storeShipTo');
    $router->get('/(\d+)/ship-to/(\d+)/edit',          'PrecisionInk\\Controllers\\CustomerController@editShipTo');
    $router->post('/(\d+)/ship-to/(\d+)/edit',         'PrecisionInk\\Controllers\\CustomerController@updateShipTo');
    $router->post('/(\d+)/ship-to/(\d+)/deactivate',   'PrecisionInk\\Controllers\\CustomerController@deactivateShipTo');
    $router->post('/(\d+)/crm-profile',                'PrecisionInk\\Controllers\\CustomerController@saveCrmProfile');
    $router->post('/(\d+)/activities',                 'PrecisionInk\\Controllers\\CustomerController@saveActivity');
    $router->post('/(\d+)/tasks',                      'PrecisionInk\\Controllers\\CustomerController@saveTask');
    $router->post('/(\d+)/tasks/(\d+)/complete',       'PrecisionInk\\Controllers\\CustomerController@completeTask');
    $router->post('/(\d+)/tasks/(\d+)/cancel',         'PrecisionInk\\Controllers\\CustomerController@cancelTask');
    $router->post('/(\d+)/prices',                     'PrecisionInk\\Controllers\\CustomerController@savePrice');
    $router->post('/(\d+)/prices/(\d+)/deactivate',    'PrecisionInk\\Controllers\\CustomerController@deactivatePrice');
    $router->post('/(\d+)/moq',                        'PrecisionInk\\Controllers\\CustomerController@saveMoq');
    $router->post('/(\d+)/moq/(\d+)/deactivate',       'PrecisionInk\\Controllers\\CustomerController@deactivateMoq');
});

// ── Inventory ───────────────────────────────────────────────────────
$router->mount('/inventory', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\InventoryController';
    $router->get('/',                          "{$c}@index");
    $router->get('/lots/(\d+)',                "{$c}@lots");
    $router->get('/adjustments',               "{$c}@adjustmentForm");
    $router->post('/adjustments',              "{$c}@saveAdjustment");
    $router->get('/quarantine',                "{$c}@quarantineList");
    $router->post('/quarantine/(\d+)',         "{$c}@quarantineLot");
    $router->post('/release/(\d+)',            "{$c}@releaseLot");
    $router->get('/transactions',              "{$c}@transactions");
    $router->get('/counts',                    "{$c}@countSessions");
    $router->get('/counts/create',             "{$c}@createCountForm");
    $router->post('/counts/create',            "{$c}@createCount");
    $router->get('/counts/(\d+)',              "{$c}@countSession");
    $router->post('/counts/(\d+)/entry',       "{$c}@saveCountEntry");
    $router->get('/counts/(\d+)/review',       "{$c}@reviewCount");
    $router->post('/counts/(\d+)/post',        "{$c}@postCount");
    $router->post('/counts/(\d+)/cancel',      "{$c}@cancelCount");
    $router->get('/counts/(\d+)/sheet',        "{$c}@countSheet");
});

// ── Purchase Requisitions ───────────────────────────────────────────
$router->mount('/purchase-requisitions', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\RequisitionController';
    $router->get('/',                  "{$c}@index");
    $router->get('/create',            "{$c}@create");
    $router->post('/create',           "{$c}@store");
    $router->get('/(\d+)',             "{$c}@view");
    $router->get('/(\d+)/edit',        "{$c}@editForm");
    $router->post('/(\d+)/edit',       "{$c}@update");
    $router->post('/(\d+)/submit',     "{$c}@submit");
    $router->post('/(\d+)/approve',    "{$c}@approve");
    $router->post('/(\d+)/reject',     "{$c}@reject");
    $router->post('/(\d+)/convert',    "{$c}@convert");
    $router->post('/(\d+)/cancel',     "{$c}@cancel");
});

// ── Purchase Orders ─────────────────────────────────────────────────
$router->mount('/purchase-orders', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\PurchaseOrderController';
    $router->get('/',                                  "{$c}@index");
    $router->get('/create',                            "{$c}@create");
    $router->post('/create',                           "{$c}@store");
    $router->get('/line-cost',                         "{$c}@lineCost");
    $router->get('/(\d+)',                             "{$c}@show");
    $router->get('/(\d+)/edit',                        "{$c}@editForm");
    $router->post('/(\d+)/edit',                       "{$c}@update");
    $router->post('/(\d+)/cancel',                     "{$c}@cancel");
    $router->post('/(\d+)/clone',                      "{$c}@clonePO");
    $router->post('/(\d+)/send',                       "{$c}@send");
    $router->get('/(\d+)/receive',                     "{$c}@receiveForm");
    $router->post('/(\d+)/receive',                    "{$c}@receive");
    $router->post('/(\d+)/cancel-line/(\d+)',          "{$c}@cancelLine");
    $router->get('/(\d+)/landed-costs',                "{$c}@landedCostsForm");
    $router->post('/(\d+)/landed-costs',               "{$c}@addLandedCost");
    $router->post('/(\d+)/landed-costs/(\d+)/post',    "{$c}@postLandedCost");
});

// ── Sales Orders ────────────────────────────────────────────────────
$router->mount('/orders', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\SalesOrderController';
    $router->get('/',                            "{$c}@index");
    $router->get('/create',                      "{$c}@create");
    $router->post('/create',                     "{$c}@store");
    $router->get('/resolve-ship-to',             "{$c}@resolveShipTo");
    $router->get('/(\d+)',                       "{$c}@show");
    $router->get('/(\d+)/edit',                  "{$c}@editForm");
    $router->post('/(\d+)/edit',                 "{$c}@update");
    $router->post('/(\d+)/confirm',              "{$c}@confirm");
    $router->post('/(\d+)/hold',                 "{$c}@hold");
    $router->post('/(\d+)/release-hold',         "{$c}@releaseHold");
    $router->post('/(\d+)/cancel',               "{$c}@cancel");
    $router->post('/(\d+)/clone',                "{$c}@cloneOrder");
    $router->post('/(\d+)/acknowledgment',       "{$c}@acknowledgment");
    $router->post('/(\d+)/proforma',             "{$c}@proforma");
});

// ── Quotes ──────────────────────────────────────────────────────────
$router->mount('/quotes', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\QuoteController';
    $router->get('/',                  "{$c}@index");
    $router->get('/create',            "{$c}@create");
    $router->post('/create',           "{$c}@store");
    $router->get('/price',             "{$c}@priceCheck");
    $router->get('/(\d+)',             "{$c}@show");
    $router->get('/(\d+)/edit',        "{$c}@editForm");
    $router->post('/(\d+)/edit',       "{$c}@update");
    $router->post('/(\d+)/send',       "{$c}@send");
    $router->post('/(\d+)/accept',     "{$c}@accept");
    $router->post('/(\d+)/decline',    "{$c}@decline");
    $router->post('/(\d+)/convert',    "{$c}@convert");
    $router->post('/(\d+)/clone',      "{$c}@cloneQuote");
});

// ── Shipping ────────────────────────────────────────────────────────
$router->mount('/shipping', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ShipmentController';
    $router->get('/create',                    "{$c}@createForm");
    $router->post('/create',                   "{$c}@store");
    $router->get('/(\d+)',                     "{$c}@show");
    $router->post('/(\d+)/delivery-date',      "{$c}@updateDeliveryDate");
    $router->get('/(\d+)/packing-slip',        "{$c}@packingSlip");
    $router->get('/(\d+)/bol',                 "{$c}@bol");
});

// ── Invoices ────────────────────────────────────────────────────────
$router->mount('/invoices', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\InvoiceController';
    $router->get('/',                          "{$c}@index");
    $router->get('/(\d+)',                     "{$c}@show");
    $router->get('/(\d+)/pdf',                 "{$c}@pdf");
    $router->post('/(\d+)/email',              "{$c}@email");
    $router->post('/(\d+)/edit',               "{$c}@edit");
    $router->post('/(\d+)/void',               "{$c}@void");
});

// ── Pick Lists ──────────────────────────────────────────────────────
$router->mount('/pick-lists', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\PickListController';
    $router->get('/',                          "{$c}@index");
    $router->get('/create',                    "{$c}@createForm");
    $router->post('/create',                   "{$c}@store");
    $router->get('/(\d+)',                     "{$c}@show");
    $router->post('/(\d+)/confirm-line',       "{$c}@confirmLine");
    $router->post('/(\d+)/complete',           "{$c}@complete");
    $router->get('/(\d+)/print',               "{$c}@printPdf");
});

// ── Batches ─────────────────────────────────────────────────────────
$router->mount('/batches', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\BatchController';
    $router->get('/',                          "{$c}@index");
    $router->get('/create',                    "{$c}@create");
    $router->post('/create',                   "{$c}@store");
    $router->get('/recipe-ingredients',        "{$c}@recipeIngredients");
    $router->get('/template-data',             "{$c}@templateData");
    $router->get('/(\d+)',                     "{$c}@show");
    $router->get('/(\d+)/edit',                "{$c}@editForm");
    $router->post('/(\d+)/edit',               "{$c}@update");
    $router->post('/(\d+)/start',              "{$c}@start");
    $router->post('/(\d+)/cancel',             "{$c}@cancel");
    $router->post('/(\d+)/clone',              "{$c}@cloneBatch");
    $router->post('/(\d+)/split',              "{$c}@split");
    $router->post('/(\d+)/scrap',              "{$c}@logScrap");
    $router->post('/(\d+)/save-template',      "{$c}@saveTemplate");
    $router->get('/(\d+)/print',               "{$c}@printPdf");
    $router->get('/(\d+)/close',               "{$c}@closeForm");
    $router->post('/(\d+)/close',              "{$c}@close");
    $router->get('/(\d+)/rework',              "{$c}@reworkForm");
    $router->post('/(\d+)/rework',             "{$c}@rework");
    $router->get('/(\d+)/cost',                "{$c}@costSummary");
    $router->get('/(\d+)/coa',                 "{$c}@coa");
    $router->post('/(\d+)/coa/email',          "{$c}@coaEmail");
    $router->get('/(\d+)/packet',              'PrecisionInk\\Controllers\\MrpController@batchPacket');
});

// ── MRP + Production ────────────────────────────────────────────────
$router->mount('/mrp', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\MrpController';
    $router->get('/',                              "{$c}@index");
    $router->post('/run',                          "{$c}@run");
    $router->get('/export',                        "{$c}@export");
    $router->post('/suggestions/batch/(\d+)',      "{$c}@createBatchFromSuggestion");
    $router->post('/suggestions/po/(\d+)',         "{$c}@createPoFromSuggestion");
});
$router->mount('/production', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\MrpController';
    $router->get('/calendar',                      "{$c}@calendar");
    $router->post('/calendar/toggle',              "{$c}@toggleCalendarDay");
    $router->get('/schedule',                      "{$c}@schedule");
});

// ── Repack ──────────────────────────────────────────────────────────
$router->mount('/repack', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\RepackController';
    $router->get('/',                  "{$c}@index");
    $router->get('/create',            "{$c}@create");
    $router->post('/create',           "{$c}@store");
    $router->get('/(\d+)',             "{$c}@show");
    $router->get('/(\d+)/edit',        "{$c}@editForm");
    $router->post('/(\d+)/edit',       "{$c}@update");
    $router->post('/(\d+)/close',      "{$c}@close");
    $router->post('/(\d+)/cancel',     "{$c}@cancel");
});

// ── QC ──────────────────────────────────────────────────────────────
$router->mount('/qc', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\QcController';
    $router->get('/specs',                   "{$c}@specsList");
    $router->get('/specs/create',            "{$c}@specCreate");
    $router->post('/specs/create',           "{$c}@specStore");
    $router->get('/specs/(\d+)',             "{$c}@specView");
    $router->get('/specs/(\d+)/edit',        "{$c}@specEditForm");
    $router->post('/specs/(\d+)/edit',       "{$c}@specUpdate");
    $router->post('/specs/(\d+)/deactivate', "{$c}@specDeactivate");
    $router->get('/inspection',              "{$c}@inspectionQueue");
    $router->get('/inspection/(\d+)',        "{$c}@inspectLot");
    $router->post('/inspection/(\d+)/pass',  "{$c}@passLot");
    $router->post('/inspection/(\d+)/fail',  "{$c}@failLot");
});

// ── SCARs ───────────────────────────────────────────────────────────
$router->mount('/scars', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ScarController';
    $router->get('/',                        "{$c}@index");
    $router->get('/create',                  "{$c}@create");
    $router->post('/create',                 "{$c}@store");
    $router->get('/(\d+)',                   "{$c}@view");
    $router->get('/(\d+)/edit',              "{$c}@editForm");
    $router->post('/(\d+)/edit',             "{$c}@update");
    $router->post('/(\d+)/respond',          "{$c}@respond");
    $router->post('/(\d+)/close',            "{$c}@close");
    $router->post('/(\d+)/cancel',           "{$c}@cancelScar");
    $router->post('/(\d+)/email',            "{$c}@emailToSupplier");
    $router->get('/(\d+)/pdf',               "{$c}@downloadPdf");
});

// ── Equipment Maintenance ───────────────────────────────────────────
$router->mount('/equipment', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\QcController';
    $router->get('/(\d+)/maintenance',       "{$c}@maintenanceHistory");
    $router->post('/(\d+)/maintenance',      "{$c}@logMaintenance");
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
    $c = 'PrecisionInk\\Controllers\\RmaController';
    $router->get('/',                  "{$c}@index");
    $router->get('/create',            "{$c}@create");
    $router->post('/create',           "{$c}@store");
    $router->get('/(\d+)',             "{$c}@show");
    $router->get('/(\d+)/edit',        "{$c}@editForm");
    $router->post('/(\d+)/edit',       "{$c}@update");
    $router->post('/(\d+)/receive',    "{$c}@receive");
    $router->post('/(\d+)/close',      "{$c}@close");
    $router->post('/(\d+)/cancel',     "{$c}@cancel");
});

// ── Consignment ─────────────────────────────────────────────────────
$router->mount('/consignment', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ConsignmentController';
    $router->get('/',                              "{$c}@index");
    $router->get('/create',                        "{$c}@createForm");
    $router->post('/create',                       "{$c}@store");
    $router->get('/(\d+)',                         "{$c}@show");
    $router->post('/(\d+)/consume',                "{$c}@consume");
    $router->post('/(\d+)/close',                  "{$c}@close");
    $router->get('/(\d+)/statement',               "{$c}@statement");
    $router->post('/(\d+)/statement/email',        "{$c}@emailStatement");
});

// ── Traceability ────────────────────────────────────────────────────
$router->mount('/traceability', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\TraceabilityController';
    $router->get('/',                  "{$c}@landing");
    $router->get('/raw-material',      "{$c}@rawMaterial");
    $router->post('/raw-material',     "{$c}@rawMaterial");
    $router->get('/finished-good',     "{$c}@finishedGood");
    $router->post('/finished-good',    "{$c}@finishedGood");
    $router->get('/complaint',         "{$c}@complaint");
    $router->post('/complaint',        "{$c}@complaint");
    $router->get('/export',            "{$c}@export");
});

// ── CRM ─────────────────────────────────────────────────────────────
$router->mount('/crm', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\CrmController';
    $router->get('/',                    "{$c}@landing");
    $router->get('/dashboard',           "{$c}@dashboard");
    $router->get('/tasks',               "{$c}@taskList");
    $router->get('/tasks/(\d+)/complete',  "{$c}@completeTask");
    $router->post('/tasks/(\d+)/complete', "{$c}@completeTask");
    $router->post('/tasks/(\d+)/cancel',   "{$c}@cancelTask");
    $router->post('/tasks/bulk',         "{$c}@bulkTasks");
    $router->post('/tasks/create',       "{$c}@taskCreate");
    $router->post('/activity/create',    "{$c}@activityCreate");
});

// ── Reports ─────────────────────────────────────────────────────────
$router->mount('/reports', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ReportController';
    $router->get('/',                                "{$c}@reportIndex");
    // CRM reports (existing individual views)
    $router->get('/crm/rep-activity',                "{$c}@crmRepActivity");
    $router->get('/crm/contact-frequency',           "{$c}@crmContactFrequency");
    $router->get('/crm/tasks',                       "{$c}@crmTaskReport");
    $router->get('/crm/rep-customers',               "{$c}@crmRepCustomers");
    $router->get('/crm/next-contact',                "{$c}@crmNextContact");
    // Generic report system
    $router->get('/(\w+)/([\w-]+)/csv',              "{$c}@exportCsv");
    $router->get('/(\w+)/([\w-]+)/pdf',              "{$c}@exportPdf");
    $router->get('/(\w+)/([\w-]+)',                   "{$c}@runReport");
    $router->post('/(\w+)/([\w-]+)',                  "{$c}@runReport");
});

// ── Import / Export ──────────────────────────────────────────────────
$router->mount('/import', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ImportExportController';
    $router->get('/',                          "{$c}@importLanding");
    $router->get('/([\w-]+)',                  "{$c}@importModule");
    $router->post('/([\w-]+)',                 "{$c}@processImport");
    $router->get('/([\w-]+)/template',         "{$c}@downloadTemplate");
    $router->get('/([\w-]+)/sample',           "{$c}@downloadSample");
});
$router->mount('/export', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ImportExportController';
    $router->get('/',                          "{$c}@exportLanding");
    $router->post('/([\w-]+)',                 "{$c}@exportModule");
});

// ── API ─────────────────────────────────────────────────────────────
$router->get('/api/docs', 'PrecisionInk\\Controllers\\ApiController@docs');
$router->mount('/api/v1', function () use ($router) {
    $c = 'PrecisionInk\\Controllers\\ApiController';
    $router->get('/health',                        "{$c}@health");
    $router->get('/schema-version',                "{$c}@schemaVersion");
    $router->get('/items',                         "{$c}@items");
    $router->get('/items/(\d+)',                   "{$c}@itemDetail");
    $router->get('/items/(\d+)/recipe',            'PrecisionInk\\Controllers\\RecipeController@sdsApi');
    $router->get('/items/(\d+)/inventory',         "{$c}@itemInventory");
    $router->get('/items/(\d+)/qc-spec',           "{$c}@itemQcSpec");
    $router->get('/batches',                       "{$c}@batches");
    $router->get('/batches/([\w-]+)',              "{$c}@batchDetail");
    $router->get('/customers',                     "{$c}@customers");
    $router->get('/customers/(\d+)',               "{$c}@customerDetail");
    $router->get('/suppliers',                     "{$c}@suppliers");
    $router->get('/suppliers/(\d+)',               "{$c}@supplierDetail");
    $router->get('/inventory/lots',                "{$c}@inventoryLots");
    $router->post('/inventory/adjustments',        "{$c}@inventoryAdjustment");
    $router->get('/purchase-orders',               "{$c}@purchaseOrders");
    $router->get('/purchase-orders/(\d+)',          "{$c}@purchaseOrderDetail");
    $router->get('/sales-orders',                  "{$c}@salesOrders");
    $router->get('/sales-orders/(\d+)',            "{$c}@salesOrderDetail");
    $router->get('/traceability/raw-material',     "{$c}@traceRawMaterial");
    $router->get('/traceability/finished-good',    "{$c}@traceFinishedGood");
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

    // Pack Extension Types
    $router->get('/pack-extensions',                   "{$c}@packExtensionTypes");
    $router->get('/pack-extensions/create',            "{$c}@packExtensionTypeForm");
    $router->get('/pack-extensions/(\d+)/edit',        "{$c}@packExtensionTypeForm");
    $router->post('/pack-extensions/save',             "{$c}@savePackExtensionType");

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
    $router->get('/audit-log',                     "{$c}@auditLogView");
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

    // Document Templates
    $router->get('/document-templates',                    "{$c}@documentTemplates");
    $router->get('/document-templates/([\w-]+)/edit',      "{$c}@editDocumentTemplate");
    $router->post('/document-templates/([\w-]+)/edit',     "{$c}@saveDocumentTemplate");
    $router->post('/document-templates/([\w-]+)/preview',  "{$c}@previewDocumentTemplate");

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
