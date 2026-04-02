<?php

namespace PrecisionInk\Controllers;

class SupplierController extends BaseController
{
    /**
     * List suppliers with filters and pagination.
     */
    public function index(): void
    {
        if (!$this->checkPermission('suppliers', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterActive = $_GET['active'] ?? '1';
        $filterSearch = trim($_GET['q'] ?? '');

        $where = ['s.deleted_at IS NULL'];
        $params = [];

        if ($filterActive !== '') {
            $where[] = 's.active = ?';
            $params[] = (int)$filterActive;
        }
        if ($filterSearch) {
            $where[] = '(s.supplier_code LIKE ? OR s.company_name LIKE ?)';
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM suppliers s WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT s.*, pt.name as payment_terms_name
            FROM suppliers s
            LEFT JOIN payment_terms pt ON s.payment_terms_id = pt.id
            WHERE {$whereClause}
            ORDER BY s.supplier_code ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll();

        $this->renderView('suppliers/list', [
            'suppliers' => $suppliers,
            'filters' => ['active' => $filterActive, 'q' => $filterSearch],
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): void
    {
        if (!$this->checkPermission('suppliers', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $paymentTerms = $this->db()->query("SELECT id, name FROM payment_terms WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('suppliers/edit', [
            'mode' => 'create',
            'supplier' => null,
            'paymentTerms' => $paymentTerms,
            'errors' => [],
        ]);
    }

    /**
     * Save new supplier.
     */
    public function store(): void
    {
        if (!$this->checkPermission('suppliers', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $data = $this->extractSupplierData();
        $errors = $this->validateSupplier($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $paymentTerms = $this->db()->query("SELECT id, name FROM payment_terms WHERE active = 1 ORDER BY name")->fetchAll();
            $this->renderView('suppliers/edit', [
                'mode' => 'create',
                'supplier' => $data,
                'paymentTerms' => $paymentTerms,
                'errors' => $errors,
            ]);
            return;
        }

        $stmt = $this->db()->prepare("
            INSERT INTO suppliers (supplier_code, company_name, street, city, state, zip, country, phone, fax, payment_terms_id, notes, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['supplier_code'], $data['company_name'],
            $data['street'] ?: null, $data['city'] ?: null, $data['state'] ?: null,
            $data['zip'] ?: null, $data['country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null,
            $data['payment_terms_id'] ?: null, $data['notes'] ?: null, $data['active'],
        ]);
        $supplierId = (int)$this->db()->lastInsertId();

        $cfErrors = $this->customFieldService->saveValues('suppliers', $supplierId, $_POST);

        $this->auditCreate('suppliers', $supplierId, $data);
        $this->toast('Supplier created successfully.', 'success');
        $this->redirect("/suppliers/{$supplierId}");
    }

    /**
     * View supplier details (tabbed).
     */
    public function view(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $supplier = $this->getSupplierOrFail((int)$id);

        // Contacts
        $contacts = $this->db()->prepare("
            SELECT * FROM supplier_contacts WHERE supplier_id = ? ORDER BY is_primary DESC, active DESC, last_name ASC
        ");
        $contacts->execute([(int)$id]);
        $contacts = $contacts->fetchAll();

        // Performance metrics
        $performance = $this->supplierService->getPerformanceMetrics((int)$id);

        // AVL
        $avlStmt = $this->db()->prepare("
            SELECT avl.*, i.item_code, i.description as item_description
            FROM approved_vendor_list avl
            JOIN items i ON avl.item_id = i.id
            WHERE avl.supplier_id = ?
            ORDER BY avl.active DESC, i.item_code ASC
        ");
        $avlStmt->execute([(int)$id]);
        $avlItems = $avlStmt->fetchAll();

        // Recent POs (10 most recent)
        $poStmt = $this->db()->prepare("
            SELECT po.id, po.po_number, po.order_date, po.expected_delivery_date, po.status,
                   f.name as facility_name
            FROM purchase_orders po
            LEFT JOIN facilities f ON po.facility_id = f.id
            WHERE po.supplier_id = ? AND po.deleted_at IS NULL
            ORDER BY po.order_date DESC
            LIMIT 10
        ");
        $poStmt->execute([(int)$id]);
        $recentPOs = $poStmt->fetchAll();

        // SCARs
        $scarStmt = $this->db()->prepare("
            SELECT id, scar_number, status, created_at FROM scars WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 10
        ");
        $scarStmt->execute([(int)$id]);
        $scars = $scarStmt->fetchAll();

        $this->renderView('suppliers/view', [
            'supplier' => $supplier,
            'contacts' => $contacts,
            'performance' => $performance,
            'avlItems' => $avlItems,
            'recentPOs' => $recentPOs,
            'scars' => $scars,
            'record' => $supplier,
        ]);
    }

    /**
     * Show edit form.
     */
    public function editForm(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $supplier = $this->getSupplierOrFail((int)$id);
        $paymentTerms = $this->db()->query("SELECT id, name FROM payment_terms WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('suppliers/edit', [
            'mode' => 'edit',
            'supplier' => $supplier,
            'paymentTerms' => $paymentTerms,
            'errors' => [],
        ]);
    }

    /**
     * Save edits.
     */
    public function update(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $supplier = $this->getSupplierOrFail((int)$id);
        $data = $this->extractSupplierData();
        $errors = $this->validateSupplier($data, (int)$id);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $paymentTerms = $this->db()->query("SELECT id, name FROM payment_terms WHERE active = 1 ORDER BY name")->fetchAll();
            $this->renderView('suppliers/edit', [
                'mode' => 'edit',
                'supplier' => array_merge($supplier, $data),
                'paymentTerms' => $paymentTerms,
                'errors' => $errors,
            ]);
            return;
        }

        $this->db()->prepare("
            UPDATE suppliers SET supplier_code = ?, company_name = ?, street = ?, city = ?, state = ?,
                zip = ?, country = ?, phone = ?, fax = ?, payment_terms_id = ?, notes = ?, active = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $data['supplier_code'], $data['company_name'],
            $data['street'] ?: null, $data['city'] ?: null, $data['state'] ?: null,
            $data['zip'] ?: null, $data['country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null,
            $data['payment_terms_id'] ?: null, $data['notes'] ?: null, $data['active'], (int)$id,
        ]);

        $cfErrors = $this->customFieldService->saveValues('suppliers', (int)$id, $_POST);

        $this->auditUpdate('suppliers', (int)$id, $supplier, $data);
        $this->toast('Supplier updated successfully.', 'success');
        $this->redirect("/suppliers/{$id}");
    }

    /**
     * Soft-delete.
     */
    public function deactivate(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'delete')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getSupplierOrFail((int)$id);
        $this->db()->prepare("UPDATE suppliers SET deleted_at = NOW(), active = 0 WHERE id = ?")->execute([(int)$id]);
        $this->auditDelete('suppliers', (int)$id);
        $this->toast('Supplier deactivated.', 'success');
        $this->redirect('/suppliers');
    }

    /**
     * JSON typeahead search.
     */
    public function search(): void
    {
        $q = trim($_GET['q'] ?? '');
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

        if (strlen($q) < 1) {
            $this->jsonResponse([]);
            return;
        }

        $like = "%{$q}%";
        $stmt = $this->db()->prepare("
            SELECT id, supplier_code, company_name,
                   CONCAT(supplier_code, ' — ', company_name) as display
            FROM suppliers
            WHERE deleted_at IS NULL AND active = 1
              AND (supplier_code LIKE ? OR company_name LIKE ?)
            ORDER BY supplier_code
            LIMIT ?
        ");
        $stmt->execute([$like, $like, $limit]);
        $this->jsonResponse($stmt->fetchAll());
    }

    // ── Contacts ───────────────────────────────────────────────────

    public function saveContact(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getSupplierOrFail((int)$id);

        $contactId = (int)($_POST['contact_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contactType = $_POST['contact_type'] ?? 'GENERAL';
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        if (!$firstName || !$lastName) {
            $this->toast('First and last name are required.', 'error');
            $this->redirect("/suppliers/{$id}#contacts");
            return;
        }

        $validTypes = ['SALES', 'ACCOUNTING', 'QUALITY', 'LOGISTICS', 'GENERAL'];
        if (!in_array($contactType, $validTypes)) $contactType = 'GENERAL';

        // If setting as primary, clear other primary flags
        if ($isPrimary) {
            $this->db()->prepare("UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = ?")->execute([(int)$id]);
        }

        if ($contactId) {
            $this->db()->prepare("
                UPDATE supplier_contacts SET first_name = ?, last_name = ?, title = ?, phone = ?, email = ?,
                    contact_type = ?, is_primary = ?, updated_at = NOW()
                WHERE id = ? AND supplier_id = ?
            ")->execute([$firstName, $lastName, $title ?: null, $phone ?: null, $email ?: null, $contactType, $isPrimary, $contactId, (int)$id]);
            $this->auditLog('UPDATE', 'supplier_contacts', $contactId, [], ['name' => "{$firstName} {$lastName}"]);
        } else {
            $this->db()->prepare("
                INSERT INTO supplier_contacts (supplier_id, first_name, last_name, title, phone, email, contact_type, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([(int)$id, $firstName, $lastName, $title ?: null, $phone ?: null, $email ?: null, $contactType, $isPrimary]);
            $this->auditLog('CREATE', 'supplier_contacts', (int)$this->db()->lastInsertId(), [], ['name' => "{$firstName} {$lastName}"]);
        }

        $this->toast('Contact saved.', 'success');
        $this->redirect("/suppliers/{$id}#contacts");
    }

    public function deactivateContact(string $id, string $contactId): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->db()->prepare("UPDATE supplier_contacts SET active = 0 WHERE id = ? AND supplier_id = ?")->execute([(int)$contactId, (int)$id]);
        $this->auditLog('UPDATE', 'supplier_contacts', (int)$contactId, ['active' => 1], ['active' => 0]);
        $this->toast('Contact deactivated.', 'success');
        $this->redirect("/suppliers/{$id}#contacts");
    }

    // ── AVL ────────────────────────────────────────────────────────

    public function saveAvl(string $id): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getSupplierOrFail((int)$id);

        $avlId = (int)($_POST['avl_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $approvedCost = (float)($_POST['approved_unit_cost'] ?? 0);
        $leadTimeDays = ($_POST['lead_time_days'] ?? '') !== '' ? (int)$_POST['lead_time_days'] : null;
        $isPreferred = isset($_POST['is_preferred']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (!$itemId) {
            $this->toast('Item is required.', 'error');
            $this->redirect("/suppliers/{$id}#avl");
            return;
        }

        if ($avlId) {
            $this->db()->prepare("
                UPDATE approved_vendor_list SET item_id = ?, approved_unit_cost = ?, lead_time_days = ?,
                    is_preferred = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND supplier_id = ?
            ")->execute([$itemId, $approvedCost, $leadTimeDays, $isPreferred, $notes ?: null, $avlId, (int)$id]);
            $this->auditLog('UPDATE', 'approved_vendor_list', $avlId, [], ['item_id' => $itemId, 'cost' => $approvedCost]);
        } else {
            // Check for existing entry
            $existing = $this->db()->prepare("SELECT id FROM approved_vendor_list WHERE item_id = ? AND supplier_id = ?");
            $existing->execute([$itemId, (int)$id]);
            if ($existing->fetch()) {
                $this->toast('This item is already on the approved vendor list. Edit the existing entry.', 'error');
                $this->redirect("/suppliers/{$id}#avl");
                return;
            }

            $this->db()->prepare("
                INSERT INTO approved_vendor_list (item_id, supplier_id, approved_unit_cost, lead_time_days, is_preferred, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$itemId, (int)$id, $approvedCost, $leadTimeDays, $isPreferred, $notes ?: null]);
            $this->auditLog('CREATE', 'approved_vendor_list', (int)$this->db()->lastInsertId(), [], ['item_id' => $itemId]);
        }

        $this->toast('AVL entry saved.', 'success');
        $this->redirect("/suppliers/{$id}#avl");
    }

    public function deactivateAvl(string $id, string $avlId): void
    {
        if (!$this->checkPermission('suppliers', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->db()->prepare("UPDATE approved_vendor_list SET active = 0 WHERE id = ? AND supplier_id = ?")->execute([(int)$avlId, (int)$id]);
        $this->auditLog('UPDATE', 'approved_vendor_list', (int)$avlId, ['active' => 1], ['active' => 0]);
        $this->toast('AVL entry removed.', 'success');
        $this->redirect("/suppliers/{$id}#avl");
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function getSupplierOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT s.*, pt.name as payment_terms_name
            FROM suppliers s
            LEFT JOIN payment_terms pt ON s.payment_terms_id = pt.id
            WHERE s.id = ? AND s.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if (!$supplier) {
            http_response_code(404);
            echo 'Supplier not found.';
            exit;
        }
        return $supplier;
    }

    private function extractSupplierData(): array
    {
        return [
            'supplier_code' => strtoupper(trim($_POST['supplier_code'] ?? '')),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'street' => trim($_POST['street'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'zip' => trim($_POST['zip'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'payment_terms_id' => (int)($_POST['payment_terms_id'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validateSupplier(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if (empty($data['supplier_code'])) {
            $errors[] = 'Supplier code is required.';
        } else {
            $sql = "SELECT id FROM suppliers WHERE supplier_code = ? AND deleted_at IS NULL";
            $params = [$data['supplier_code']];
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $checkStmt = $this->db()->prepare($sql);
            $checkStmt->execute($params);
            if ($checkStmt->fetch()) {
                $errors[] = 'Supplier code already exists.';
            }
        }
        if (empty($data['company_name'])) $errors[] = 'Company name is required.';
        return $errors;
    }
}
