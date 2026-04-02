<?php

namespace PrecisionInk\Controllers;

class MrpController extends BaseController
{
    // ── MRP Summary ─────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($_GET['facility_id'] ?? $active['id'] ?? 0);

        $results = $this->runMRP($facilityId);

        $this->renderView('mrp/index', [
            'results' => $results, 'facilities' => $facilities, 'facilityId' => $facilityId,
        ]);
    }

    public function run(): void
    {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $this->redirect("/mrp?facility_id={$facilityId}");
    }

    public function createBatchFromSuggestion(string $itemId): void
    {
        $qty = (float)($_GET['qty'] ?? $_POST['qty'] ?? 0);
        $this->redirect("/batches/create?item_id={$itemId}&target_quantity={$qty}");
    }

    public function createPoFromSuggestion(string $itemId): void
    {
        $qty = (float)($_GET['qty'] ?? $_POST['qty'] ?? 0);
        $supplierId = (int)($_GET['supplier_id'] ?? $_POST['supplier_id'] ?? 0);
        $this->redirect("/purchase-orders/create?item_id={$itemId}&quantity={$qty}&supplier_id={$supplierId}");
    }

    public function export(): void
    {
        if (!$this->checkPermission('inventory', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $facilityId = (int)($_GET['facility_id'] ?? 0);
        $results = $this->runMRP($facilityId);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="mrp_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Item Code', 'Description', 'Type', 'On Hand', 'Available', 'SO Demand', 'PO Supply', 'Batch Supply', 'Net Position', 'Action', 'Suggested Qty']);
        foreach ($results as $r) {
            fputcsv($out, [$r['item_code'], $r['description'], $r['item_type'], $r['on_hand'], $r['available'], $r['so_demand'], $r['po_supply'], $r['batch_supply'], $r['net_position'], $r['action'], $r['suggested_order_qty']]);
        }
        fclose($out);
        exit;
    }

    // ── Production Calendar ─────────────────────────────────────────

    public function calendar(): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $facilities = $this->facilityService->getUserFacilities($userId);
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($_GET['facility_id'] ?? $active['id'] ?? 0);

        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDow = (int)date('w', mktime(0, 0, 0, $month, 1, $year));

        // Get overrides
        $overStmt = $this->db()->prepare("SELECT calendar_date, is_workday, override_reason FROM production_calendar WHERE facility_id = ? AND calendar_date BETWEEN ? AND ?");
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $overStmt->execute([$facilityId, $startDate, $endDate]);
        $overrides = [];
        foreach ($overStmt->fetchAll() as $ov) $overrides[$ov['calendar_date']] = $ov;

        // Get batch counts per day
        $batchStmt = $this->db()->prepare("SELECT scheduled_date, COUNT(*) as cnt FROM batch_tickets WHERE facility_id = ? AND scheduled_date BETWEEN ? AND ? AND status IN ('OPEN','IN_PROGRESS') GROUP BY scheduled_date");
        $batchStmt->execute([$facilityId, $startDate, $endDate]);
        $batchCounts = [];
        foreach ($batchStmt->fetchAll() as $bc) $batchCounts[$bc['scheduled_date']] = (int)$bc['cnt'];

        $this->renderView('mrp/calendar', [
            'facilities' => $facilities, 'facilityId' => $facilityId,
            'month' => $month, 'year' => $year, 'daysInMonth' => $daysInMonth, 'firstDow' => $firstDow,
            'overrides' => $overrides, 'batchCounts' => $batchCounts,
        ]);
    }

    public function toggleCalendarDay(): void
    {
        if (!$this->checkPermission('batch_tickets', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $date = $_POST['calendar_date'] ?? '';
        $isWorkday = (int)($_POST['is_workday'] ?? 1);
        $reason = trim($_POST['override_reason'] ?? '');

        if ($facilityId && $date) {
            $this->db()->prepare("INSERT INTO production_calendar (facility_id, calendar_date, is_workday, override_reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_workday = VALUES(is_workday), override_reason = VALUES(override_reason)")
                ->execute([$facilityId, $date, $isWorkday, $reason ?: null]);
        }

        $month = date('n', strtotime($date));
        $year = date('Y', strtotime($date));
        $this->redirect("/production/calendar?facility_id={$facilityId}&month={$month}&year={$year}");
    }

    // ── Production Schedule ─────────────────────────────────────────

    public function schedule(): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $userId = $this->currentUserId();
        $active = $this->facilityService->getActiveFacility($userId);
        $facilityId = (int)($active['id'] ?? 0);

        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+28 days'));

        // Get batches for next 4 weeks
        $batchStmt = $this->db()->prepare("
            SELECT bt.*, i.item_code, i.description as item_description,
                   GROUP_CONCAT(e.name) as equipment_names
            FROM batch_tickets bt
            JOIN items i ON bt.item_id = i.id
            LEFT JOIN batch_equipment be ON be.batch_id = bt.id
            LEFT JOIN equipment e ON be.equipment_id = e.id
            WHERE bt.facility_id = ? AND bt.scheduled_date BETWEEN ? AND ?
              AND bt.status IN ('OPEN','IN_PROGRESS') AND bt.deleted_at IS NULL
            GROUP BY bt.id
            ORDER BY bt.scheduled_date, bt.priority DESC
        ");
        $batchStmt->execute([$facilityId, $startDate, $endDate]);
        $batches = $batchStmt->fetchAll();

        // Group by date
        $byDate = [];
        foreach ($batches as $b) {
            $byDate[$b['scheduled_date']][] = $b;
        }

        // Generate date range
        $dates = [];
        $d = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        while ($d <= $end) {
            $dates[] = $d->format('Y-m-d');
            $d->modify('+1 day');
        }

        $this->renderView('mrp/schedule', [
            'dates' => $dates, 'byDate' => $byDate, 'facilityId' => $facilityId,
        ]);
    }

    // ── Batch Packet PDF ────────────────────────────────────────────

    public function batchPacket(string $id): void
    {
        if (!$this->checkPermission('batch_tickets', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $batch = $this->db()->prepare("SELECT bt.*, i.item_code, i.description as item_description, rv.version_name, rv.version_number, f.name as facility_name, u.full_name as assigned_name FROM batch_tickets bt JOIN items i ON bt.item_id = i.id LEFT JOIN recipe_versions rv ON bt.recipe_version_id = rv.id LEFT JOIN facilities f ON bt.facility_id = f.id LEFT JOIN users u ON bt.assigned_to = u.id WHERE bt.id = ?");
        $batch->execute([(int)$id]);
        $batch = $batch->fetch();
        if (!$batch) { http_response_code(404); echo 'Batch not found.'; exit; }

        // Lines
        $lines = $this->db()->prepare("SELECT bl.*, i.item_code, i.description as item_description, um.abbreviation as uom_abbr FROM batch_ticket_lines bl JOIN items i ON bl.item_id = i.id LEFT JOIN uom um ON bl.uom_id = um.id WHERE bl.batch_id = ? ORDER BY bl.sequence");
        $lines->execute([(int)$id]);
        $lines = $lines->fetchAll();

        // Instructions
        $instrStmt = $this->db()->prepare("SELECT instruction_text, sequence FROM recipe_steps WHERE recipe_version_id = ? AND step_type = 'INSTRUCTION' ORDER BY sequence");
        $instrStmt->execute([$batch['recipe_version_id']]);
        $instructions = $instrStmt->fetchAll();

        // Packs
        $packStmt = $this->db()->prepare("SELECT bp.target_quantity, pe.name as pack_name FROM batch_ticket_packs bp JOIN item_pack_extensions pe ON bp.pack_extension_id = pe.id WHERE bp.batch_id = ?");
        $packStmt->execute([(int)$id]);
        $packs = $packStmt->fetchAll();

        // Equipment
        $eqStmt = $this->db()->prepare("SELECT e.name, e.next_maintenance_due FROM batch_equipment be JOIN equipment e ON be.equipment_id = e.id WHERE be.batch_id = ?");
        $eqStmt->execute([(int)$id]);
        $equipment = $eqStmt->fetchAll();

        // QC Spec tests
        $tests = [];
        if ($batch['qc_spec_id']) {
            $testStmt = $this->db()->prepare("SELECT * FROM qc_spec_tests WHERE spec_id = ? ORDER BY display_sequence, id");
            $testStmt->execute([$batch['qc_spec_id']]);
            $tests = $testStmt->fetchAll();
        }

        // Available lots per ingredient
        $lotInfo = [];
        foreach ($lines as $l) {
            $lotStmt = $this->db()->prepare("SELECT lot_number, remaining_quantity, COALESCE(ifl.location,'') as location FROM fifo_lots fl LEFT JOIN item_facility_locations ifl ON ifl.item_id = fl.item_id AND ifl.facility_id = fl.facility_id WHERE fl.item_id = ? AND fl.facility_id = ? AND fl.status = 'AVAILABLE' AND fl.remaining_quantity > 0 ORDER BY fl.created_at LIMIT 3");
            $lotStmt->execute([$l['item_id'], $batch['facility_id']]);
            $lotInfo[$l['item_id']] = $lotStmt->fetchAll();
        }

        $html = $this->buildBatchPacketHtml($batch, $lines, $instructions, $packs, $equipment, $tests, $lotInfo);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("PACKET_{$batch['batch_number']}.pdf", ['Attachment' => false]);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function runMRP(int $facilityId): array
    {
        $stmt = $this->db()->prepare('
            SELECT DISTINCT i.id, i.item_code, i.description, i.item_type, i.reorder_min, i.reorder_max, i.unit_cost
            FROM items i WHERE i.active = 1 AND i.deleted_at IS NULL AND (
                i.reorder_min IS NOT NULL
                OR EXISTS (SELECT 1 FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id = so.id WHERE sol.item_id = i.id AND so.status IN ("CONFIRMED","PARTIAL") AND (sol.ordered_quantity - sol.shipped_quantity) > 0 AND so.facility_id = ?)
            ) ORDER BY i.item_code
        ');
        $stmt->execute([$facilityId]);
        $items = $stmt->fetchAll();

        $results = [];
        foreach ($items as $item) {
            $onHand = $this->fifoService->getOnHand((int)$item['id'], $facilityId);
            $available = $this->fifoService->getAvailable((int)$item['id'], $facilityId);

            $s = $this->db()->prepare('SELECT COALESCE(SUM(sol.ordered_quantity - sol.shipped_quantity), 0) FROM sales_order_lines sol JOIN sales_orders so ON sol.so_id = so.id WHERE sol.item_id = ? AND so.status IN ("CONFIRMED","PARTIAL") AND (sol.ordered_quantity - sol.shipped_quantity) > 0 AND so.facility_id = ?');
            $s->execute([$item['id'], $facilityId]);
            $soDemand = (float)$s->fetchColumn();

            $s = $this->db()->prepare('SELECT COALESCE(SUM(pol.ordered_quantity - pol.received_quantity), 0) FROM purchase_order_lines pol JOIN purchase_orders po ON pol.po_id = po.id WHERE pol.item_id = ? AND po.facility_id = ? AND po.status IN ("SENT") AND pol.line_status NOT IN ("CANCELLED","RECEIVED")');
            $s->execute([$item['id'], $facilityId]);
            $poSupply = (float)$s->fetchColumn();

            $s = $this->db()->prepare('SELECT COALESCE(SUM(target_quantity), 0) FROM batch_tickets WHERE item_id = ? AND facility_id = ? AND status IN ("OPEN","IN_PROGRESS")');
            $s->execute([$item['id'], $facilityId]);
            $batchSupply = (float)$s->fetchColumn();

            $netPosition = $available + $poSupply + $batchSupply - $soDemand;
            $reorderMin = (float)($item['reorder_min'] ?? 0);
            $reorderMax = (float)($item['reorder_max'] ?? 0);

            $suggestedQty = 0;
            $action = 'OK';
            if ($netPosition < 0) {
                $action = 'ORDER';
                $suggestedQty = abs($netPosition) + ($reorderMax > 0 ? $reorderMax - $reorderMin : 0);
            } elseif ($reorderMin > 0 && $onHand < $reorderMin) {
                $action = 'REORDER';
                $suggestedQty = $reorderMax > 0 ? $reorderMax - $onHand : $reorderMin - $onHand;
            }

            $otherFac = [];
            $allFac = $this->fifoService->getAvailableAllFacilities((int)$item['id']);
            foreach ($allFac as $fId => $fData) {
                if ($fId != $facilityId && $fData['available'] > 0) $otherFac[] = "{$fData['available']} at {$fData['facility_name']}";
            }

            $prefSupp = $this->db()->prepare('SELECT s.id as supplier_id, s.supplier_code, s.company_name, avl.approved_unit_cost, avl.lead_time_days FROM approved_vendor_list avl JOIN suppliers s ON avl.supplier_id = s.id WHERE avl.item_id = ? AND avl.is_preferred = 1 AND avl.active = 1 LIMIT 1');
            $prefSupp->execute([$item['id']]);
            $supplier = $prefSupp->fetch() ?: null;

            $results[] = array_merge($item, [
                'on_hand' => $onHand, 'available' => $available, 'so_demand' => $soDemand,
                'po_supply' => $poSupply, 'batch_supply' => $batchSupply,
                'net_position' => $netPosition, 'action' => $action,
                'suggested_order_qty' => $suggestedQty, 'other_facility' => $otherFac,
                'preferred_supplier' => $supplier,
            ]);
        }

        usort($results, fn($a, $b) => ['ORDER' => 0, 'REORDER' => 1, 'OK' => 2][$a['action']] <=> ['ORDER' => 0, 'REORDER' => 1, 'OK' => 2][$b['action']]);
        return $results;
    }

    private function buildBatchPacketHtml(array $batch, array $lines, array $instructions, array $packs, array $equipment, array $tests, array $lotInfo): string
    {
        $css = 'body{font-family:Arial,sans-serif;font-size:12px;margin:25px;}h1{font-size:22px;margin-bottom:4px;}h2{font-size:16px;margin-top:20px;}table{width:100%;border-collapse:collapse;margin:8px 0;}th,td{border:1px solid #ccc;padding:5px 8px;text-align:left;}th{background:#f0f0f0;font-size:11px;}tr{page-break-inside:avoid;}.rush{color:#dc2626;font-weight:bold;font-size:16px;text-align:center;padding:8px;border:2px solid #dc2626;margin-bottom:12px;}.footer{position:fixed;bottom:10px;left:25px;right:25px;font-size:10px;color:#999;border-top:1px solid #ccc;padding-top:4px;text-align:center;}.blank{border-bottom:1px solid #999;min-width:80px;display:inline-block;height:20px;}';

        $html = '<!DOCTYPE html><html><head><style>' . $css . '</style></head><body>';

        // Page 1: Header
        $html .= '<h1>BATCH PACKET</h1>';
        $html .= '<div style="font-size:24px;font-weight:bold;margin-bottom:8px;">' . htmlspecialchars($batch['batch_number']) . '</div>';
        if ($batch['priority'] === 'RUSH') $html .= '<div class="rush">*** RUSH ***</div>';
        $html .= '<table style="border:none;"><tr><td style="border:none;width:50%;"><strong>Item:</strong> ' . htmlspecialchars($batch['item_code'] . ' — ' . $batch['item_description']) . '<br><strong>Recipe:</strong> v' . (int)$batch['version_number'] . ' ' . htmlspecialchars($batch['version_name'] ?? '') . '<br><strong>Target:</strong> ' . number_format((float)$batch['target_quantity'], 4) . '</td><td style="border:none;width:50%;"><strong>Facility:</strong> ' . htmlspecialchars($batch['facility_name']) . '<br><strong>Scheduled:</strong> ' . date('M j, Y', strtotime($batch['scheduled_date'])) . '<br><strong>Assigned:</strong> ' . htmlspecialchars($batch['assigned_name'] ?? '—') . '</td></tr></table>';

        // Equipment
        if (!empty($equipment)) {
            $html .= '<p><strong>Equipment:</strong> ';
            foreach ($equipment as $eq) {
                $overdue = $eq['next_maintenance_due'] && $eq['next_maintenance_due'] < date('Y-m-d');
                $html .= htmlspecialchars($eq['name']) . ($overdue ? ' <span style="color:red;">(MAINT OVERDUE)</span>' : '') . ', ';
            }
            $html = rtrim($html, ', ') . '</p>';
        }

        // Packs
        if (!empty($packs)) {
            $html .= '<p><strong>Target Packs:</strong> ';
            foreach ($packs as $p) $html .= htmlspecialchars($p['pack_name']) . ': ' . number_format((float)$p['target_quantity'], 4) . ', ';
            $html = rtrim($html, ', ') . '</p>';
        }

        if ($batch['internal_notes']) $html .= '<div style="padding:8px;background:#fef9c3;border:1px solid #fde68a;border-radius:4px;margin-top:8px;"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($batch['internal_notes'])) . '</div>';

        // Page 2: Instructions
        if (!empty($instructions)) {
            $html .= '<div style="page-break-before:always;"></div><h2>INSTRUCTION STEPS</h2>';
            foreach ($instructions as $i => $ins) {
                $html .= '<div style="margin-bottom:16px;padding:8px;border-left:4px solid #2563eb;"><strong>Step ' . ($i + 1) . ':</strong><br>' . nl2br(htmlspecialchars($ins['instruction_text'])) . '</div>';
            }
        }

        // Page 3: Ingredient Checklist
        $html .= '<div style="page-break-before:always;"></div><h2>INGREDIENT CHECKLIST</h2>';
        $html .= '<table><thead><tr><th>#</th><th>Item Code</th><th>Description</th><th style="text-align:right;">Theoretical</th><th>UOM</th><th>Lot #</th><th>Actual Qty</th><th>Initials</th></tr></thead><tbody>';
        $n = 1;
        foreach ($lines as $l) {
            $html .= '<tr style="height:30px;"><td>' . $n++ . '</td><td style="font-weight:bold;">' . htmlspecialchars($l['item_code']) . '</td><td>' . htmlspecialchars($l['item_description']) . '</td><td style="text-align:right;">' . number_format((float)$l['theoretical_quantity'], 4) . '</td><td>' . htmlspecialchars($l['uom_abbr'] ?? '') . '</td><td style="width:100px;"></td><td style="width:80px;"></td><td style="width:60px;"></td></tr>';
            $lots = $lotInfo[$l['item_id']] ?? [];
            if (!empty($lots)) {
                $lotText = implode(', ', array_map(fn($lt) => $lt['lot_number'] . ' (' . number_format((float)$lt['remaining_quantity'], 2) . ' @ ' . $lt['location'] . ')', $lots));
                $html .= '<tr><td></td><td colspan="7" style="font-size:10px;color:#6b7280;padding:2px 8px;">Available: ' . htmlspecialchars($lotText) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

        // Page 4: QC Sheet
        if (!empty($tests)) {
            $html .= '<div style="page-break-before:always;"></div><h2>QC ENTRY SHEET</h2>';
            $html .= '<table><thead><tr><th>Test</th><th>Type</th><th>Spec</th><th style="width:120px;">Result</th><th style="width:60px;">P/F</th></tr></thead><tbody>';
            foreach ($tests as $t) {
                $spec = $t['test_type'] === 'NUMERIC_RANGE' ? 'Min: ' . number_format((float)($t['min_value'] ?? 0), 2) . ' Max: ' . number_format((float)($t['max_value'] ?? 0), 2) . ' ' . htmlspecialchars($t['uom'] ?? '') : 'Pass/Fail';
                $html .= '<tr style="height:28px;"><td style="font-weight:bold;">' . htmlspecialchars($t['test_name']) . ($t['is_required'] ? ' *' : '') . '</td><td>' . ($t['test_type'] === 'PASS_FAIL' ? 'P/F' : 'Numeric') . '</td><td>' . $spec . '</td><td></td><td></td></tr>';
            }
            $html .= '</tbody></table>';
            $html .= '<p style="margin-top:24px;">QC Technician: _________________________ Date: _____________</p>';
        }

        // Page 5: Yield Entry
        $html .= '<div style="page-break-before:always;"></div><h2>YIELD ENTRY</h2>';
        $html .= '<table><thead><tr><th>Pack</th><th style="text-align:right;">Target</th><th style="width:120px;">Actual Qty</th><th style="width:100px;">Container Count</th></tr></thead><tbody>';
        foreach ($packs as $p) {
            $html .= '<tr style="height:28px;"><td style="font-weight:bold;">' . htmlspecialchars($p['pack_name']) . '</td><td style="text-align:right;">' . number_format((float)$p['target_quantity'], 4) . '</td><td></td><td></td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p style="margin-top:16px;font-size:14px;">Total Yield: _________________ Yield %: _________________</p>';

        $html .= '<div class="footer">Batch Packet — ' . htmlspecialchars($batch['batch_number']) . '</div>';
        $html .= '</body></html>';

        return $html;
    }
}
