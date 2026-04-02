<?php

namespace PrecisionInk\Controllers;

class CustomerController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('customers', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterActive = $_GET['active'] ?? '1';
        $filterSearch = trim($_GET['q'] ?? '');
        $filterRep = (int)($_GET['sales_rep_id'] ?? 0);

        $where = ['c.deleted_at IS NULL'];
        $params = [];

        if ($filterActive !== '') { $where[] = 'c.active = ?'; $params[] = (int)$filterActive; }
        if ($filterSearch) {
            $where[] = '(c.customer_code LIKE ? OR c.company_name LIKE ?)';
            $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
        }
        if ($filterRep) { $where[] = 'c.sales_rep_id = ?'; $params[] = $filterRep; }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM customers c WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT c.*, pt.name as payment_terms_name, u.full_name as sales_rep_name
            FROM customers c
            LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            WHERE {$whereClause}
            ORDER BY c.customer_code ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $customers = $stmt->fetchAll();

        $reps = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();

        $this->renderView('customers/list', [
            'customers' => $customers,
            'filters' => ['active' => $filterActive, 'q' => $filterSearch, 'sales_rep_id' => $filterRep],
            'reps' => $reps,
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
        ]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function create(): void
    {
        if (!$this->checkPermission('customers', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('customers/edit', $this->formData('create'));
    }

    public function store(): void
    {
        if (!$this->checkPermission('customers', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractData();
        $errors = $this->validateCustomer($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $this->renderView('customers/edit', $this->formData('create', $data, $errors));
            return;
        }

        $stmt = $this->db()->prepare("
            INSERT INTO customers (customer_code, company_name, billing_street, billing_city, billing_state,
                billing_zip, billing_country, phone, fax, payment_terms_id, credit_limit, lead_time_days,
                sales_rep_id, default_ship_via_id, tax_exempt, account_hold, account_hold_reason,
                default_internal_notes, active, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['customer_code'], $data['company_name'],
            $data['billing_street'] ?: null, $data['billing_city'] ?: null, $data['billing_state'] ?: null,
            $data['billing_zip'] ?: null, $data['billing_country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null,
            $data['payment_terms_id'] ?: null, $data['credit_limit'], $data['lead_time_days'] ?: null,
            $data['sales_rep_id'] ?: null, $data['default_ship_via_id'] ?: null,
            $data['tax_exempt'], $data['account_hold'], $data['account_hold_reason'] ?: null,
            $data['default_internal_notes'] ?: null, $data['active'], $data['notes'] ?: null,
        ]);
        $id = (int)$this->db()->lastInsertId();
        $this->customFieldService->saveValues('customers', $id, $_POST);
        $this->auditCreate('customers', $id, $data);
        $this->toast('Customer created.', 'success');
        $this->redirect("/customers/{$id}");
    }

    // ── View ────────────────────────────────────────────────────────

    public function view(string $id): void
    {
        if (!$this->checkPermission('customers', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customer = $this->getOrFail((int)$id);
        $credit = $this->creditService->getExposure((int)$id);

        $contactsStmt = $this->db()->prepare("SELECT * FROM customer_contacts WHERE customer_id = ? ORDER BY is_primary DESC, active DESC, last_name");
        $contactsStmt->execute([(int)$id]);
        $contacts = $contactsStmt->fetchAll();

        $shipTos = $this->db()->prepare("SELECT * FROM ship_to_locations WHERE customer_id = ? AND deleted_at IS NULL ORDER BY active DESC, location_name");
        $shipTos->execute([(int)$id]);
        $shipTos = $shipTos->fetchAll();

        $crmProfile = $this->db()->prepare("SELECT p.*, iseg.name as segment_name FROM customer_crm_profiles p LEFT JOIN industry_segments iseg ON p.industry_segment_id = iseg.id WHERE p.customer_id = ?");
        $crmProfile->execute([(int)$id]);
        $crmProfile = $crmProfile->fetch() ?: [];

        $activities = $this->db()->prepare("SELECT a.*, u.full_name as created_by_name FROM customer_activities a LEFT JOIN users u ON a.created_by = u.id WHERE a.customer_id = ? ORDER BY a.created_at DESC LIMIT 50");
        $activities->execute([(int)$id]);
        $activities = $activities->fetchAll();

        $tasks = $this->db()->prepare("SELECT t.*, u.full_name as assigned_name, cc.first_name as contact_first, cc.last_name as contact_last FROM crm_tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN customer_contacts cc ON t.contact_id = cc.id WHERE t.customer_id = ? ORDER BY FIELD(t.status,'OPEN','COMPLETED','CANCELLED'), t.due_date ASC");
        $tasks->execute([(int)$id]);
        $tasks = $tasks->fetchAll();

        $prices = $this->db()->prepare("SELECT cp.*, i.item_code, i.description as item_description FROM customer_prices cp JOIN items i ON cp.item_id = i.id WHERE cp.customer_id = ? ORDER BY cp.active DESC, i.item_code");
        $prices->execute([(int)$id]);
        $prices = $prices->fetchAll();

        $moqs = $this->db()->prepare("SELECT m.*, i.item_code, i.description as item_description FROM customer_moq m JOIN items i ON m.item_id = i.id WHERE m.customer_id = ? ORDER BY m.active DESC, i.item_code");
        $moqs->execute([(int)$id]);
        $moqs = $moqs->fetchAll();

        $recentSOs = $this->db()->prepare("SELECT id, so_number, order_date, status FROM sales_orders WHERE customer_id = ? AND deleted_at IS NULL ORDER BY order_date DESC LIMIT 10");
        $recentSOs->execute([(int)$id]);
        $recentSOs = $recentSOs->fetchAll();

        $recentInvoices = $this->db()->prepare("SELECT id, invoice_number, invoice_date, total_due, status FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC LIMIT 10");
        $recentInvoices->execute([(int)$id]);
        $recentInvoices = $recentInvoices->fetchAll();

        $users = $this->db()->query("SELECT id, full_name FROM users WHERE active = 1 ORDER BY full_name")->fetchAll();
        $segments = $this->db()->query("SELECT id, name FROM industry_segments WHERE active = 1 ORDER BY name")->fetchAll();

        // Price list assignments
        $plAssignStmt = $this->db()->prepare("
            SELECT cpla.*, pl.name as list_name, pl.list_type, pl.default_priority, pl.effective_date, pl.expiration_date, pl.active as list_active
            FROM customer_price_list_assignments cpla
            JOIN price_lists pl ON cpla.price_list_id = pl.id
            WHERE cpla.customer_id = ?
            ORDER BY cpla.active DESC, COALESCE(cpla.priority_override, pl.default_priority) ASC
        ");
        $plAssignStmt->execute([(int)$id]);
        $priceListAssignments = $plAssignStmt->fetchAll();

        $availablePriceLists = $this->db()->prepare("
            SELECT pl.id, pl.name, pl.default_priority
            FROM price_lists pl
            WHERE pl.active = 1 AND pl.list_type = 'CUSTOMER'
              AND pl.id NOT IN (SELECT price_list_id FROM customer_price_list_assignments WHERE customer_id = ? AND active = 1)
            ORDER BY pl.name
        ");
        $availablePriceLists->execute([(int)$id]);
        $availablePriceLists = $availablePriceLists->fetchAll();

        $this->renderView('customers/view', [
            'customer' => $customer, 'credit' => $credit, 'contacts' => $contacts,
            'shipTos' => $shipTos, 'crmProfile' => $crmProfile, 'activities' => $activities,
            'tasks' => $tasks, 'prices' => $prices, 'moqs' => $moqs,
            'recentSOs' => $recentSOs, 'recentInvoices' => $recentInvoices,
            'users' => $users, 'segments' => $segments, 'record' => $customer,
            'priceListAssignments' => $priceListAssignments, 'availablePriceLists' => $availablePriceLists,
        ]);
    }

    // ── Edit ────────────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $customer = $this->getOrFail((int)$id);
        $this->renderView('customers/edit', $this->formData('edit', $customer));
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $customer = $this->getOrFail((int)$id);
        $data = $this->extractData();
        $errors = $this->validateCustomer($data, (int)$id);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $this->renderView('customers/edit', $this->formData('edit', array_merge($customer, $data), $errors));
            return;
        }

        $this->db()->prepare("
            UPDATE customers SET customer_code=?, company_name=?, billing_street=?, billing_city=?,
                billing_state=?, billing_zip=?, billing_country=?, phone=?, fax=?, payment_terms_id=?,
                credit_limit=?, lead_time_days=?, sales_rep_id=?, default_ship_via_id=?, tax_exempt=?,
                account_hold=?, account_hold_reason=?, default_internal_notes=?, active=?, notes=?, updated_at=NOW()
            WHERE id=?
        ")->execute([
            $data['customer_code'], $data['company_name'],
            $data['billing_street'] ?: null, $data['billing_city'] ?: null, $data['billing_state'] ?: null,
            $data['billing_zip'] ?: null, $data['billing_country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null,
            $data['payment_terms_id'] ?: null, $data['credit_limit'], $data['lead_time_days'] ?: null,
            $data['sales_rep_id'] ?: null, $data['default_ship_via_id'] ?: null,
            $data['tax_exempt'], $data['account_hold'], $data['account_hold_reason'] ?: null,
            $data['default_internal_notes'] ?: null, $data['active'], $data['notes'] ?: null, (int)$id,
        ]);
        $this->customFieldService->saveValues('customers', (int)$id, $_POST);
        $this->auditUpdate('customers', (int)$id, $customer, $data);
        $this->toast('Customer updated.', 'success');
        $this->redirect("/customers/{$id}");
    }

    public function deactivate(string $id): void
    {
        if (!$this->checkPermission('customers', 'delete')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);
        $this->db()->prepare("UPDATE customers SET deleted_at=NOW(), active=0 WHERE id=?")->execute([(int)$id]);
        $this->auditDelete('customers', (int)$id);
        $this->toast('Customer deactivated.', 'success');
        $this->redirect('/customers');
    }

    // ── Search ──────────────────────────────────────────────────────

    public function search(): void
    {
        $q = trim($_GET['q'] ?? '');
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
        if (strlen($q) < 1) { $this->jsonResponse([]); return; }

        $like = "%{$q}%";
        $stmt = $this->db()->prepare("
            SELECT id, customer_code, company_name, account_hold,
                   CONCAT(customer_code, ' — ', company_name) as display
            FROM customers WHERE deleted_at IS NULL AND active = 1
              AND (customer_code LIKE ? OR company_name LIKE ?)
            ORDER BY customer_code LIMIT ?
        ");
        $stmt->execute([$like, $like, $limit]);
        $this->jsonResponse($stmt->fetchAll());
    }

    // ── Contacts ────────────────────────────────────────────────────

    public function saveContact(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $contactId = (int)($_POST['contact_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contactType = $_POST['contact_type'] ?? 'GENERAL';
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        if (!$firstName || !$lastName) { $this->toast('First and last name required.', 'error'); $this->redirect("/customers/{$id}#contacts"); return; }

        $validTypes = ['BILLING','SHIPPING','QUALITY','GENERAL','DECISION_MAKER','INFLUENCER'];
        if (!in_array($contactType, $validTypes)) $contactType = 'GENERAL';

        if ($isPrimary) {
            $this->db()->prepare("UPDATE customer_contacts SET is_primary=0 WHERE customer_id=?")->execute([(int)$id]);
        }

        if ($contactId) {
            $this->db()->prepare("UPDATE customer_contacts SET first_name=?, last_name=?, title=?, phone=?, email=?, contact_type=?, is_primary=?, updated_at=NOW() WHERE id=? AND customer_id=?")
                ->execute([$firstName, $lastName, $title ?: null, $phone ?: null, $email ?: null, $contactType, $isPrimary, $contactId, (int)$id]);
            $this->auditLog('UPDATE', 'customer_contacts', $contactId, [], ['name' => "{$firstName} {$lastName}"]);
        } else {
            $this->db()->prepare("INSERT INTO customer_contacts (customer_id, first_name, last_name, title, phone, email, contact_type, is_primary) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([(int)$id, $firstName, $lastName, $title ?: null, $phone ?: null, $email ?: null, $contactType, $isPrimary]);
            $this->auditLog('CREATE', 'customer_contacts', (int)$this->db()->lastInsertId(), [], ['name' => "{$firstName} {$lastName}"]);
        }
        $this->toast('Contact saved.', 'success');
        $this->redirect("/customers/{$id}#contacts");
    }

    public function deactivateContact(string $id, string $contactId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE customer_contacts SET active=0 WHERE id=? AND customer_id=?")->execute([(int)$contactId, (int)$id]);
        $this->auditLog('UPDATE', 'customer_contacts', (int)$contactId, ['active'=>1], ['active'=>0]);
        $this->toast('Contact deactivated.', 'success');
        $this->redirect("/customers/{$id}#contacts");
    }

    // ── Ship-To Locations ───────────────────────────────────────────

    public function createShipTo(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $customer = $this->getOrFail((int)$id);
        $this->renderView('customers/ship_to_form', $this->shipToFormData($customer, 'create'));
    }

    public function storeShipTo(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $customer = $this->getOrFail((int)$id);
        $data = $this->extractShipToData();

        if (empty($data['location_name'])) {
            $this->toast('Location name is required.', 'error');
            $this->renderView('customers/ship_to_form', $this->shipToFormData($customer, 'create', $data));
            return;
        }

        $this->db()->prepare("
            INSERT INTO ship_to_locations (customer_id, location_name, street, city, state, zip, country,
                phone, fax, contact_name, email, sales_rep_id, default_ship_via_id, payment_terms_id,
                credit_limit, default_internal_notes, notes, active)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            (int)$id, $data['location_name'], $data['street'] ?: null, $data['city'] ?: null,
            $data['state'] ?: null, $data['zip'] ?: null, $data['country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null, $data['contact_name'] ?: null,
            $data['email'] ?: null, $data['sales_rep_id'] ?: null, $data['default_ship_via_id'] ?: null,
            $data['payment_terms_id'] ?: null, $data['credit_limit'], $data['default_internal_notes'] ?: null,
            $data['notes'] ?: null, $data['active'],
        ]);
        $stId = (int)$this->db()->lastInsertId();
        $this->auditCreate('ship_to_locations', $stId, $data);
        $this->toast('Ship-to location created.', 'success');
        $this->redirect("/customers/{$id}#ship-to");
    }

    public function editShipTo(string $id, string $stId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $customer = $this->getOrFail((int)$id);
        $shipTo = $this->getShipToOrFail((int)$stId, (int)$id);
        $this->renderView('customers/ship_to_form', $this->shipToFormData($customer, 'edit', $shipTo));
    }

    public function updateShipTo(string $id, string $stId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $customer = $this->getOrFail((int)$id);
        $shipTo = $this->getShipToOrFail((int)$stId, (int)$id);
        $data = $this->extractShipToData();

        if (empty($data['location_name'])) {
            $this->toast('Location name is required.', 'error');
            $this->renderView('customers/ship_to_form', $this->shipToFormData($customer, 'edit', array_merge($shipTo, $data)));
            return;
        }

        $this->db()->prepare("
            UPDATE ship_to_locations SET location_name=?, street=?, city=?, state=?, zip=?, country=?,
                phone=?, fax=?, contact_name=?, email=?, sales_rep_id=?, default_ship_via_id=?,
                payment_terms_id=?, credit_limit=?, default_internal_notes=?, notes=?, active=?, updated_at=NOW()
            WHERE id=? AND customer_id=?
        ")->execute([
            $data['location_name'], $data['street'] ?: null, $data['city'] ?: null,
            $data['state'] ?: null, $data['zip'] ?: null, $data['country'] ?: null,
            $data['phone'] ?: null, $data['fax'] ?: null, $data['contact_name'] ?: null,
            $data['email'] ?: null, $data['sales_rep_id'] ?: null, $data['default_ship_via_id'] ?: null,
            $data['payment_terms_id'] ?: null, $data['credit_limit'], $data['default_internal_notes'] ?: null,
            $data['notes'] ?: null, $data['active'], (int)$stId, (int)$id,
        ]);
        $this->auditUpdate('ship_to_locations', (int)$stId, $shipTo, $data);
        $this->toast('Ship-to location updated.', 'success');
        $this->redirect("/customers/{$id}#ship-to");
    }

    public function deactivateShipTo(string $id, string $stId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE ship_to_locations SET deleted_at=NOW(), active=0 WHERE id=? AND customer_id=?")->execute([(int)$stId, (int)$id]);
        $this->auditLog('UPDATE', 'ship_to_locations', (int)$stId, ['active'=>1], ['active'=>0]);
        $this->toast('Ship-to location deactivated.', 'success');
        $this->redirect("/customers/{$id}#ship-to");
    }

    // ── CRM Profile ─────────────────────────────────────────────────

    public function saveCrmProfile(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $data = [
            'equipment_on_site' => trim($_POST['equipment_on_site'] ?? '') ?: null,
            'products_of_interest' => trim($_POST['products_of_interest'] ?? '') ?: null,
            'competition' => trim($_POST['competition'] ?? '') ?: null,
            'key_decision_makers' => trim($_POST['key_decision_makers'] ?? '') ?: null,
            'profile_notes' => trim($_POST['profile_notes'] ?? '') ?: null,
            'industry_segment_id' => (int)($_POST['industry_segment_id'] ?? 0) ?: null,
            'annual_volume' => ($_POST['annual_volume'] ?? '') !== '' ? (float)$_POST['annual_volume'] : null,
            'customer_since' => $_POST['customer_since'] ?? null ?: null,
            'last_rep_visit' => $_POST['last_rep_visit'] ?? null ?: null,
            'next_contact_date' => $_POST['next_contact_date'] ?? null ?: null,
        ];

        $existing = $this->db()->prepare("SELECT id FROM customer_crm_profiles WHERE customer_id=?");
        $existing->execute([(int)$id]);

        if ($existing->fetch()) {
            $this->db()->prepare("
                UPDATE customer_crm_profiles SET equipment_on_site=?, products_of_interest=?, competition=?,
                    key_decision_makers=?, profile_notes=?, industry_segment_id=?, annual_volume=?,
                    customer_since=?, last_rep_visit=?, next_contact_date=?, updated_at=NOW()
                WHERE customer_id=?
            ")->execute([...array_values($data), (int)$id]);
        } else {
            $this->db()->prepare("
                INSERT INTO customer_crm_profiles (customer_id, equipment_on_site, products_of_interest, competition,
                    key_decision_makers, profile_notes, industry_segment_id, annual_volume, customer_since,
                    last_rep_visit, next_contact_date)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([(int)$id, ...array_values($data)]);
        }

        $this->auditLog('UPDATE', 'customer_crm_profiles', (int)$id, [], $data);
        $this->toast('CRM profile saved.', 'success');
        $this->redirect("/customers/{$id}#crm");
    }

    // ── Activities ──────────────────────────────────────────────────

    public function saveActivity(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $type = $_POST['activity_type'] ?? 'GENERAL';
        $subject = trim($_POST['subject'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $contactIds = $_POST['contact_ids'] ?? [];

        if (!$subject) { $this->toast('Subject is required.', 'error'); $this->redirect("/customers/{$id}#activities"); return; }

        $this->db()->prepare("INSERT INTO customer_activities (customer_id, activity_type, subject, notes, created_by) VALUES (?,?,?,?,?)")
            ->execute([(int)$id, $type, $subject, $notes ?: null, $this->currentUserId()]);
        $actId = (int)$this->db()->lastInsertId();

        if (!empty($contactIds)) {
            $stmt = $this->db()->prepare("INSERT INTO customer_activity_contacts (activity_id, contact_id) VALUES (?,?)");
            foreach ($contactIds as $cid) {
                $stmt->execute([$actId, (int)$cid]);
            }
        }

        $this->auditLog('CREATE', 'customer_activities', $actId, [], ['subject' => $subject, 'type' => $type]);
        $this->toast('Activity logged.', 'success');
        $this->redirect("/customers/{$id}#activities");
    }

    // ── Tasks ───────────────────────────────────────────────────────

    public function saveTask(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $priority = $_POST['priority'] ?? 'NORMAL';
        $contactId = (int)($_POST['contact_id'] ?? 0) ?: null;

        if (!$title || !$dueDate || !$assignedTo) {
            $this->toast('Title, due date, and assigned user are required.', 'error');
            $this->redirect("/customers/{$id}#tasks");
            return;
        }

        $this->db()->prepare("INSERT INTO crm_tasks (customer_id, contact_id, title, description, due_date, assigned_to, priority, created_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([(int)$id, $contactId, $title, $description ?: null, $dueDate, $assignedTo, $priority, $this->currentUserId()]);
        $taskId = (int)$this->db()->lastInsertId();

        $this->auditLog('CREATE', 'crm_tasks', $taskId, [], ['title' => $title]);
        $this->toast('Task created.', 'success');
        $this->redirect("/customers/{$id}#tasks");
    }

    public function completeTask(string $id, string $taskId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE crm_tasks SET status='COMPLETED', completed_at=NOW(), updated_at=NOW() WHERE id=? AND customer_id=?")->execute([(int)$taskId, (int)$id]);
        $this->auditLog('UPDATE', 'crm_tasks', (int)$taskId, ['status'=>'OPEN'], ['status'=>'COMPLETED']);
        $this->toast('Task completed.', 'success');
        $this->redirect("/customers/{$id}#tasks");
    }

    public function cancelTask(string $id, string $taskId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE crm_tasks SET status='CANCELLED', updated_at=NOW() WHERE id=? AND customer_id=?")->execute([(int)$taskId, (int)$id]);
        $this->auditLog('UPDATE', 'crm_tasks', (int)$taskId, ['status'=>'OPEN'], ['status'=>'CANCELLED']);
        $this->toast('Task cancelled.', 'success');
        $this->redirect("/customers/{$id}#tasks");
    }

    // ── Pricing ─────────────────────────────────────────────────────

    public function savePrice(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $priceId = (int)($_POST['price_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $minQty = (float)($_POST['min_quantity'] ?? 0);
        $unitPrice = (float)($_POST['unit_price'] ?? 0);
        $effectiveDate = $_POST['effective_date'] ?? date('Y-m-d');

        if (!$itemId) { $this->toast('Item is required.', 'error'); $this->redirect("/customers/{$id}#pricing"); return; }

        if ($priceId) {
            $this->db()->prepare("UPDATE customer_prices SET item_id=?, min_quantity=?, unit_price=?, effective_date=?, updated_at=NOW() WHERE id=? AND customer_id=?")
                ->execute([$itemId, $minQty, $unitPrice, $effectiveDate, $priceId, (int)$id]);
            $this->auditLog('UPDATE', 'customer_prices', $priceId, [], ['item_id'=>$itemId, 'price'=>$unitPrice]);
        } else {
            $this->db()->prepare("INSERT INTO customer_prices (customer_id, item_id, min_quantity, unit_price, effective_date) VALUES (?,?,?,?,?)")
                ->execute([(int)$id, $itemId, $minQty, $unitPrice, $effectiveDate]);
            $this->auditLog('CREATE', 'customer_prices', (int)$this->db()->lastInsertId(), [], ['item_id'=>$itemId, 'price'=>$unitPrice]);
        }
        $this->toast('Price saved.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    public function deactivatePrice(string $id, string $priceId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE customer_prices SET active=0 WHERE id=? AND customer_id=?")->execute([(int)$priceId, (int)$id]);
        $this->auditLog('UPDATE', 'customer_prices', (int)$priceId, ['active'=>1], ['active'=>0]);
        $this->toast('Price deactivated.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    // ── MOQ ─────────────────────────────────────────────────────────

    public function saveMoq(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->getOrFail((int)$id);

        $moqId = (int)($_POST['moq_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $minQty = (float)($_POST['min_quantity'] ?? 0);
        $effectiveDate = $_POST['effective_date'] ?? date('Y-m-d');

        if (!$itemId || $minQty <= 0) { $this->toast('Item and min quantity are required.', 'error'); $this->redirect("/customers/{$id}#pricing"); return; }

        if ($moqId) {
            $this->db()->prepare("UPDATE customer_moq SET item_id=?, min_quantity=?, effective_date=?, updated_at=NOW() WHERE id=? AND customer_id=?")
                ->execute([$itemId, $minQty, $effectiveDate, $moqId, (int)$id]);
            $this->auditLog('UPDATE', 'customer_moq', $moqId, [], ['item_id'=>$itemId]);
        } else {
            $this->db()->prepare("INSERT INTO customer_moq (customer_id, item_id, min_quantity, effective_date) VALUES (?,?,?,?)")
                ->execute([(int)$id, $itemId, $minQty, $effectiveDate]);
            $this->auditLog('CREATE', 'customer_moq', (int)$this->db()->lastInsertId(), [], ['item_id'=>$itemId]);
        }
        $this->toast('MOQ saved.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    public function deactivateMoq(string $id, string $moqId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE customer_moq SET active=0 WHERE id=? AND customer_id=?")->execute([(int)$moqId, (int)$id]);
        $this->auditLog('UPDATE', 'customer_moq', (int)$moqId, ['active'=>1], ['active'=>0]);
        $this->toast('MOQ deactivated.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    // ── Price List Assignments ──────────────────────────────────────

    public function assignPriceList(string $id): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $listId = (int)($_POST['price_list_id'] ?? 0);
        $priority = ($_POST['priority_override'] ?? '') !== '' ? (int)$_POST['priority_override'] : null;
        if (!$listId) { $this->toast('Price list is required.', 'error'); $this->redirect("/customers/{$id}#pricing"); return; }

        $this->db()->prepare("
            INSERT INTO customer_price_list_assignments (customer_id, price_list_id, priority_override, active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE priority_override = VALUES(priority_override), active = 1, updated_at = NOW()
        ")->execute([(int)$id, $listId, $priority]);

        $this->toast('Price list assigned.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    public function updatePriceListAssignment(string $id, string $assignmentId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $priority = ($_POST['priority_override'] ?? '') !== '' ? (int)$_POST['priority_override'] : null;
        $this->db()->prepare("UPDATE customer_price_list_assignments SET priority_override = ?, updated_at = NOW() WHERE id = ? AND customer_id = ?")
            ->execute([$priority, (int)$assignmentId, (int)$id]);
        $this->toast('Priority updated.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    public function removePriceListAssignment(string $id, string $assignmentId): void
    {
        if (!$this->checkPermission('customers', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->db()->prepare("UPDATE customer_price_list_assignments SET active = 0, updated_at = NOW() WHERE id = ? AND customer_id = ?")
            ->execute([(int)$assignmentId, (int)$id]);
        $this->toast('Price list removed.', 'success');
        $this->redirect("/customers/{$id}#pricing");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT c.*, pt.name as payment_terms_name, u.full_name as sales_rep_name, sv.name as ship_via_name
            FROM customers c
            LEFT JOIN payment_terms pt ON c.payment_terms_id = pt.id
            LEFT JOIN users u ON c.sales_rep_id = u.id
            LEFT JOIN ship_via sv ON c.default_ship_via_id = sv.id
            WHERE c.id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) { http_response_code(404); echo 'Customer not found.'; exit; }
        return $c;
    }

    private function getShipToOrFail(int $stId, int $custId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM ship_to_locations WHERE id=? AND customer_id=? AND deleted_at IS NULL");
        $stmt->execute([$stId, $custId]);
        $st = $stmt->fetch();
        if (!$st) { http_response_code(404); echo 'Ship-to location not found.'; exit; }
        return $st;
    }

    private function extractData(): array
    {
        return [
            'customer_code' => strtoupper(trim($_POST['customer_code'] ?? '')),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'billing_street' => trim($_POST['billing_street'] ?? ''),
            'billing_city' => trim($_POST['billing_city'] ?? ''),
            'billing_state' => trim($_POST['billing_state'] ?? ''),
            'billing_zip' => trim($_POST['billing_zip'] ?? ''),
            'billing_country' => trim($_POST['billing_country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'payment_terms_id' => (int)($_POST['payment_terms_id'] ?? 0),
            'credit_limit' => (float)($_POST['credit_limit'] ?? 0),
            'lead_time_days' => ($_POST['lead_time_days'] ?? '') !== '' ? (int)$_POST['lead_time_days'] : null,
            'sales_rep_id' => (int)($_POST['sales_rep_id'] ?? 0),
            'default_ship_via_id' => (int)($_POST['default_ship_via_id'] ?? 0),
            'tax_exempt' => isset($_POST['tax_exempt']) ? 1 : 0,
            'account_hold' => isset($_POST['account_hold']) ? 1 : 0,
            'account_hold_reason' => trim($_POST['account_hold_reason'] ?? ''),
            'default_internal_notes' => trim($_POST['default_internal_notes'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
        ];
    }

    private function extractShipToData(): array
    {
        return [
            'location_name' => trim($_POST['location_name'] ?? ''),
            'street' => trim($_POST['street'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'zip' => trim($_POST['zip'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'sales_rep_id' => isset($_POST['override_sales_rep']) ? ((int)($_POST['sales_rep_id'] ?? 0) ?: null) : null,
            'default_ship_via_id' => isset($_POST['override_ship_via']) ? ((int)($_POST['default_ship_via_id'] ?? 0) ?: null) : null,
            'payment_terms_id' => isset($_POST['override_payment_terms']) ? ((int)($_POST['payment_terms_id'] ?? 0) ?: null) : null,
            'credit_limit' => isset($_POST['override_credit_limit']) ? (float)($_POST['credit_limit'] ?? 0) : null,
            'default_internal_notes' => trim($_POST['default_internal_notes'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validateCustomer(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if (empty($data['customer_code'])) $errors[] = 'Customer code is required.';
        else {
            $sql = "SELECT id FROM customers WHERE customer_code=? AND deleted_at IS NULL";
            $params = [$data['customer_code']];
            if ($excludeId) { $sql .= " AND id!=?"; $params[] = $excludeId; }
            $s = $this->db()->prepare($sql); $s->execute($params);
            if ($s->fetch()) $errors[] = 'Customer code already exists.';
        }
        if (empty($data['company_name'])) $errors[] = 'Company name is required.';
        if ($data['account_hold'] && empty($data['account_hold_reason'])) $errors[] = 'Account hold reason is required when hold is set.';
        return $errors;
    }

    private function formData(string $mode, ?array $customer = null, array $errors = []): array
    {
        return [
            'mode' => $mode,
            'customer' => $customer,
            'paymentTerms' => $this->db()->query("SELECT id, name FROM payment_terms WHERE active=1 ORDER BY name")->fetchAll(),
            'reps' => $this->db()->query("SELECT id, full_name FROM users WHERE active=1 ORDER BY full_name")->fetchAll(),
            'shipVias' => $this->db()->query("SELECT id, name FROM ship_via WHERE active=1 ORDER BY name")->fetchAll(),
            'errors' => $errors,
        ];
    }

    private function shipToFormData(array $customer, string $mode, ?array $shipTo = null): array
    {
        return [
            'mode' => $mode,
            'customer' => $customer,
            'shipTo' => $shipTo,
            'paymentTerms' => $this->db()->query("SELECT id, name FROM payment_terms WHERE active=1 ORDER BY name")->fetchAll(),
            'reps' => $this->db()->query("SELECT id, full_name FROM users WHERE active=1 ORDER BY full_name")->fetchAll(),
            'shipVias' => $this->db()->query("SELECT id, name FROM ship_via WHERE active=1 ORDER BY name")->fetchAll(),
        ];
    }
}
