<?php

namespace App\Services;

/**
 * Handles bulk data imports from CSV files.
 */
class ImportService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    // ── Public API ────────────────────────────────────────────────

    /**
     * Validate CSV rows without writing to database.
     * @return array [['row' => N, 'status' => 'valid'|'warning'|'error', 'message' => '...'], ...]
     */
    public function dryRun(string $entity, array $rows): array
    {
        $results = [];
        foreach ($rows as $i => $row) {
            $rowNum = $i + 1;
            $errors = $this->validateRow($entity, $row, $rowNum);
            if (empty($errors)) {
                $results[] = ['row' => $rowNum, 'status' => 'valid', 'message' => 'OK'];
            } else {
                foreach ($errors as $err) {
                    $results[] = ['row' => $rowNum, 'status' => $err['status'], 'message' => $err['message']];
                }
            }
        }
        return $results;
    }

    /**
     * Validate and import CSV rows.
     * @return array ['imported' => N, 'skipped' => N, 'errors' => [['row' => N, 'message' => '...']]]
     */
    public function commit(string $entity, array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 1;
            $rowErrors = $this->validateRow($entity, $row, $rowNum);
            $hasError = false;
            foreach ($rowErrors as $err) {
                if ($err['status'] === 'error') {
                    $hasError = true;
                    $errors[] = ['row' => $rowNum, 'message' => $err['message']];
                }
            }
            if ($hasError) {
                $skipped++;
                continue;
            }

            try {
                $this->importRow($entity, $row);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    // ── Validation ────────────────────────────────────────────────

    private function validateRow(string $entity, array $row, int $rowNum): array
    {
        return match ($entity) {
            'items'             => $this->validateItemRow($row),
            'customers'         => $this->validateCustomerRow($row),
            'suppliers'         => $this->validateSupplierRow($row),
            'opening-inventory' => $this->validateInventoryRow($row),
            'customer-pricing'  => $this->validatePricingRow($row),
            'approved-vendors'  => $this->validateVendorRow($row),
            default             => [['status' => 'error', 'message' => "Unknown entity: {$entity}"]],
        };
    }

    private function validateItemRow(array $row): array
    {
        $errors = [];
        $validTypes = ['RAW_MATERIAL', 'FINISHED_GOOD', 'INTERMEDIATE', 'RESALE', 'SERVICE'];

        $code = trim($row['item_code'] ?? '');
        if ($code === '') {
            $errors[] = ['status' => 'error', 'message' => 'item_code is required'];
        } elseif (str_contains($code, ' ')) {
            $errors[] = ['status' => 'error', 'message' => 'item_code must not contain spaces'];
        }

        $itemType = strtoupper(trim($row['item_type'] ?? ''));
        if ($itemType === '') {
            $errors[] = ['status' => 'error', 'message' => 'item_type is required'];
        } elseif (!in_array($itemType, $validTypes, true)) {
            $errors[] = ['status' => 'error', 'message' => "Unknown item_type: {$itemType}"];
        }

        $glGroup = strtoupper(trim($row['gl_group'] ?? ''));
        if ($glGroup === '') {
            $errors[] = ['status' => 'error', 'message' => 'gl_group is required'];
        } elseif (!in_array($glGroup, $validTypes, true)) {
            $errors[] = ['status' => 'error', 'message' => "Unknown gl_group: {$glGroup}"];
        }

        $uom = trim($row['uom_abbreviation'] ?? '');
        if ($uom !== '') {
            $stmt = $this->db->prepare('SELECT id FROM uom WHERE abbreviation = ? AND active = 1');
            $stmt->execute([$uom]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'warning', 'message' => "UOM '{$uom}' not found — will use default"];
            }
        } else {
            $errors[] = ['status' => 'warning', 'message' => 'uom_abbreviation blank — will use default'];
        }

        if (($row['unit_cost'] ?? '') !== '' && !is_numeric($row['unit_cost'])) {
            $errors[] = ['status' => 'error', 'message' => 'unit_cost must be numeric'];
        }
        if (($row['sale_price'] ?? '') !== '' && !is_numeric($row['sale_price'])) {
            $errors[] = ['status' => 'error', 'message' => 'sale_price must be numeric'];
        }

        $ri = trim($row['requires_inspection'] ?? '');
        if ($ri !== '' && !in_array($ri, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'requires_inspection must be 0 or 1'];
        }
        $active = trim($row['active'] ?? '');
        if ($active !== '' && !in_array($active, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'active must be 0 or 1'];
        }

        return $errors;
    }

    private function validateCustomerRow(array $row): array
    {
        $errors = [];

        if (trim($row['customer_code'] ?? '') === '') {
            $errors[] = ['status' => 'error', 'message' => 'customer_code is required'];
        }

        $pt = trim($row['payment_terms_name'] ?? '');
        if ($pt !== '') {
            $stmt = $this->db->prepare('SELECT id FROM payment_terms WHERE name = ?');
            $stmt->execute([$pt]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'warning', 'message' => "Payment terms '{$pt}' not found — will import as null"];
            }
        }

        if (($row['credit_limit'] ?? '') !== '' && !is_numeric($row['credit_limit'])) {
            $errors[] = ['status' => 'error', 'message' => 'credit_limit must be numeric'];
        }
        $te = trim($row['tax_exempt'] ?? '');
        if ($te !== '' && !in_array($te, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'tax_exempt must be 0 or 1'];
        }
        $active = trim($row['active'] ?? '');
        if ($active !== '' && !in_array($active, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'active must be 0 or 1'];
        }

        return $errors;
    }

    private function validateSupplierRow(array $row): array
    {
        $errors = [];

        if (trim($row['supplier_code'] ?? '') === '') {
            $errors[] = ['status' => 'error', 'message' => 'supplier_code is required'];
        }

        $pt = trim($row['payment_terms_name'] ?? '');
        if ($pt !== '') {
            $stmt = $this->db->prepare('SELECT id FROM payment_terms WHERE name = ?');
            $stmt->execute([$pt]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'warning', 'message' => "Payment terms '{$pt}' not found — will import as null"];
            }
        }

        $active = trim($row['active'] ?? '');
        if ($active !== '' && !in_array($active, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'active must be 0 or 1'];
        }

        return $errors;
    }

    private function validateInventoryRow(array $row): array
    {
        $errors = [];

        $itemCode = trim($row['item_code'] ?? '');
        if ($itemCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'item_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
            $stmt->execute([$itemCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Item '{$itemCode}' not found"];
            }
        }

        $facilityCode = trim($row['facility_code'] ?? '');
        if ($facilityCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'facility_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM facilities WHERE code = ?');
            $stmt->execute([$facilityCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Facility '{$facilityCode}' not found"];
            }
        }

        if (trim($row['lot_number'] ?? '') === '') {
            $errors[] = ['status' => 'error', 'message' => 'lot_number is required'];
        }

        $qty = trim($row['quantity'] ?? '');
        if ($qty === '' || !is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = ['status' => 'error', 'message' => 'quantity must be a positive number'];
        }

        $cost = trim($row['unit_cost'] ?? '');
        if ($cost === '' || !is_numeric($cost) || (float) $cost <= 0) {
            $errors[] = ['status' => 'error', 'message' => 'unit_cost must be a positive number'];
        }

        $expDate = trim($row['expiration_date'] ?? '');
        if ($expDate !== '' && !\DateTime::createFromFormat('Y-m-d', $expDate)) {
            $errors[] = ['status' => 'error', 'message' => 'expiration_date must be YYYY-MM-DD'];
        }

        return $errors;
    }

    private function validatePricingRow(array $row): array
    {
        $errors = [];

        $custCode = trim($row['customer_code'] ?? '');
        if ($custCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'customer_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE customer_code = ?');
            $stmt->execute([$custCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Customer '{$custCode}' not found"];
            }
        }

        $itemCode = trim($row['item_code'] ?? '');
        if ($itemCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'item_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
            $stmt->execute([$itemCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Item '{$itemCode}' not found"];
            }
        }

        $minQty = trim($row['min_quantity'] ?? '');
        if ($minQty !== '' && (!is_numeric($minQty) || (float) $minQty < 0)) {
            $errors[] = ['status' => 'error', 'message' => 'min_quantity must be numeric >= 0'];
        }

        $price = trim($row['unit_price'] ?? '');
        if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
            $errors[] = ['status' => 'error', 'message' => 'unit_price must be a positive number'];
        }

        $effDate = trim($row['effective_date'] ?? '');
        if ($effDate === '') {
            $errors[] = ['status' => 'error', 'message' => 'effective_date is required'];
        } elseif (!\DateTime::createFromFormat('Y-m-d', $effDate)) {
            $errors[] = ['status' => 'error', 'message' => 'effective_date must be YYYY-MM-DD'];
        }

        return $errors;
    }

    private function validateVendorRow(array $row): array
    {
        $errors = [];

        $itemCode = trim($row['item_code'] ?? '');
        if ($itemCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'item_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
            $stmt->execute([$itemCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Item '{$itemCode}' not found"];
            }
        }

        $supplierCode = trim($row['supplier_code'] ?? '');
        if ($supplierCode === '') {
            $errors[] = ['status' => 'error', 'message' => 'supplier_code is required'];
        } else {
            $stmt = $this->db->prepare('SELECT id FROM suppliers WHERE supplier_code = ?');
            $stmt->execute([$supplierCode]);
            if (!$stmt->fetch()) {
                $errors[] = ['status' => 'error', 'message' => "Supplier '{$supplierCode}' not found"];
            }
        }

        $cost = trim($row['approved_unit_cost'] ?? '');
        if ($cost === '' || !is_numeric($cost) || (float) $cost <= 0) {
            $errors[] = ['status' => 'error', 'message' => 'approved_unit_cost must be a positive number'];
        }

        $lead = trim($row['lead_time_days'] ?? '');
        if ($lead !== '' && (!ctype_digit($lead) || (int) $lead < 0)) {
            $errors[] = ['status' => 'error', 'message' => 'lead_time_days must be an integer >= 0'];
        }

        $pref = trim($row['is_preferred'] ?? '');
        if ($pref !== '' && !in_array($pref, ['0', '1'], true)) {
            $errors[] = ['status' => 'error', 'message' => 'is_preferred must be 0 or 1'];
        }

        return $errors;
    }

    // ── Import Row ────────────────────────────────────────────────

    private function importRow(string $entity, array $row): void
    {
        match ($entity) {
            'items'             => $this->importItemRow($row),
            'customers'         => $this->importCustomerRow($row),
            'suppliers'         => $this->importSupplierRow($row),
            'opening-inventory' => $this->importInventoryRow($row),
            'customer-pricing'  => $this->importPricingRow($row),
            'approved-vendors'  => $this->importVendorRow($row),
        };
    }

    private function importItemRow(array $row): void
    {
        $code = trim($row['item_code']);
        $uomAbbr = trim($row['uom_abbreviation'] ?? '');
        $uomId = null;
        if ($uomAbbr !== '') {
            $stmt = $this->db->prepare('SELECT id FROM uom WHERE abbreviation = ? AND active = 1');
            $stmt->execute([$uomAbbr]);
            $uomId = $stmt->fetchColumn() ?: null;
        }
        if (!$uomId) {
            $uomId = $this->db->query('SELECT id FROM uom WHERE active = 1 ORDER BY id LIMIT 1')->fetchColumn() ?: 1;
        }

        $existing = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
        $existing->execute([$code]);
        $existingId = $existing->fetchColumn();

        $data = [
            trim($row['description'] ?? ''),
            strtoupper(trim($row['item_type'] ?? '')),
            strtoupper(trim($row['gl_group'] ?? '')),
            $uomId,
            is_numeric($row['unit_cost'] ?? '') ? (float) $row['unit_cost'] : 0,
            is_numeric($row['sale_price'] ?? '') ? (float) $row['sale_price'] : 0,
            is_numeric($row['reorder_min'] ?? '') ? (float) $row['reorder_min'] : null,
            is_numeric($row['reorder_max'] ?? '') ? (float) $row['reorder_max'] : null,
            is_numeric($row['shelf_life_days'] ?? '') ? (int) $row['shelf_life_days'] : null,
            in_array(trim($row['requires_inspection'] ?? ''), ['1'], true) ? 1 : 0,
            in_array(trim($row['active'] ?? '1'), ['0'], true) ? 0 : 1,
            trim($row['notes'] ?? '') ?: null,
        ];

        if ($existingId) {
            $this->db->prepare(
                'UPDATE items SET description=?, item_type=?, gl_group=?, uom_id=?, unit_cost=?, sale_price=?, reorder_min=?, reorder_max=?, shelf_life_days=?, requires_inspection=?, active=?, notes=? WHERE id=?'
            )->execute(array_merge($data, [$existingId]));
        } else {
            $this->db->prepare(
                'INSERT INTO items (item_code, description, item_type, gl_group, uom_id, unit_cost, sale_price, reorder_min, reorder_max, shelf_life_days, requires_inspection, active, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge([$code], $data));
        }
    }

    private function importCustomerRow(array $row): void
    {
        $code = trim($row['customer_code']);
        $ptName = trim($row['payment_terms_name'] ?? '');
        $ptId = null;
        if ($ptName !== '') {
            $stmt = $this->db->prepare('SELECT id FROM payment_terms WHERE name = ?');
            $stmt->execute([$ptName]);
            $ptId = $stmt->fetchColumn() ?: null;
        }

        $existing = $this->db->prepare('SELECT id FROM customers WHERE customer_code = ?');
        $existing->execute([$code]);
        $existingId = $existing->fetchColumn();

        $data = [
            trim($row['company_name'] ?? ''),
            trim($row['billing_street'] ?? '') ?: null,
            trim($row['billing_city'] ?? '') ?: null,
            trim($row['billing_state'] ?? '') ?: null,
            trim($row['billing_zip'] ?? '') ?: null,
            trim($row['billing_country'] ?? '') ?: null,
            trim($row['phone'] ?? '') ?: null,
            trim($row['fax'] ?? '') ?: null,
            $ptId,
            is_numeric($row['credit_limit'] ?? '') ? (float) $row['credit_limit'] : 0,
            in_array(trim($row['tax_exempt'] ?? ''), ['1'], true) ? 1 : 0,
            in_array(trim($row['active'] ?? '1'), ['0'], true) ? 0 : 1,
            trim($row['notes'] ?? '') ?: null,
        ];

        if ($existingId) {
            $this->db->prepare(
                'UPDATE customers SET company_name=?, billing_street=?, billing_city=?, billing_state=?, billing_zip=?, billing_country=?, phone=?, fax=?, payment_terms_id=?, credit_limit=?, tax_exempt=?, active=?, notes=? WHERE id=?'
            )->execute(array_merge($data, [$existingId]));
        } else {
            $this->db->prepare(
                'INSERT INTO customers (customer_code, company_name, billing_street, billing_city, billing_state, billing_zip, billing_country, phone, fax, payment_terms_id, credit_limit, tax_exempt, active, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge([$code], $data));
        }
    }

    private function importSupplierRow(array $row): void
    {
        $code = trim($row['supplier_code']);
        $ptName = trim($row['payment_terms_name'] ?? '');
        $ptId = null;
        if ($ptName !== '') {
            $stmt = $this->db->prepare('SELECT id FROM payment_terms WHERE name = ?');
            $stmt->execute([$ptName]);
            $ptId = $stmt->fetchColumn() ?: null;
        }

        $existing = $this->db->prepare('SELECT id FROM suppliers WHERE supplier_code = ?');
        $existing->execute([$code]);
        $existingId = $existing->fetchColumn();

        $data = [
            trim($row['company_name'] ?? ''),
            trim($row['street'] ?? '') ?: null,
            trim($row['city'] ?? '') ?: null,
            trim($row['state'] ?? '') ?: null,
            trim($row['zip'] ?? '') ?: null,
            trim($row['country'] ?? '') ?: null,
            trim($row['phone'] ?? '') ?: null,
            trim($row['fax'] ?? '') ?: null,
            $ptId,
            in_array(trim($row['active'] ?? '1'), ['0'], true) ? 0 : 1,
            trim($row['notes'] ?? '') ?: null,
        ];

        if ($existingId) {
            $this->db->prepare(
                'UPDATE suppliers SET company_name=?, street=?, city=?, state=?, zip=?, country=?, phone=?, fax=?, payment_terms_id=?, active=?, notes=? WHERE id=?'
            )->execute(array_merge($data, [$existingId]));
        } else {
            $this->db->prepare(
                'INSERT INTO suppliers (supplier_code, company_name, street, city, state, zip, country, phone, fax, payment_terms_id, active, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge([$code], $data));
        }
    }

    private function importInventoryRow(array $row): void
    {
        $itemId = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
        $itemId->execute([trim($row['item_code'])]);
        $itemId = (int) $itemId->fetchColumn();

        $facilityId = $this->db->prepare('SELECT id FROM facilities WHERE code = ?');
        $facilityId->execute([trim($row['facility_code'])]);
        $facilityId = (int) $facilityId->fetchColumn();

        $qty = (float) $row['quantity'];
        $cost = (float) $row['unit_cost'];
        $lotNumber = trim($row['lot_number']);
        $expDate = trim($row['expiration_date'] ?? '') ?: null;

        $this->db->prepare(
            'INSERT INTO fifo_lots (item_id, facility_id, lot_number, quantity, remaining_quantity, unit_cost, source_type, expiration_date, status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$itemId, $facilityId, $lotNumber, $qty, $qty, $cost, 'ADJUSTMENT', $expDate, 'AVAILABLE']);

        $lotId = (int) $this->db->lastInsertId();

        $this->db->prepare(
            'INSERT INTO inventory_transactions (item_id, facility_id, fifo_lot_id, transaction_type, quantity, unit_cost, reference_type, reference_id, user_id)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$itemId, $facilityId, $lotId, 'OPENING_BALANCE', $qty, $cost, 'import', $lotId, $_SESSION['user']['id'] ?? 0]);
    }

    private function importPricingRow(array $row): void
    {
        $custId = $this->db->prepare('SELECT id FROM customers WHERE customer_code = ?');
        $custId->execute([trim($row['customer_code'])]);
        $custId = (int) $custId->fetchColumn();

        $itemId = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
        $itemId->execute([trim($row['item_code'])]);
        $itemId = (int) $itemId->fetchColumn();

        $minQty = is_numeric($row['min_quantity'] ?? '') ? (float) $row['min_quantity'] : 1;
        $price = (float) $row['unit_price'];
        $effDate = trim($row['effective_date']);

        // Upsert on customer_id + item_id + min_quantity + effective_date
        $existing = $this->db->prepare(
            'SELECT id FROM customer_pricing WHERE customer_id=? AND item_id=? AND min_quantity=? AND effective_date=?'
        );
        $existing->execute([$custId, $itemId, $minQty, $effDate]);
        $existingId = $existing->fetchColumn();

        if ($existingId) {
            $this->db->prepare('UPDATE customer_pricing SET unit_price=? WHERE id=?')
                ->execute([$price, $existingId]);
        } else {
            $this->db->prepare(
                'INSERT INTO customer_pricing (customer_id, item_id, min_quantity, unit_price, effective_date) VALUES (?,?,?,?,?)'
            )->execute([$custId, $itemId, $minQty, $price, $effDate]);
        }
    }

    private function importVendorRow(array $row): void
    {
        $itemId = $this->db->prepare('SELECT id FROM items WHERE item_code = ?');
        $itemId->execute([trim($row['item_code'])]);
        $itemId = (int) $itemId->fetchColumn();

        $supplierId = $this->db->prepare('SELECT id FROM suppliers WHERE supplier_code = ?');
        $supplierId->execute([trim($row['supplier_code'])]);
        $supplierId = (int) $supplierId->fetchColumn();

        $cost = (float) $row['approved_unit_cost'];
        $leadDays = trim($row['lead_time_days'] ?? '') !== '' ? (int) $row['lead_time_days'] : null;
        $preferred = in_array(trim($row['is_preferred'] ?? ''), ['1'], true) ? 1 : 0;

        $existing = $this->db->prepare('SELECT id FROM approved_vendor_list WHERE item_id=? AND supplier_id=?');
        $existing->execute([$itemId, $supplierId]);
        $existingId = $existing->fetchColumn();

        if ($existingId) {
            $this->db->prepare(
                'UPDATE approved_vendor_list SET approved_unit_cost=?, lead_time_days=?, is_preferred=? WHERE id=?'
            )->execute([$cost, $leadDays, $preferred, $existingId]);
        } else {
            $this->db->prepare(
                'INSERT INTO approved_vendor_list (item_id, supplier_id, approved_unit_cost, lead_time_days, is_preferred) VALUES (?,?,?,?,?)'
            )->execute([$itemId, $supplierId, $cost, $leadDays, $preferred]);
        }
    }

    // ── CSV Templates ─────────────────────────────────────────────

    public function getTemplate(string $entity): ?array
    {
        return match ($entity) {
            'items' => [
                ['item_code', 'description', 'item_type', 'gl_group', 'uom_abbreviation', 'unit_cost', 'sale_price', 'reorder_min', 'reorder_max', 'shelf_life_days', 'requires_inspection', 'active', 'notes'],
                ['RESIN-001', 'Acrylic Resin 45%', 'RAW_MATERIAL', 'RAW_MATERIAL', 'lb', '2.50', '0.00', '100', '500', '', '0', '1', 'Main resin base'],
            ],
            'customers' => [
                ['customer_code', 'company_name', 'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'phone', 'fax', 'payment_terms_name', 'credit_limit', 'tax_exempt', 'active', 'notes'],
                ['CUST-001', 'Acme Corporation', '123 Main St', 'Springfield', 'IL', '62701', 'US', '555-0100', '', 'Net 30', '50000.00', '0', '1', 'Key account'],
            ],
            'suppliers' => [
                ['supplier_code', 'company_name', 'street', 'city', 'state', 'zip', 'country', 'phone', 'fax', 'payment_terms_name', 'active', 'notes'],
                ['SUP-001', 'ChemCo Supplies', '456 Industrial Ave', 'Chicago', 'IL', '60601', 'US', '555-0200', '', 'Net 30', '1', 'Primary chemical supplier'],
            ],
            'opening-inventory' => [
                ['item_code', 'facility_code', 'lot_number', 'quantity', 'unit_cost', 'expiration_date'],
                ['RESIN-001', 'MAIN', 'LOT-2026-001', '500.00', '2.50', '2027-06-30'],
            ],
            'customer-pricing' => [
                ['customer_code', 'item_code', 'min_quantity', 'unit_price', 'effective_date'],
                ['CUST-001', 'RESIN-001', '1', '3.25', '2026-01-01'],
            ],
            'approved-vendors' => [
                ['item_code', 'supplier_code', 'approved_unit_cost', 'lead_time_days', 'is_preferred'],
                ['RESIN-001', 'SUP-001', '2.50', '14', '1'],
            ],
            default => null,
        };
    }

    // ── CSV Parser ────────────────────────────────────────────────

    public static function parseCsvUpload(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [];
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }
        $headers = array_map('trim', $headers);
        // Strip BOM from first header
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (count(array_filter($line, fn($v) => trim($v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = trim($line[$i] ?? '');
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}
