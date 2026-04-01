<?php

namespace App\Services;

/**
 * Handles bulk data exports to CSV.
 */
class ExportService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Export entity data as CSV string.
     */
    public function export(string $entity): string
    {
        return match ($entity) {
            'items'           => $this->exportItems(),
            'customers'       => $this->exportCustomers(),
            'suppliers'       => $this->exportSuppliers(),
            'inventory'       => $this->exportInventory(),
            'open-sales'      => $this->exportOpenSalesOrders(),
            'open-purchases'  => $this->exportOpenPurchaseOrders(),
            default           => '',
        };
    }

    public function getFilename(string $entity): string
    {
        $date = date('Y-m-d');
        return match ($entity) {
            'items'          => "items_export_{$date}.csv",
            'customers'      => "customers_export_{$date}.csv",
            'suppliers'      => "suppliers_export_{$date}.csv",
            'inventory'      => "inventory_export_{$date}.csv",
            'open-sales'     => "open_sales_orders_{$date}.csv",
            'open-purchases' => "open_purchase_orders_{$date}.csv",
            default          => "export_{$date}.csv",
        };
    }

    private function toCsv(array $headers, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    private function exportItems(): string
    {
        $rows = $this->db->query("
            SELECT i.item_code, i.description, i.item_type, i.gl_group,
                   u.abbreviation as uom_abbreviation, i.unit_cost, i.sale_price,
                   i.reorder_min, i.reorder_max, i.shelf_life_days,
                   i.requires_inspection, i.sds_on_file, i.active, i.notes
            FROM items i
            LEFT JOIN uom u ON i.uom_id = u.id
            WHERE i.deleted_at IS NULL
            ORDER BY i.item_code
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['item_code', 'description', 'item_type', 'gl_group', 'uom_abbreviation', 'unit_cost', 'sale_price', 'reorder_min', 'reorder_max', 'shelf_life_days', 'requires_inspection', 'sds_on_file', 'active', 'notes'],
            $rows
        );
    }

    private function exportCustomers(): string
    {
        $rows = $this->db->query("
            SELECT c.customer_code, c.company_name, c.billing_street, c.billing_city,
                   c.billing_state, c.billing_zip, c.billing_country, c.phone, c.fax,
                   pt.name as payment_terms, c.credit_limit, c.tax_exempt, c.active, c.notes
            FROM customers c
            LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.id
            WHERE c.deleted_at IS NULL
            ORDER BY c.customer_code
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['customer_code', 'company_name', 'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'phone', 'fax', 'payment_terms', 'credit_limit', 'tax_exempt', 'active', 'notes'],
            $rows
        );
    }

    private function exportSuppliers(): string
    {
        $rows = $this->db->query("
            SELECT s.supplier_code, s.company_name, s.street, s.city, s.state,
                   s.zip, s.country, s.phone, s.fax,
                   pt.name as payment_terms, s.active, s.notes
            FROM suppliers s
            LEFT JOIN payment_terms pt ON s.payment_terms_id = pt.id
            WHERE s.deleted_at IS NULL
            ORDER BY s.supplier_code
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['supplier_code', 'company_name', 'street', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'payment_terms', 'active', 'notes'],
            $rows
        );
    }

    private function exportInventory(): string
    {
        $rows = $this->db->query("
            SELECT i.item_code, i.description, f.code as facility_code, f.name as facility_name,
                   fl.lot_number, fl.remaining_quantity, u.abbreviation as uom,
                   fl.unit_cost, fl.expiration_date, fl.status
            FROM fifo_lots fl
            JOIN items i ON fl.item_id = i.id
            JOIN facilities f ON fl.facility_id = f.id
            LEFT JOIN uom u ON i.uom_id = u.id
            WHERE fl.remaining_quantity > 0
            ORDER BY i.item_code, f.code
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['item_code', 'description', 'facility_code', 'facility_name', 'lot_number', 'remaining_quantity', 'uom', 'unit_cost', 'expiration_date', 'status'],
            $rows
        );
    }

    private function exportOpenSalesOrders(): string
    {
        $rows = $this->db->query("
            SELECT so.so_number, c.customer_code, c.company_name, so.order_date,
                   so.promised_ship_date, so.status, i.item_code, sol.ordered_quantity,
                   sol.shipped_quantity, sol.unit_price
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.id
            JOIN sales_order_lines sol ON sol.so_id = so.id
            JOIN items i ON sol.item_id = i.id
            WHERE so.status NOT IN ('SHIPPED','CANCELLED')
              AND so.deleted_at IS NULL
            ORDER BY so.so_number
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['so_number', 'customer_code', 'company_name', 'order_date', 'promised_ship_date', 'status', 'item_code', 'ordered_quantity', 'shipped_quantity', 'unit_price'],
            $rows
        );
    }

    private function exportOpenPurchaseOrders(): string
    {
        $rows = $this->db->query("
            SELECT po.po_number, s.supplier_code, s.company_name, po.order_date,
                   po.expected_delivery_date, po.status, i.item_code,
                   pol.ordered_quantity, pol.received_quantity, pol.unit_cost
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN purchase_order_lines pol ON pol.po_id = po.id
            JOIN items i ON pol.item_id = i.id
            WHERE po.status NOT IN ('RECEIVED','CANCELLED')
              AND po.deleted_at IS NULL
            ORDER BY po.po_number
        ")->fetchAll(\PDO::FETCH_NUM);

        return $this->toCsv(
            ['po_number', 'supplier_code', 'company_name', 'order_date', 'expected_delivery_date', 'status', 'item_code', 'ordered_quantity', 'received_quantity', 'unit_cost'],
            $rows
        );
    }
}
