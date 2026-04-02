<?php

namespace App\Services;

class QbDesktopService
{
    private \PDO $db;

    public function __construct(\PDO $db) { $this->db = $db; }

    /**
     * Export invoices to IIF format.
     */
    public function exportInvoices(bool $includeAll = false): string
    {
        $where = $includeAll
            ? 'i.status != "VOID"'
            : 'i.status != "VOID" AND NOT EXISTS (SELECT 1 FROM qb_sync_log q WHERE q.record_type="invoice" AND q.record_id=i.id AND q.status="SUCCESS" AND q.qb_mode="DESKTOP")';

        $stmt = $this->db->query("
            SELECT i.*, c.company_name as customer_name,
                   c.billing_street, c.billing_city, c.billing_state, c.billing_zip,
                   pt.name as payment_terms_name
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            LEFT JOIN payment_terms pt ON i.payment_terms_id = pt.id
            WHERE {$where}
            ORDER BY i.invoice_date ASC
        ");
        $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lines = [];
        $lines[] = "!TRNS\tTRNSTYPE\tDATE\tACCNT\tNAME\tAMOUNT\tDOCNUM\tMEMO\tCLEAR\tTOPRINT\tADDR1\tADDR2\tADDR3\tDUEDATE\tTERMS";
        $lines[] = "!SPL\tTRNSTYPE\tDATE\tACCNT\tNAME\tAMOUNT\tDOCNUM\tMEMO\tCLEAR\tQNTY\tPRICE\tINVITEM";
        $lines[] = "!ENDTRNS";

        foreach ($invoices as $inv) {
            $arAccount = $this->getSetting('qb_ar_account', 'Accounts Receivable');
            $date = date('m/d/Y', strtotime($inv['invoice_date']));
            $dueDate = date('m/d/Y', strtotime($inv['due_date']));
            $amount = number_format((float)$inv['total_due'], 2, '.', '');

            $lines[] = implode("\t", [
                'TRNS', 'INVOICE', $date, $arAccount,
                $this->iifSafe($inv['customer_name']), $amount, $inv['invoice_number'],
                '', 'N', 'N',
                $this->iifSafe($inv['billing_street'] ?? ''),
                $this->iifSafe(($inv['billing_city'] ?? '') . ', ' . ($inv['billing_state'] ?? '') . ' ' . ($inv['billing_zip'] ?? '')),
                '', $dueDate, $this->iifSafe($inv['payment_terms_name'] ?? '')
            ]);

            // Invoice lines from shipment
            $lineStmt = $this->db->prepare('
                SELECT sl.quantity_shipped, sl.unit_price,
                       it.item_code, it.description, it.gl_group
                FROM shipment_lines sl
                JOIN sales_order_lines sol ON sl.so_line_id = sol.id
                JOIN items it ON sol.item_id = it.id
                WHERE sl.shipment_id = ?
            ');
            $lineStmt->execute([$inv['shipment_id']]);

            foreach ($lineStmt->fetchAll(\PDO::FETCH_ASSOC) as $il) {
                $incomeAccount = $this->getGlAccount($il['gl_group'] ?? '', 'income');
                $lineAmount = number_format(-((float)$il['quantity_shipped'] * (float)$il['unit_price']), 2, '.', '');
                $lines[] = implode("\t", [
                    'SPL', 'INVOICE', $date, $incomeAccount,
                    $this->iifSafe($inv['customer_name']), $lineAmount, $inv['invoice_number'],
                    $this->iifSafe($il['description']), 'N', $il['quantity_shipped'],
                    number_format((float)$il['unit_price'], 2, '.', ''),
                    $this->iifSafe($il['item_code'])
                ]);
            }

            if ((float)($inv['freight_amount'] ?? 0) > 0) {
                $lines[] = implode("\t", [
                    'SPL', 'INVOICE', $date, 'Freight Income',
                    $this->iifSafe($inv['customer_name']), number_format(-(float)$inv['freight_amount'], 2, '.', ''),
                    $inv['invoice_number'], 'Freight', 'N', '', '', 'FREIGHT'
                ]);
            }

            $lines[] = 'ENDTRNS';

            if (!$includeAll) {
                $this->db->prepare('INSERT INTO qb_sync_log (sync_type, record_type, record_id, qb_mode, status, synced_at) VALUES ("invoice","invoice",?,"DESKTOP","SUCCESS",NOW())')
                    ->execute([$inv['id']]);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Export vendor bills (from PO receipts) to IIF.
     */
    public function exportBills(bool $includeAll = false): string
    {
        $where = $includeAll
            ? '1=1'
            : 'NOT EXISTS (SELECT 1 FROM qb_sync_log q WHERE q.record_type="po_receipt" AND q.record_id=pr.id AND q.status="SUCCESS" AND q.qb_mode="DESKTOP")';

        $stmt = $this->db->query("
            SELECT pr.*, s.company_name as vendor_name, po.po_number,
                   pt.name as payment_terms_name
            FROM po_receipts pr
            JOIN purchase_orders po ON pr.po_id = po.id
            JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN payment_terms pt ON s.payment_terms_id = pt.id
            WHERE {$where}
        ");
        $receipts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lines = [];
        $lines[] = "!TRNS\tTRNSTYPE\tDATE\tACCNT\tNAME\tAMOUNT\tDOCNUM\tMEMO";
        $lines[] = "!SPL\tTRNSTYPE\tDATE\tACCNT\tNAME\tAMOUNT\tDOCNUM\tMEMO\tQNTY\tPRICE\tINVITEM";
        $lines[] = "!ENDTRNS";

        foreach ($receipts as $receipt) {
            $apAccount = $this->getSetting('qb_ap_account', 'Accounts Payable');
            $date = date('m/d/Y', strtotime($receipt['receipt_date']));

            $totalStmt = $this->db->prepare('SELECT SUM(received_quantity * unit_cost) FROM po_receipt_lines WHERE receipt_id = ?');
            $totalStmt->execute([$receipt['id']]);
            $billTotal = (float)$totalStmt->fetchColumn();

            $lines[] = implode("\t", [
                'TRNS', 'BILL', $date, $apAccount,
                $this->iifSafe($receipt['vendor_name']), number_format(-$billTotal, 2, '.', ''),
                $this->iifSafe($receipt['supplier_invoice_number'] ?? ''), "PO: {$receipt['po_number']}"
            ]);

            $lineStmt = $this->db->prepare('
                SELECT prl.received_quantity, prl.unit_cost,
                       it.item_code, it.description, it.gl_group
                FROM po_receipt_lines prl
                JOIN purchase_order_lines pol ON prl.po_line_id = pol.id
                JOIN items it ON pol.item_id = it.id
                WHERE prl.receipt_id = ?
            ');
            $lineStmt->execute([$receipt['id']]);

            foreach ($lineStmt->fetchAll(\PDO::FETCH_ASSOC) as $bl) {
                $inventoryAccount = $this->getGlAccount($bl['gl_group'] ?? '', 'inventory');
                $lineAmount = number_format((float)$bl['received_quantity'] * (float)$bl['unit_cost'], 2, '.', '');
                $lines[] = implode("\t", [
                    'SPL', 'BILL', $date, $inventoryAccount,
                    $this->iifSafe($receipt['vendor_name']), $lineAmount,
                    $this->iifSafe($receipt['supplier_invoice_number'] ?? ''), $this->iifSafe($bl['description']),
                    $bl['received_quantity'], number_format((float)$bl['unit_cost'], 2, '.', ''),
                    $this->iifSafe($bl['item_code'])
                ]);
            }

            $lines[] = 'ENDTRNS';

            if (!$includeAll) {
                $this->db->prepare('INSERT INTO qb_sync_log (sync_type, record_type, record_id, qb_mode, status, synced_at) VALUES ("bill","po_receipt",?,"DESKTOP","SUCCESS",NOW())')
                    ->execute([$receipt['id']]);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Export customers list to IIF.
     */
    public function exportCustomers(): string
    {
        $stmt = $this->db->query('SELECT c.*, pt.name as payment_terms_name FROM customers c LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.id WHERE c.active = 1 AND c.deleted_at IS NULL');
        $lines = ["!CUST\tNAME\tBALANCE\tBSTREET\tBCITY\tBSTATE\tBZIP\tPHONE1\tTERMS"];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $lines[] = implode("\t", [
                'CUST', $this->iifSafe($c['company_name']), '0',
                $this->iifSafe($c['billing_street'] ?? ''), $this->iifSafe($c['billing_city'] ?? ''),
                $this->iifSafe($c['billing_state'] ?? ''), $this->iifSafe($c['billing_zip'] ?? ''),
                $this->iifSafe($c['phone'] ?? ''), $this->iifSafe($c['payment_terms_name'] ?? '')
            ]);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Export vendors list to IIF.
     */
    public function exportVendors(): string
    {
        $stmt = $this->db->query('SELECT s.*, pt.name as payment_terms_name FROM suppliers s LEFT JOIN payment_terms pt ON s.payment_terms_id = pt.id WHERE s.active = 1 AND s.deleted_at IS NULL');
        $lines = ["!VEND\tNAME\tADDR1\tCITY\tSTATE\tZIP\tPHONE1\tTERMS"];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $v) {
            $lines[] = implode("\t", [
                'VEND', $this->iifSafe($v['company_name']),
                $this->iifSafe($v['street'] ?? ''), $this->iifSafe($v['city'] ?? ''),
                $this->iifSafe($v['state'] ?? ''), $this->iifSafe($v['zip'] ?? ''),
                $this->iifSafe($v['phone'] ?? ''), $this->iifSafe($v['payment_terms_name'] ?? '')
            ]);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Export items list to IIF.
     */
    public function exportItems(): string
    {
        $stmt = $this->db->query('SELECT i.*, u.abbreviation as uom FROM items i JOIN uom u ON i.uom_id = u.id WHERE i.active = 1 AND i.deleted_at IS NULL');
        $lines = ["!INVITEM\tNAME\tINVITEMTYPE\tDESC\tACCNT\tASSETACCNT\tCOGSACCNT\tPRICE\tCOST\tQUANTITY\tUOM"];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            $incomeAcct = $this->getGlAccount($item['gl_group'] ?? '', 'income');
            $inventoryAcct = $this->getGlAccount($item['gl_group'] ?? '', 'inventory');
            $cogsAcct = $this->getGlAccount($item['gl_group'] ?? '', 'cogs');

            $lines[] = implode("\t", [
                'INVITEM', $this->iifSafe($item['item_code']), 'INVENTORY',
                $this->iifSafe($item['description']),
                $incomeAcct, $inventoryAcct, $cogsAcct,
                number_format((float)($item['sale_price'] ?? 0), 2, '.', ''),
                number_format((float)($item['unit_cost'] ?? 0), 2, '.', ''),
                '0', $this->iifSafe($item['uom'])
            ]);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Export inventory valuation journal entry to IIF.
     */
    public function exportInventoryValuation(): string
    {
        $date = date('m/d/Y');
        $dateKey = date('Y-m-d');

        $stmt = $this->db->query('
            SELECT i.gl_group,
                   SUM(fl.remaining_quantity * fl.unit_cost) as total_value
            FROM fifo_lots fl
            JOIN items i ON fl.item_id = i.id
            WHERE fl.status = "AVAILABLE" AND fl.remaining_quantity > 0
            GROUP BY i.gl_group
        ');
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lines = [];
        $lines[] = "!TRNS\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tMEMO";
        $lines[] = "!SPL\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tMEMO";
        $lines[] = "!ENDTRNS";

        $total = array_sum(array_column($groups, 'total_value'));
        $defaultInvAcct = $this->getSetting('qb_default_inventory_account', 'Inventory Asset');

        $lines[] = implode("\t", ['TRNS', 'GENERAL JOURNAL', $date,
            $defaultInvAcct, number_format($total, 2, '.', ''),
            'Inventory Valuation ' . $dateKey]);

        foreach ($groups as $group) {
            $inventoryAcct = $this->getGlAccount($group['gl_group'] ?? '', 'inventory');
            $lines[] = implode("\t", ['SPL', 'GENERAL JOURNAL', $date,
                $inventoryAcct, number_format(-(float)$group['total_value'], 2, '.', ''),
                ($group['gl_group'] ?? 'Uncategorized') . ' inventory value']);
        }
        $lines[] = 'ENDTRNS';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Count unsynced records by type.
     */
    public function getUnsyncedCounts(): array
    {
        $invoices = (int)$this->db->query('SELECT COUNT(*) FROM invoices i WHERE i.status != "VOID" AND NOT EXISTS (SELECT 1 FROM qb_sync_log q WHERE q.record_type="invoice" AND q.record_id=i.id AND q.status="SUCCESS" AND q.qb_mode="DESKTOP")')->fetchColumn();
        $bills = (int)$this->db->query('SELECT COUNT(*) FROM po_receipts pr WHERE NOT EXISTS (SELECT 1 FROM qb_sync_log q WHERE q.record_type="po_receipt" AND q.record_id=pr.id AND q.status="SUCCESS" AND q.qb_mode="DESKTOP")')->fetchColumn();
        $customers = (int)$this->db->query('SELECT COUNT(*) FROM customers WHERE active = 1 AND deleted_at IS NULL')->fetchColumn();
        $vendors = (int)$this->db->query('SELECT COUNT(*) FROM suppliers WHERE active = 1 AND deleted_at IS NULL')->fetchColumn();
        $items = (int)$this->db->query('SELECT COUNT(*) FROM items WHERE active = 1 AND deleted_at IS NULL')->fetchColumn();

        return compact('invoices', 'bills', 'customers', 'vendors', 'items');
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM qb_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: $default);
    }

    private function getGlAccount(string $glGroup, string $type): string
    {
        $mappingType = match ($type) {
            'income' => 'gl_group_income',
            'cogs' => 'gl_group_cogs',
            'inventory' => 'gl_group_inventory',
            default => 'gl_group_income',
        };
        $stmt = $this->db->prepare('SELECT qb_account_name FROM qb_account_mappings WHERE mapping_type = ? AND mapping_key = ? AND active = 1');
        $stmt->execute([$mappingType, $glGroup]);
        $account = $stmt->fetchColumn();
        if ($account) return $account;

        return match ($type) {
            'income' => $this->getSetting('qb_default_income_account', 'Sales'),
            'cogs' => $this->getSetting('qb_default_cogs_account', 'Cost of Goods Sold'),
            'inventory' => $this->getSetting('qb_default_inventory_account', 'Inventory Asset'),
            default => 'Uncategorized',
        };
    }

    /**
     * Sanitize value for IIF (strip tabs/newlines).
     */
    private function iifSafe(string $value): string
    {
        return str_replace(["\t", "\n", "\r"], [' ', ' ', ''], $value);
    }
}
