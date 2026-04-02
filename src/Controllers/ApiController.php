<?php

namespace PrecisionInk\Controllers;

class ApiController extends BaseController
{
    private ?array $apiKey = null;
    private float $startTime;

    private function authenticate(bool $requireWrite = false): bool
    {
        $this->startTime = microtime(true);
        header('Content-Type: application/json');
        header('X-API-Version: 1.0');

        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (!$key) {
            $this->apiError('Unauthorized', 'Missing X-API-Key header', 401);
            return false;
        }

        $hash = hash('sha256', $key);
        $stmt = $this->db()->prepare('SELECT * FROM api_keys WHERE key_hash = ? AND active = 1');
        $stmt->execute([$hash]);
        $this->apiKey = $stmt->fetch();

        if (!$this->apiKey) {
            $this->apiError('Unauthorized', 'Invalid or expired API key', 401);
            return false;
        }

        // Update last_used_at
        $this->db()->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$this->apiKey['id']]);

        return true;
    }

    private function apiSuccess(mixed $data, array $meta = []): void
    {
        $meta['generated_at'] = date('c');
        $this->logRequest(200);
        echo json_encode(['data' => $data, 'meta' => $meta], JSON_PRETTY_PRINT);
        exit;
    }

    private function apiPaginated(array $data, int $total, int $page, int $perPage): void
    {
        $this->logRequest(200);
        echo json_encode([
            'data' => $data,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, ceil($total / $perPage)), 'generated_at' => date('c')],
        ], JSON_PRETTY_PRINT);
        exit;
    }

    private function apiError(string $error, string $message, int $code = 400): void
    {
        http_response_code($code);
        $this->logRequest($code);
        echo json_encode(['error' => $error, 'message' => $message]);
        exit;
    }

    private function logRequest(int $code): void
    {
        $duration = isset($this->startTime) ? (int)((microtime(true) - $this->startTime) * 1000) : 0;
        try {
            $this->db()->prepare('INSERT INTO api_request_log (api_key_id, method, path, query_params, response_code, duration_ms) VALUES (?,?,?,?,?,?)')
                ->execute([$this->apiKey['id'] ?? null, $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING'] ?? null, $code, $duration]);
        } catch (\Throwable $e) {} // Don't fail request on log error
    }

    private function paginate(string $sql, array $params, string $countSql, array $countParams): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $cStmt = $this->db()->prepare($countSql);
        $cStmt->execute($countParams);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $this->db()->prepare($sql . " LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        $this->apiPaginated($stmt->fetchAll(), $total, $page, $perPage);
    }

    // ── Health ──────────────────────────────────────────────────────

    public function health(): void
    {
        header('Content-Type: application/json');
        $dbOk = false;
        try { $this->db()->query('SELECT 1'); $dbOk = true; } catch (\Throwable $e) {}
        echo json_encode(['status' => $dbOk ? 'ok' : 'degraded', 'timestamp' => date('c'), 'version' => '1.0', 'db' => $dbOk ? 'connected' : 'error']);
        exit;
    }

    public function schemaVersion(): void
    {
        if (!$this->authenticate()) return;
        $count = (int)$this->db()->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
        $this->apiSuccess(['schema_version' => $count]);
    }

    // ── Items ───────────────────────────────────────────────────────

    public function items(): void
    {
        if (!$this->authenticate()) return;

        $where = ['i.deleted_at IS NULL'];
        $params = [];
        $q = trim($_GET['q'] ?? '');
        if ($q) { $where[] = '(i.item_code LIKE ? OR i.description LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
        if (isset($_GET['item_type'])) { $where[] = 'i.item_type = ?'; $params[] = $_GET['item_type']; }
        $active = $_GET['active'] ?? '1';
        if ($active !== '') { $where[] = 'i.active = ?'; $params[] = (int)$active; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT i.id, i.item_code, i.description, i.item_type, i.gl_group, u.abbreviation as uom, i.unit_cost, i.sale_price, i.active FROM items i LEFT JOIN uom u ON i.uom_id = u.id WHERE {$wc} ORDER BY i.item_code",
            $params,
            "SELECT COUNT(*) FROM items i WHERE {$wc}",
            $params
        );
    }

    public function itemDetail(string $id): void
    {
        if (!$this->authenticate()) return;

        $stmt = $this->db()->prepare("SELECT i.*, u.abbreviation as uom FROM items i LEFT JOIN uom u ON i.uom_id = u.id WHERE i.id = ? AND i.deleted_at IS NULL");
        $stmt->execute([(int)$id]);
        $item = $stmt->fetch();
        if (!$item) $this->apiError('Not Found', 'Item not found', 404);

        $packs = $this->db()->prepare("SELECT id, name, net_weight, tare_weight, active FROM item_pack_extensions WHERE item_id = ?")->execute([(int)$id]) ? [] : [];
        $pStmt = $this->db()->prepare("SELECT id, name, net_weight, tare_weight, active FROM item_pack_extensions WHERE item_id = ?");
        $pStmt->execute([(int)$id]);
        $item['pack_extensions'] = $pStmt->fetchAll();

        $aStmt = $this->db()->prepare("SELECT id, alias_code, alias_description, customer_id FROM item_aliases WHERE item_id = ? AND active = 1");
        $aStmt->execute([(int)$id]);
        $item['aliases'] = $aStmt->fetchAll();

        $this->apiSuccess($item);
    }

    public function itemInventory(string $id): void
    {
        if (!$this->authenticate()) return;

        $item = $this->db()->prepare("SELECT id, item_code FROM items WHERE id = ?")->execute([(int)$id]) ? null : null;
        $iStmt = $this->db()->prepare("SELECT id, item_code FROM items WHERE id = ?");
        $iStmt->execute([(int)$id]);
        $item = $iStmt->fetch();
        if (!$item) $this->apiError('Not Found', 'Item not found', 404);

        $allFac = $this->fifoService->getAvailableAllFacilities((int)$id);
        $facilities = [];
        foreach ($allFac as $fId => $fData) {
            $facilities[] = ['facility_id' => $fId, 'facility_name' => $fData['facility_name'], 'on_hand' => $fData['on_hand'], 'available' => $fData['available']];
        }

        $this->apiSuccess(['item_id' => (int)$item['id'], 'item_code' => $item['item_code'], 'facilities' => $facilities]);
    }

    public function itemQcSpec(string $id): void
    {
        if (!$this->authenticate()) return;

        $spec = $this->db()->prepare("SELECT * FROM qc_specs WHERE item_id = ? AND is_active = 1 LIMIT 1");
        $spec->execute([(int)$id]);
        $spec = $spec->fetch();
        if (!$spec) $this->apiSuccess(null);

        $tests = $this->db()->prepare("SELECT id, test_name, test_type, min_value, max_value, uom, is_required, display_sequence FROM qc_spec_tests WHERE spec_id = ? ORDER BY display_sequence");
        $tests->execute([$spec['id']]);
        $spec['tests'] = $tests->fetchAll();

        $this->apiSuccess($spec);
    }

    // ── Batches ─────────────────────────────────────────────────────

    public function batches(): void
    {
        if (!$this->authenticate()) return;

        $where = ['bt.deleted_at IS NULL'];
        $params = [];
        if (isset($_GET['status'])) { $where[] = 'bt.status = ?'; $params[] = $_GET['status']; }
        if (isset($_GET['item_id'])) { $where[] = 'bt.item_id = ?'; $params[] = (int)$_GET['item_id']; }
        if (isset($_GET['date_from'])) { $where[] = 'bt.scheduled_date >= ?'; $params[] = $_GET['date_from']; }
        if (isset($_GET['date_to'])) { $where[] = 'bt.scheduled_date <= ?'; $params[] = $_GET['date_to']; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT bt.id, bt.batch_number, bt.status, i.item_code, f.name as facility, bt.scheduled_date, bt.actual_yield, bt.yield_percentage, bt.cost_per_unit FROM batch_tickets bt JOIN items i ON bt.item_id=i.id LEFT JOIN facilities f ON bt.facility_id=f.id WHERE {$wc} ORDER BY bt.scheduled_date DESC",
            $params,
            "SELECT COUNT(*) FROM batch_tickets bt WHERE {$wc}",
            $params
        );
    }

    public function batchDetail(string $batchNumber): void
    {
        if (!$this->authenticate()) return;

        $stmt = $this->db()->prepare("SELECT bt.*, i.item_code, i.description, f.name as facility FROM batch_tickets bt JOIN items i ON bt.item_id=i.id LEFT JOIN facilities f ON bt.facility_id=f.id WHERE bt.batch_number = ?");
        $stmt->execute([$batchNumber]);
        $batch = $stmt->fetch();
        if (!$batch) $this->apiError('Not Found', 'Batch not found', 404);

        $ingredients = $this->db()->prepare("SELECT bil.*, i.item_code, i.description FROM batch_ingredient_lots bil JOIN items i ON bil.ingredient_item_id=i.id WHERE bil.batch_id=?");
        $ingredients->execute([$batch['id']]);
        $batch['ingredients'] = $ingredients->fetchAll();

        $qc = $this->db()->prepare("SELECT qr.*, qst.test_name, qst.test_type FROM qc_results qr JOIN qc_spec_tests qst ON qr.test_id=qst.id WHERE qr.batch_id=?");
        $qc->execute([$batch['id']]);
        $batch['qc_results'] = $qc->fetchAll();

        $packs = $this->db()->prepare("SELECT bp.*, pe.name FROM batch_ticket_packs bp JOIN item_pack_extensions pe ON bp.pack_extension_id=pe.id WHERE bp.batch_id=?");
        $packs->execute([$batch['id']]);
        $batch['packs'] = $packs->fetchAll();

        $this->apiSuccess($batch);
    }

    // ── Customers / Suppliers ───────────────────────────────────────

    public function customers(): void
    {
        if (!$this->authenticate()) return;
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        $q = trim($_GET['q'] ?? '');
        if ($q) { $where[] = '(c.customer_code LIKE ? OR c.company_name LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
        $active = $_GET['active'] ?? '1';
        if ($active !== '') { $where[] = 'c.active = ?'; $params[] = (int)$active; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT c.id, c.customer_code, c.company_name, c.billing_city, c.billing_state, c.phone, c.active FROM customers c WHERE {$wc} ORDER BY c.customer_code",
            $params, "SELECT COUNT(*) FROM customers c WHERE {$wc}", $params
        );
    }

    public function customerDetail(string $id): void
    {
        if (!$this->authenticate()) return;
        $stmt = $this->db()->prepare("SELECT c.id, c.customer_code, c.company_name, c.billing_street, c.billing_city, c.billing_state, c.billing_zip, c.phone, c.active FROM customers c WHERE c.id=? AND c.deleted_at IS NULL");
        $stmt->execute([(int)$id]);
        $cust = $stmt->fetch();
        if (!$cust) $this->apiError('Not Found', 'Customer not found', 404);

        $stStmt = $this->db()->prepare("SELECT id, location_name, city, state, active FROM ship_to_locations WHERE customer_id=? AND deleted_at IS NULL");
        $stStmt->execute([(int)$id]);
        $cust['ship_to_locations'] = $stStmt->fetchAll();

        $this->apiSuccess($cust);
    }

    public function suppliers(): void
    {
        if (!$this->authenticate()) return;
        $where = ['s.deleted_at IS NULL'];
        $params = [];
        $q = trim($_GET['q'] ?? '');
        if ($q) { $where[] = '(s.supplier_code LIKE ? OR s.company_name LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT s.id, s.supplier_code, s.company_name, s.city, s.state, s.phone, s.active FROM suppliers s WHERE {$wc} ORDER BY s.supplier_code",
            $params, "SELECT COUNT(*) FROM suppliers s WHERE {$wc}", $params
        );
    }

    public function supplierDetail(string $id): void
    {
        if (!$this->authenticate()) return;
        $stmt = $this->db()->prepare("SELECT s.id, s.supplier_code, s.company_name, s.street, s.city, s.state, s.zip, s.phone, s.active FROM suppliers s WHERE s.id=? AND s.deleted_at IS NULL");
        $stmt->execute([(int)$id]);
        $supp = $stmt->fetch();
        if (!$supp) $this->apiError('Not Found', 'Supplier not found', 404);

        $avl = $this->db()->prepare("SELECT avl.*, i.item_code FROM approved_vendor_list avl JOIN items i ON avl.item_id=i.id WHERE avl.supplier_id=? AND avl.active=1");
        $avl->execute([(int)$id]);
        $supp['avl_items'] = $avl->fetchAll();

        $this->apiSuccess($supp);
    }

    // ── Inventory Lots ──────────────────────────────────────────────

    public function inventoryLots(): void
    {
        if (!$this->authenticate()) return;
        $where = ['fl.remaining_quantity > 0'];
        $params = [];
        if (isset($_GET['item_id'])) { $where[] = 'fl.item_id = ?'; $params[] = (int)$_GET['item_id']; }
        if (isset($_GET['facility_id'])) { $where[] = 'fl.facility_id = ?'; $params[] = (int)$_GET['facility_id']; }
        if (isset($_GET['lot_number'])) { $where[] = 'fl.lot_number LIKE ?'; $params[] = '%' . $_GET['lot_number'] . '%'; }
        if (isset($_GET['status'])) { $where[] = 'fl.status = ?'; $params[] = $_GET['status']; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT fl.id, i.item_code, f.name as facility, fl.lot_number, fl.remaining_quantity, fl.unit_cost, fl.status, fl.expiration_date, fl.source_type FROM fifo_lots fl JOIN items i ON fl.item_id=i.id JOIN facilities f ON fl.facility_id=f.id WHERE {$wc} ORDER BY fl.created_at DESC",
            $params, "SELECT COUNT(*) FROM fifo_lots fl WHERE {$wc}", $params
        );
    }

    public function inventoryAdjustment(): void
    {
        if (!$this->authenticate(true)) return;

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $this->apiError('Bad Request', 'Invalid JSON body', 400);

        $itemId = (int)($input['item_id'] ?? 0);
        $facilityId = (int)($input['facility_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);

        if (!$itemId || !$facilityId || $qty == 0) $this->apiError('Bad Request', 'item_id, facility_id, and non-zero quantity required', 400);

        try {
            if ($qty > 0) {
                $lotId = $this->fifoService->addLot($itemId, $facilityId, $qty, (float)($input['unit_cost'] ?? 0), $input['lot_number'] ?? 'API-ADJ-' . date('Ymd-His'), 'ADJUSTMENT', 0, null, 'AVAILABLE', 0);
                $this->apiSuccess(['action' => 'added', 'lot_id' => $lotId, 'quantity' => $qty]);
            } else {
                $consumed = $this->fifoService->consume($itemId, $facilityId, abs($qty), 'ADJUSTMENT', 0, 0);
                $this->apiSuccess(['action' => 'consumed', 'lots_consumed' => $consumed, 'quantity' => abs($qty)]);
            }
        } catch (\App\Exceptions\NegativeInventoryException $e) {
            $this->apiError('Conflict', $e->getMessage(), 409);
        }
    }

    // ── Purchase Orders / Sales Orders ──────────────────────────────

    public function purchaseOrders(): void
    {
        if (!$this->authenticate()) return;
        $where = ['po.deleted_at IS NULL'];
        $params = [];
        if (isset($_GET['supplier_id'])) { $where[] = 'po.supplier_id = ?'; $params[] = (int)$_GET['supplier_id']; }
        if (isset($_GET['status'])) { $where[] = 'po.status = ?'; $params[] = $_GET['status']; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT po.id, po.po_number, s.company_name as supplier, po.order_date, po.status FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id WHERE {$wc} ORDER BY po.created_at DESC",
            $params, "SELECT COUNT(*) FROM purchase_orders po WHERE {$wc}", $params
        );
    }

    public function purchaseOrderDetail(string $id): void
    {
        if (!$this->authenticate()) return;
        $stmt = $this->db()->prepare("SELECT po.*, s.company_name as supplier FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id=s.id WHERE po.id=?");
        $stmt->execute([(int)$id]);
        $po = $stmt->fetch();
        if (!$po) $this->apiError('Not Found', 'PO not found', 404);

        $lines = $this->db()->prepare("SELECT pol.*, i.item_code FROM purchase_order_lines pol JOIN items i ON pol.item_id=i.id WHERE pol.po_id=?");
        $lines->execute([(int)$id]);
        $po['lines'] = $lines->fetchAll();

        $this->apiSuccess($po);
    }

    public function salesOrders(): void
    {
        if (!$this->authenticate()) return;
        $where = ['so.deleted_at IS NULL'];
        $params = [];
        if (isset($_GET['customer_id'])) { $where[] = 'so.customer_id = ?'; $params[] = (int)$_GET['customer_id']; }
        if (isset($_GET['status'])) { $where[] = 'so.status = ?'; $params[] = $_GET['status']; }
        $wc = implode(' AND ', $where);

        $this->paginate(
            "SELECT so.id, so.so_number, c.company_name as customer, so.order_date, so.status FROM sales_orders so LEFT JOIN customers c ON so.customer_id=c.id WHERE {$wc} ORDER BY so.created_at DESC",
            $params, "SELECT COUNT(*) FROM sales_orders so WHERE {$wc}", $params
        );
    }

    public function salesOrderDetail(string $id): void
    {
        if (!$this->authenticate()) return;
        $stmt = $this->db()->prepare("SELECT so.*, c.company_name as customer FROM sales_orders so LEFT JOIN customers c ON so.customer_id=c.id WHERE so.id=?");
        $stmt->execute([(int)$id]);
        $so = $stmt->fetch();
        if (!$so) $this->apiError('Not Found', 'SO not found', 404);

        $lines = $this->db()->prepare("SELECT sol.*, i.item_code FROM sales_order_lines sol JOIN items i ON sol.item_id=i.id WHERE sol.so_id=?");
        $lines->execute([(int)$id]);
        $so['lines'] = $lines->fetchAll();

        $this->apiSuccess($so);
    }

    // ── Traceability ────────────────────────────────────────────────

    public function traceRawMaterial(): void
    {
        if (!$this->authenticate()) return;
        $lot = trim($_GET['lot'] ?? '');
        if (!$lot) $this->apiError('Bad Request', 'lot parameter required', 400);

        $svc = new \App\Services\LotTraceabilityService($this->db());
        $this->apiSuccess($svc->traceRawMaterialLot($lot));
    }

    public function traceFinishedGood(): void
    {
        if (!$this->authenticate()) return;
        $batch = trim($_GET['batch'] ?? '');
        if (!$batch) $this->apiError('Bad Request', 'batch parameter required', 400);

        $svc = new \App\Services\LotTraceabilityService($this->db());
        $this->apiSuccess($svc->traceFinishedGoodLot($batch));
    }

    // ── API Docs ────────────────────────────────────────────────────

    public function docs(): void
    {
        $endpoints = [
            ['GET', '/api/v1/health', 'Health check (no auth)', '{"status":"ok","timestamp":"...","version":"1.0","db":"connected"}'],
            ['GET', '/api/v1/items', 'List items. Params: q, item_type, active, page, per_page', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/items/{id}', 'Item detail with packs and aliases', '{"data":{...}}'],
            ['GET', '/api/v1/items/{id}/recipe', 'Active recipe versions with ingredients', '{"data":{...}}'],
            ['GET', '/api/v1/items/{id}/inventory', 'On-hand and available per facility', '{"data":{...}}'],
            ['GET', '/api/v1/items/{id}/qc-spec', 'Active QC spec with tests', '{"data":{...}}'],
            ['GET', '/api/v1/batches', 'List batches. Params: status, item_id, date_from, date_to', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/batches/{batchNumber}', 'Batch detail with ingredients, QC, packs', '{"data":{...}}'],
            ['GET', '/api/v1/customers', 'List customers. Params: q, active', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/customers/{id}', 'Customer with ship-to locations', '{"data":{...}}'],
            ['GET', '/api/v1/suppliers', 'List suppliers. Params: q', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/suppliers/{id}', 'Supplier with AVL items', '{"data":{...}}'],
            ['GET', '/api/v1/inventory/lots', 'FIFO lots. Params: item_id, facility_id, lot_number, status', '{"data":[...],"meta":{...}}'],
            ['POST', '/api/v1/inventory/adjustments', 'Create adjustment (requires write_access). Body: {item_id, facility_id, quantity, unit_cost, lot_number}', '{"data":{...}}'],
            ['GET', '/api/v1/purchase-orders', 'List POs. Params: supplier_id, status', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/purchase-orders/{id}', 'PO detail with lines', '{"data":{...}}'],
            ['GET', '/api/v1/sales-orders', 'List SOs. Params: customer_id, status', '{"data":[...],"meta":{...}}'],
            ['GET', '/api/v1/sales-orders/{id}', 'SO detail with lines', '{"data":{...}}'],
            ['GET', '/api/v1/traceability/raw-material?lot=X', 'Trace raw material lot forward', '{"data":{...}}'],
            ['GET', '/api/v1/traceability/finished-good?batch=X', 'Trace finished good lot', '{"data":{...}}'],
        ];

        $html = '<!DOCTYPE html><html><head><title>API Documentation — Precision Ink ERP</title><style>body{font-family:Arial,sans-serif;max-width:900px;margin:40px auto;font-size:13px;}h1{color:#1d4ed8;}table{width:100%;border-collapse:collapse;margin:16px 0;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f5f5f5;}.get{color:#16a34a;font-weight:bold;}.post{color:#f59e0b;font-weight:bold;}code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-size:12px;}</style></head><body>';
        $html .= '<h1>Precision Ink ERP — API v1</h1><p>All endpoints require <code>X-API-Key</code> header unless noted. Base URL: <code>/api/v1</code></p>';
        $html .= '<table><thead><tr><th>Method</th><th>Endpoint</th><th>Description</th><th>Example Response</th></tr></thead><tbody>';
        foreach ($endpoints as $ep) {
            $cls = strtolower($ep[0]);
            $html .= '<tr><td class="' . $cls . '">' . $ep[0] . '</td><td><code>' . htmlspecialchars($ep[1]) . '</code></td><td>' . htmlspecialchars($ep[2]) . '</td><td><code>' . htmlspecialchars(substr($ep[3], 0, 50)) . '...</code></td></tr>';
        }
        $html .= '</tbody></table></body></html>';
        echo $html;
        exit;
    }
}
