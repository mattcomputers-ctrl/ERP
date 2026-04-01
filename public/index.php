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
    // TODO: system configuration, user management, roles
});

// Dispatch
$router->run();
