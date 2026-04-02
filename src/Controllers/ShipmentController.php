<?php

namespace PrecisionInk\Controllers;

class ShipmentController extends BaseController
{
    // ── Shipping Screen ─────────────────────────────────────────────

    public function createForm(): void
    {
        if (!$this->checkPermission('shipments', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $soIds = $_GET['so_ids'] ?? [];
        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($active['id'] ?? 0);

        if (empty($soIds)) {
            // Show SO picker
            $stmt = $this->db()->prepare("
                SELECT so.id, so.so_number, c.company_name, st.location_name as ship_to_name,
                       so.promised_ship_date, so.status
                FROM sales_orders so
                JOIN customers c ON so.customer_id = c.id
                LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
                WHERE so.facility_id = ? AND so.status IN ('CONFIRMED','PARTIAL') AND so.deleted_at IS NULL
                ORDER BY so.promised_ship_date ASC
            ");
            $stmt->execute([$facilityId]);
            $this->renderView('shipments/select_orders', ['orders' => $stmt->fetchAll(), 'facilityId' => $facilityId]);
            return;
        }

        // Load SO details and lines
        $placeholders = implode(',', array_fill(0, count($soIds), '?'));
        $soStmt = $this->db()->prepare("
            SELECT so.*, c.customer_code, c.company_name, c.account_hold, c.account_hold_reason,
                   c.credit_limit, c.ar_balance, st.location_name as ship_to_name,
                   st.street as st_street, st.city as st_city, st.state as st_state, st.zip as st_zip,
                   sv.name as ship_via_name, sv.transit_days
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            LEFT JOIN ship_to_locations st ON so.ship_to_id = st.id
            LEFT JOIN ship_via sv ON so.ship_via_id = sv.id
            WHERE so.id IN ({$placeholders})
        ");
        $soStmt->execute(array_map('intval', $soIds));
        $orders = $soStmt->fetchAll();

        // Pre-checks
        $errors = [];
        foreach ($orders as $so) {
            if ($so['account_hold']) $errors[] = "Account hold: {$so['company_name']} — {$so['account_hold_reason']}";
            if ($so['status'] === 'ON_HOLD') $errors[] = "Order hold: {$so['so_number']} — {$so['hold_reason']}";
        }
        if (!empty($errors)) {
            $this->toast(implode(' | ', $errors), 'error');
            $this->redirect('/shipping/create');
            return;
        }

        // Credit check
        $creditWarnings = [];
        $customersChecked = [];
        foreach ($orders as $so) {
            if (isset($customersChecked[$so['customer_id']])) continue;
            $customersChecked[$so['customer_id']] = true;
            if ($this->creditService) {
                $check = $this->creditService->checkAtShipment((int)$so['customer_id']);
                if ($check['would_exceed'] && !$this->hasSpecialPermission('credit_override')) {
                    $errors[] = "Credit limit exceeded for {$so['company_name']}. Requires Credit Override permission.";
                } elseif ($check['would_exceed']) {
                    $creditWarnings[] = "{$so['company_name']}: exposure \${$check['total_exposure']} vs limit \${$check['credit_limit']}";
                }
            }
        }
        if (!empty($errors)) {
            $this->toast(implode(' | ', $errors), 'error');
            $this->redirect('/shipping/create');
            return;
        }

        // Get unshipped lines per SO
        $allLines = [];
        foreach ($orders as $so) {
            $lineStmt = $this->db()->prepare("
                SELECT sol.*, i.item_code, i.description as item_description, pe.name as pack_name,
                       u.abbreviation as uom_abbr, COALESCE(ifl.location, '') as location
                FROM sales_order_lines sol
                JOIN items i ON sol.item_id = i.id
                LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
                LEFT JOIN uom u ON sol.uom_id = u.id
                LEFT JOIN item_facility_locations ifl ON ifl.item_id = sol.item_id AND ifl.facility_id = ?
                WHERE sol.so_id = ? AND sol.ordered_quantity > sol.shipped_quantity
            ");
            $lineStmt->execute([$facilityId, $so['id']]);
            $allLines[$so['id']] = $lineStmt->fetchAll();
        }

        $shipVias = $this->db()->query("SELECT id, name, transit_days FROM ship_via WHERE active=1 ORDER BY name")->fetchAll();

        $this->renderView('shipments/create', [
            'orders' => $orders, 'allLines' => $allLines,
            'facilityId' => $facilityId, 'shipVias' => $shipVias,
            'creditWarnings' => $creditWarnings,
        ]);
    }

    public function store(): void
    {
        if (!$this->checkPermission('shipments', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $shipDate = $_POST['ship_date'] ?? date('Y-m-d');
        $shipViaId = (int)($_POST['ship_via_id'] ?? 0) ?: null;
        $trackingNumber = trim($_POST['tracking_number'] ?? '') ?: null;
        $freightCost = (float)($_POST['freight_cost'] ?? 0);
        $shipToId = (int)($_POST['ship_to_id'] ?? 0);
        $soIds = $_POST['so_ids'] ?? [];
        $lineData = $_POST['lines'] ?? [];

        if (empty($soIds) || empty($lineData)) {
            $this->toast('No lines to ship.', 'error');
            $this->redirect('/shipping/create');
            return;
        }

        $this->db()->beginTransaction();
        try {
            $shipmentNumber = $this->generateDocumentNumber('SHIPMENT');

            $this->db()->prepare("
                INSERT INTO shipments (shipment_number, facility_id, ship_date, ship_via_id, tracking_number, freight_cost, ship_to_id, sales_rep_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$shipmentNumber, $facilityId, $shipDate, $shipViaId, $trackingNumber, $freightCost ?: null, $shipToId, null, $userId]);
            $shipmentId = (int)$this->db()->lastInsertId();

            // Shipment orders
            $soInsert = $this->db()->prepare("INSERT INTO shipment_orders (shipment_id, so_id) VALUES (?, ?)");
            foreach ($soIds as $soId) {
                $soInsert->execute([$shipmentId, (int)$soId]);
            }

            // Process lines
            $lineInsert = $this->db()->prepare("INSERT INTO shipment_lines (shipment_id, so_line_id, quantity_shipped, unit_price, packing_slip_note) VALUES (?,?,?,?,?)");
            $lotInsert = $this->db()->prepare("INSERT INTO shipment_line_lots (shipment_line_id, lot_number, quantity_shipped, fifo_lot_id) VALUES (?,?,?,?)");
            $subtotal = 0;

            foreach ($lineData as $soLineId => $ld) {
                $qtyShipped = (float)($ld['quantity_shipped'] ?? 0);
                if ($qtyShipped <= 0) continue;

                $unitPrice = (float)($ld['unit_price'] ?? 0);
                $packingNote = trim($ld['packing_slip_note'] ?? '') ?: null;

                $lineInsert->execute([$shipmentId, (int)$soLineId, $qtyShipped, $unitPrice, $packingNote]);
                $shipLineId = (int)$this->db()->lastInsertId();
                $subtotal += $qtyShipped * $unitPrice;

                // Get item_id for FIFO consumption
                $solStmt = $this->db()->prepare("SELECT item_id FROM sales_order_lines WHERE id = ?");
                $solStmt->execute([(int)$soLineId]);
                $itemId = (int)$solStmt->fetchColumn();

                // Consume from FIFO
                $consumed = $this->fifoService->consume($itemId, $facilityId, $qtyShipped, 'SHIPMENT', $shipmentId, $userId);
                foreach ($consumed as $c) {
                    $lotInsert->execute([$shipLineId, $c['lot_number'], $c['quantity_consumed'], $c['lot_id']]);
                }

                // Update SO line
                $this->db()->prepare("UPDATE sales_order_lines SET shipped_quantity = shipped_quantity + ?, backordered_quantity = ordered_quantity - shipped_quantity - ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$qtyShipped, $qtyShipped, (int)$soLineId]);
            }

            // Update SO statuses
            foreach ($soIds as $soId) {
                $remaining = $this->db()->prepare("SELECT SUM(ordered_quantity - shipped_quantity) FROM sales_order_lines WHERE so_id = ? AND ordered_quantity > shipped_quantity");
                $remaining->execute([(int)$soId]);
                $rem = (float)$remaining->fetchColumn();
                $newStatus = $rem > 0 ? 'PARTIAL' : 'SHIPPED';
                $this->db()->prepare("UPDATE sales_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, (int)$soId]);
            }

            // Create invoice
            $invoiceNumber = $this->generateDocumentNumber('INVOICE');
            $firstSo = $this->db()->prepare("SELECT so.*, c.id as cust_id, pt.net_days FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id LEFT JOIN payment_terms pt ON so.payment_terms_id = pt.id WHERE so.id = ?");
            $firstSo->execute([(int)$soIds[0]]);
            $soData = $firstSo->fetch();

            $netDays = (int)($soData['net_days'] ?? 30);
            $dueDate = date('Y-m-d', strtotime($shipDate . " + {$netDays} days"));
            $depositAmount = (float)($soData['deposit_amount'] ?? 0);
            $totalDue = $subtotal + $freightCost - $depositAmount;

            $this->db()->prepare("
                INSERT INTO invoices (invoice_number, shipment_id, customer_id, so_id, invoice_date, due_date, payment_terms_id, ship_via_id, subtotal, freight_amount, deposit_amount, total_due, sales_rep_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'OPEN')
            ")->execute([
                $invoiceNumber, $shipmentId, (int)$soData['customer_id'], (int)$soIds[0],
                $shipDate, $dueDate, $soData['payment_terms_id'], $shipViaId,
                $subtotal, $freightCost, $depositAmount, max(0, $totalDue),
                $soData['sales_rep_id'],
            ]);
            $invoiceId = (int)$this->db()->lastInsertId();

            $this->db()->commit();

            $this->auditCreate('shipments', $shipmentId, ['shipment_number' => $shipmentNumber]);
            $this->auditCreate('invoices', $invoiceId, ['invoice_number' => $invoiceNumber]);
            $this->toast("Shipment {$shipmentNumber} created. Invoice {$invoiceNumber} generated.", 'success');
            $this->redirect("/shipping/{$shipmentId}");
        } catch (\App\Exceptions\NegativeInventoryException $e) {
            $this->db()->rollBack();
            $this->toast('Inventory shortfall: ' . $e->getMessage(), 'error');
            $this->redirect('/shipping/create?' . http_build_query(['so_ids' => $soIds]));
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
            $this->redirect('/shipping/create');
        }
    }

    // ── View Shipment ───────────────────────────────────────────────

    public function show(string $id): void
    {
        if (!$this->checkPermission('shipments', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $shipment = $this->getShipmentOrFail((int)$id);

        $lines = $this->db()->prepare("
            SELECT sl.*, sol.item_id, i.item_code, i.description as item_description,
                   pe.name as pack_name, u.abbreviation as uom_abbr
            FROM shipment_lines sl
            JOIN sales_order_lines sol ON sl.so_line_id = sol.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            LEFT JOIN uom u ON sol.uom_id = u.id
            WHERE sl.shipment_id = ?
        ");
        $lines->execute([(int)$id]);

        $invoice = $this->db()->prepare("SELECT * FROM invoices WHERE shipment_id = ? LIMIT 1");
        $invoice->execute([(int)$id]);

        $this->renderView('shipments/view', [
            'shipment' => $shipment, 'lines' => $lines->fetchAll(),
            'invoice' => $invoice->fetch() ?: null,
        ]);
    }

    // ── Delivery Date ───────────────────────────────────────────────

    public function updateDeliveryDate(string $id): void
    {
        if (!$this->checkPermission('shipments', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $date = $_POST['actual_delivery_date'] ?? '';
        if ($date) {
            $this->db()->prepare("UPDATE shipments SET actual_delivery_date = ?, updated_at = NOW() WHERE id = ?")->execute([$date, (int)$id]);
            $this->auditLog('UPDATE', 'shipments', (int)$id, [], ['actual_delivery_date' => $date]);
        }
        $this->toast('Delivery date updated.', 'success');
        $this->redirect("/shipping/{$id}");
    }

    // ── Packing Slip PDF ────────────────────────────────────────────

    public function packingSlip(string $id): void
    {
        if (!$this->checkPermission('shipments', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $shipment = $this->getShipmentOrFail((int)$id);
        $lines = $this->getShipmentLines((int)$id);

        $lineRows = '';
        foreach ($lines as $l) {
            $lineRows .= '<tr style="height:24px;"><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td>'.htmlspecialchars($l['pack_name']??'').'</td><td>'.htmlspecialchars($l['lot_numbers']??'').'</td><td style="text-align:right;">'.number_format((float)$l['quantity_shipped'],4).'</td></tr>';
            if ($l['packing_slip_note']) {
                $lineRows .= '<tr><td></td><td colspan="4" style="font-style:italic;color:#555;padding:2px 8px;">Note: '.htmlspecialchars($l['packing_slip_note']).'</td></tr>';
            }
        }

        $html = '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:20px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;}th{background:#f0f0f0;}</style></head><body>'
            .'<h1>PACKING SLIP</h1>'
            .'<p><strong>Shipment:</strong> '.htmlspecialchars($shipment['shipment_number']).' | <strong>Date:</strong> '.date('M j, Y',strtotime($shipment['ship_date'])).'</p>'
            .'<p><strong>Ship To:</strong> '.htmlspecialchars($shipment['ship_to_name']??'').' '.htmlspecialchars(implode(', ',array_filter([$shipment['st_street']??'',$shipment['st_city']??'',$shipment['st_state']??'',$shipment['st_zip']??'']))).'</p>'
            .'<table><thead><tr><th>Item</th><th>Description</th><th>Pack</th><th>Lot</th><th style="text-align:right;">Qty</th></tr></thead><tbody>'.$lineRows.'</tbody></table>'
            .'<p style="margin-top:40px;">Received by: _________________________ Date: _____________</p>'
            .'</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter','portrait');
        $dompdf->render();
        $dompdf->stream("PS_{$shipment['shipment_number']}.pdf",['Attachment'=>false]);
        exit;
    }

    // ── BOL PDF ─────────────────────────────────────────────────────

    public function bol(string $id): void
    {
        if (!$this->checkPermission('shipments', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $shipment = $this->getShipmentOrFail((int)$id);
        $lines = $this->getShipmentLines((int)$id);

        $lineRows = '';
        foreach ($lines as $l) {
            $lineRows .= '<tr><td>'.htmlspecialchars($l['item_code']).'</td><td>'.htmlspecialchars($l['item_description']).'</td><td>'.htmlspecialchars($l['pack_name']??'').'</td><td>'.htmlspecialchars($l['lot_numbers']??'').'</td><td style="text-align:right;">'.number_format((float)$l['quantity_shipped'],4).'</td></tr>';
        }

        $html = '<!DOCTYPE html><html><head><style>body{font-family:Arial,sans-serif;font-size:12px;margin:30px;}h1{font-size:20px;}table{width:100%;border-collapse:collapse;margin:12px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;}th{background:#f0f0f0;}</style></head><body>'
            .'<h1>BILL OF LADING</h1>'
            .'<p><strong>Shipment:</strong> '.htmlspecialchars($shipment['shipment_number']).' | <strong>Date:</strong> '.date('M j, Y',strtotime($shipment['ship_date'])).'</p>'
            .'<p><strong>Carrier:</strong> '.htmlspecialchars($shipment['ship_via_name']??'').' | <strong>Tracking:</strong> '.htmlspecialchars($shipment['tracking_number']??'').'</p>'
            .'<p><strong>Consignee:</strong> '.htmlspecialchars($shipment['ship_to_name']??'').' '.htmlspecialchars(implode(', ',array_filter([$shipment['st_street']??'',$shipment['st_city']??'',$shipment['st_state']??'',$shipment['st_zip']??'']))).'</p>'
            .'<table><thead><tr><th>Item</th><th>Description</th><th>Pack</th><th>Lot</th><th style="text-align:right;">Qty</th></tr></thead><tbody>'.$lineRows.'</tbody></table>'
            .'</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter','portrait');
        $dompdf->render();
        $dompdf->stream("BOL_{$shipment['shipment_number']}.pdf",['Attachment'=>false]);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getShipmentOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT s.*, f.name as facility_name, sv.name as ship_via_name,
                   st.location_name as ship_to_name, st.street as st_street, st.city as st_city,
                   st.state as st_state, st.zip as st_zip, u.full_name as created_by_name
            FROM shipments s
            LEFT JOIN facilities f ON s.facility_id = f.id
            LEFT JOIN ship_via sv ON s.ship_via_id = sv.id
            LEFT JOIN ship_to_locations st ON s.ship_to_id = st.id
            LEFT JOIN users u ON s.created_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if (!$s) { http_response_code(404); echo 'Shipment not found.'; exit; }
        return $s;
    }

    private function getShipmentLines(int $shipmentId): array
    {
        $stmt = $this->db()->prepare("
            SELECT sl.*, i.item_code, i.description as item_description, pe.name as pack_name,
                   (SELECT GROUP_CONCAT(lot_number) FROM shipment_line_lots WHERE shipment_line_id = sl.id) as lot_numbers
            FROM shipment_lines sl
            JOIN sales_order_lines sol ON sl.so_line_id = sol.id
            JOIN items i ON sol.item_id = i.id
            LEFT JOIN item_pack_extensions pe ON sol.pack_extension_id = pe.id
            WHERE sl.shipment_id = ?
        ");
        $stmt->execute([$shipmentId]);
        return $stmt->fetchAll();
    }
}
