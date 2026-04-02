<?php

namespace PrecisionInk\Controllers;

class ItemController extends BaseController
{
    /**
     * List items with filters and pagination.
     */
    public function index(): void
    {
        if (!$this->checkPermission('items', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $filterType = $_GET['item_type'] ?? '';
        $filterActive = $_GET['active'] ?? '1';
        $filterSearch = trim($_GET['q'] ?? '');

        $where = ['i.deleted_at IS NULL'];
        $params = [];

        if ($filterType) {
            $where[] = 'i.item_type = ?';
            $params[] = $filterType;
        }
        if ($filterActive !== '') {
            $where[] = 'i.active = ?';
            $params[] = (int)$filterActive;
        }
        if ($filterSearch) {
            $where[] = '(i.item_code LIKE ? OR i.description LIKE ?)';
            $params[] = "%{$filterSearch}%";
            $params[] = "%{$filterSearch}%";
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM items i WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));

        $stmt = $this->db()->prepare("
            SELECT i.*, u.abbreviation as uom_abbr
            FROM items i
            LEFT JOIN uom u ON i.uom_id = u.id
            WHERE {$whereClause}
            ORDER BY i.item_code ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $this->renderView('items/list', [
            'items' => $items,
            'filters' => [
                'item_type' => $filterType,
                'active' => $filterActive,
                'q' => $filterSearch,
            ],
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
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
        $prototypes = $this->db()->query("SELECT id, name, item_type, gl_group, uom_id, shelf_life_days, requires_inspection FROM item_prototypes WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('items/edit', [
            'mode' => 'create',
            'item' => null,
            'uoms' => $uoms,
            'prototypes' => $prototypes,
            'errors' => [],
        ]);
    }

    /**
     * Save new item.
     */
    public function store(): void
    {
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $data = $this->extractItemData();
        $errors = $this->validateItem($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
            $prototypes = $this->db()->query("SELECT id, name, item_type, gl_group, uom_id, shelf_life_days, requires_inspection FROM item_prototypes WHERE active = 1 ORDER BY name")->fetchAll();
            $this->renderView('items/edit', [
                'mode' => 'create',
                'item' => $data,
                'uoms' => $uoms,
                'prototypes' => $prototypes,
                'errors' => $errors,
            ]);
            return;
        }

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare("
                INSERT INTO items (item_code, description, item_type, gl_group, uom_id,
                    unit_cost, sale_price, reorder_min, reorder_max, shelf_life_days,
                    requires_inspection, sds_on_file, sds_last_received, active, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['item_code'], $data['description'], $data['item_type'], $data['gl_group'],
                $data['uom_id'], $data['unit_cost'], $data['sale_price'],
                $data['reorder_min'] ?: null, $data['reorder_max'] ?: null,
                $data['shelf_life_days'] ?: null, $data['requires_inspection'],
                $data['sds_on_file'], $data['sds_last_received'] ?: null,
                $data['active'], $data['notes'] ?: null,
            ]);
            $itemId = (int)$this->db()->lastInsertId();

            $this->db()->commit();

            // Save custom fields
            $cfErrors = $this->customFieldService->saveValues('items', $itemId, $_POST);

            $this->auditCreate('items', $itemId, $data);
            $this->toast('Item created successfully.', 'success');
            $this->redirect("/items/{$itemId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error creating item: ' . $e->getMessage(), 'error');
            $this->redirect('/items/create');
        }
    }

    /**
     * View item details (tabbed).
     */
    public function view(string $id): void
    {
        if (!$this->checkPermission('items', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$id);

        // Pack extensions
        $packExtensions = $this->db()->prepare("SELECT * FROM item_pack_extensions WHERE item_id = ? ORDER BY active DESC, name ASC");
        $packExtensions->execute([(int)$id]);
        $packExtensions = $packExtensions->fetchAll();

        // Aliases
        $aliasStmt = $this->db()->prepare("
            SELECT ia.*, c.company_name as customer_name
            FROM item_aliases ia
            LEFT JOIN customers c ON ia.customer_id = c.id
            WHERE ia.item_id = ?
            ORDER BY ia.active DESC, ia.alias_code ASC
        ");
        $aliasStmt->execute([(int)$id]);
        $aliases = $aliasStmt->fetchAll();

        // Substitutions
        $subStmt = $this->db()->prepare("
            SELECT isub.*, si.item_code as sub_item_code, si.description as sub_description
            FROM item_substitutions isub
            JOIN items si ON isub.substitute_item_id = si.id
            WHERE isub.item_id = ?
            ORDER BY isub.active DESC, isub.priority ASC
        ");
        $subStmt->execute([(int)$id]);
        $substitutions = $subStmt->fetchAll();

        // Locations
        $locStmt = $this->db()->prepare("
            SELECT f.id, f.name, COALESCE(ifl.location, '') as location
            FROM facilities f
            LEFT JOIN item_facility_locations ifl ON ifl.item_id = ? AND ifl.facility_id = f.id
            WHERE f.active = 1
            ORDER BY f.is_default DESC, f.name
        ");
        $locStmt->execute([(int)$id]);
        $locations = $locStmt->fetchAll();

        // Recipe versions
        $recipeStmt = $this->db()->prepare("
            SELECT rv.*, u.full_name as created_by_name
            FROM recipe_versions rv
            LEFT JOIN users u ON rv.created_by = u.id
            WHERE rv.item_id = ?
            ORDER BY rv.version_number DESC
        ");
        $recipeStmt->execute([(int)$id]);
        $recipes = $recipeStmt->fetchAll();

        // Approved vendors
        $avlStmt = $this->db()->prepare("
            SELECT avl.*, s.supplier_code, s.company_name as supplier_name
            FROM approved_vendor_list avl
            JOIN suppliers s ON avl.supplier_id = s.id
            WHERE avl.item_id = ?
            ORDER BY avl.active DESC, avl.is_preferred DESC, s.supplier_code ASC
        ");
        $avlStmt->execute([(int)$id]);
        $approvedVendors = $avlStmt->fetchAll();

        // Global pack types with overrides
        $packTypesStmt = $this->db()->prepare("
            SELECT pet.id, pet.code, pet.name, pet.default_net_weight, pet.tare_weight,
                   COALESCE(ipeo.net_weight_override, pet.default_net_weight) as effective_net,
                   ipeo.net_weight_override, COALESCE(ipeo.active, 1) as item_active
            FROM pack_extension_types pet
            LEFT JOIN item_pack_extension_overrides ipeo ON ipeo.pack_extension_type_id = pet.id AND ipeo.item_id = ?
            WHERE pet.active = 1
            ORDER BY pet.display_sequence, pet.name
        ");
        $packTypesStmt->execute([(int)$id]);
        $globalPackTypes = $packTypesStmt->fetchAll();

        // Active QC spec (legacy)
        $qcSpecStmt = $this->db()->prepare("SELECT * FROM qc_specs WHERE item_id = ? AND is_active = 1 LIMIT 1");
        $qcSpecStmt->execute([(int)$id]);
        $qcSpec = $qcSpecStmt->fetch() ?: null;

        $qcSpecTests = [];
        if ($qcSpec) {
            $qcTestStmt = $this->db()->prepare("SELECT * FROM qc_spec_tests WHERE spec_id = ? ORDER BY display_sequence, id");
            $qcTestStmt->execute([$qcSpec['id']]);
            $qcSpecTests = $qcTestStmt->fetchAll();
        }

        // Global QC test definitions (all active)
        $allQcTests = $this->db()->query("SELECT * FROM qc_test_definitions WHERE active = 1 ORDER BY display_sequence, test_name")->fetchAll();

        // Item QC test assignments
        $itemQcStmt = $this->db()->prepare("
            SELECT iqt.*, qtd.test_name, qtd.test_type, qtd.uom, qtd.default_min_value, qtd.default_max_value, qtd.description as test_description
            FROM item_qc_tests iqt
            JOIN qc_test_definitions qtd ON iqt.qc_test_definition_id = qtd.id
            WHERE iqt.item_id = ?
            ORDER BY iqt.display_sequence, qtd.test_name
        ");
        $itemQcStmt->execute([(int)$id]);
        $itemQcTests = $itemQcStmt->fetchAll();
        $assignedTestIds = array_column($itemQcTests, 'qc_test_definition_id');

        $this->renderView('items/view', [
            'item' => $item,
            'packExtensions' => $packExtensions,
            'globalPackTypes' => $globalPackTypes,
            'aliases' => $aliases,
            'substitutions' => $substitutions,
            'locations' => $locations,
            'recipes' => $recipes,
            'approvedVendors' => $approvedVendors,
            'qcSpec' => $qcSpec,
            'qcSpecTests' => $qcSpecTests,
            'allQcTests' => $allQcTests,
            'itemQcTests' => $itemQcTests,
            'assignedTestIds' => $assignedTestIds,
            'record' => $item,
        ]);
    }

    /**
     * Show edit form.
     */
    public function editForm(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$id);
        $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
        $prototypes = $this->db()->query("SELECT id, name, item_type, gl_group, uom_id, shelf_life_days, requires_inspection FROM item_prototypes WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('items/edit', [
            'mode' => 'edit',
            'item' => $item,
            'uoms' => $uoms,
            'prototypes' => $prototypes,
            'errors' => [],
        ]);
    }

    /**
     * Save edits.
     */
    public function update(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$id);
        $data = $this->extractItemData();
        $errors = $this->validateItem($data, (int)$id);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
            $prototypes = $this->db()->query("SELECT id, name, item_type, gl_group, uom_id, shelf_life_days, requires_inspection FROM item_prototypes WHERE active = 1 ORDER BY name")->fetchAll();
            $this->renderView('items/edit', [
                'mode' => 'edit',
                'item' => array_merge($item, $data),
                'uoms' => $uoms,
                'prototypes' => $prototypes,
                'errors' => $errors,
            ]);
            return;
        }

        $stmt = $this->db()->prepare("
            UPDATE items SET item_code = ?, description = ?, item_type = ?, gl_group = ?,
                uom_id = ?, unit_cost = ?, sale_price = ?, reorder_min = ?, reorder_max = ?,
                shelf_life_days = ?, requires_inspection = ?, sds_on_file = ?,
                sds_last_received = ?, active = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['item_code'], $data['description'], $data['item_type'], $data['gl_group'],
            $data['uom_id'], $data['unit_cost'], $data['sale_price'],
            $data['reorder_min'] ?: null, $data['reorder_max'] ?: null,
            $data['shelf_life_days'] ?: null, $data['requires_inspection'],
            $data['sds_on_file'], $data['sds_last_received'] ?: null,
            $data['active'], $data['notes'] ?: null, (int)$id,
        ]);

        // Save custom fields
        $cfErrors = $this->customFieldService->saveValues('items', (int)$id, $_POST);

        $this->auditUpdate('items', (int)$id, $item, $data);
        $this->toast('Item updated successfully.', 'success');
        $this->redirect("/items/{$id}");
    }

    /**
     * Soft-delete (set deleted_at).
     */
    public function deactivate(string $id): void
    {
        if (!$this->checkPermission('items', 'delete')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$id);

        $this->db()->prepare("UPDATE items SET deleted_at = NOW(), active = 0 WHERE id = ?")->execute([(int)$id]);
        $this->auditDelete('items', (int)$id);
        $this->toast('Item deactivated.', 'success');
        $this->redirect('/items');
    }

    /**
     * Clone an item.
     */
    public function cloneItem(string $id): void
    {
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$id);

        $this->db()->beginTransaction();
        try {
            $stmt = $this->db()->prepare("
                INSERT INTO items (item_code, description, item_type, gl_group, uom_id,
                    unit_cost, sale_price, reorder_min, reorder_max, shelf_life_days,
                    requires_inspection, sds_on_file, sds_last_received, active, notes)
                VALUES ('', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([
                'COPY OF ' . $item['description'], $item['item_type'], $item['gl_group'],
                $item['uom_id'], $item['unit_cost'], $item['sale_price'],
                $item['reorder_min'], $item['reorder_max'], $item['shelf_life_days'],
                $item['requires_inspection'], $item['sds_on_file'], $item['sds_last_received'],
                $item['notes'],
            ]);
            $newId = (int)$this->db()->lastInsertId();

            // Copy pack extensions
            $packs = $this->db()->prepare("SELECT name, net_weight, tare_weight, active FROM item_pack_extensions WHERE item_id = ?");
            $packs->execute([(int)$id]);
            $packInsert = $this->db()->prepare("INSERT INTO item_pack_extensions (item_id, name, net_weight, tare_weight, active) VALUES (?, ?, ?, ?, ?)");
            foreach ($packs->fetchAll() as $pack) {
                $packInsert->execute([$newId, $pack['name'], $pack['net_weight'], $pack['tare_weight'], $pack['active']]);
            }

            // Copy aliases
            $aliasRows = $this->db()->prepare("SELECT customer_id, alias_code, alias_description, active FROM item_aliases WHERE item_id = ?");
            $aliasRows->execute([(int)$id]);
            $aliasInsert = $this->db()->prepare("INSERT INTO item_aliases (item_id, customer_id, alias_code, alias_description, active) VALUES (?, ?, ?, ?, ?)");
            foreach ($aliasRows->fetchAll() as $alias) {
                $aliasInsert->execute([$newId, $alias['customer_id'], $alias['alias_code'], $alias['alias_description'], $alias['active']]);
            }

            // Copy substitutions
            $subRows = $this->db()->prepare("SELECT substitute_item_id, priority, notes, active FROM item_substitutions WHERE item_id = ?");
            $subRows->execute([(int)$id]);
            $subInsert = $this->db()->prepare("INSERT INTO item_substitutions (item_id, substitute_item_id, priority, notes, active) VALUES (?, ?, ?, ?, ?)");
            foreach ($subRows->fetchAll() as $sub) {
                $subInsert->execute([$newId, $sub['substitute_item_id'], $sub['priority'], $sub['notes'], $sub['active']]);
            }

            $this->db()->commit();

            $this->auditCreate('items', $newId, ['cloned_from' => (int)$id]);
            $this->toast('Review and save your cloned item — please assign a new item code.', 'warning');
            $this->redirect("/items/{$newId}/edit");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error cloning item: ' . $e->getMessage(), 'error');
            $this->redirect("/items/{$id}");
        }
    }

    /**
     * JSON search endpoint for typeahead.
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
            SELECT DISTINCT i.id, i.item_code, i.description,
                   NULL as alias_code,
                   CONCAT(i.item_code, ' — ', i.description) as display
            FROM items i
            WHERE i.deleted_at IS NULL AND i.active = 1
              AND (i.item_code LIKE ? OR i.description LIKE ?)

            UNION

            SELECT DISTINCT i.id, i.item_code, i.description,
                   ia.alias_code,
                   CONCAT(ia.alias_code, ' (alias) — ', i.description) as display
            FROM items i
            JOIN item_aliases ia ON ia.item_id = i.id AND ia.active = 1
            WHERE i.deleted_at IS NULL AND i.active = 1
              AND (ia.alias_code LIKE ? OR ia.alias_description LIKE ?)

            ORDER BY display
            LIMIT ?
        ");
        $stmt->execute([$like, $like, $like, $like, $limit]);
        $this->jsonResponse($stmt->fetchAll());
    }

    // ── Pack Extensions ────────────────────────────────────────────

    public function savePack(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getItemOrFail((int)$id);

        $packId = (int)($_POST['pack_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $netWeight = (float)($_POST['net_weight'] ?? 0);
        $tareWeight = (float)($_POST['tare_weight'] ?? 0);

        if (!$name) {
            $this->toast('Pack name is required.', 'error');
            $this->redirect("/items/{$id}#packs");
            return;
        }

        if ($packId) {
            $this->db()->prepare("UPDATE item_pack_extensions SET name = ?, net_weight = ?, tare_weight = ?, updated_at = NOW() WHERE id = ? AND item_id = ?")
                ->execute([$name, $netWeight, $tareWeight, $packId, (int)$id]);
            $this->auditLog('UPDATE', 'item_pack_extensions', $packId, [], ['name' => $name]);
        } else {
            $this->db()->prepare("INSERT INTO item_pack_extensions (item_id, name, net_weight, tare_weight) VALUES (?, ?, ?, ?)")
                ->execute([(int)$id, $name, $netWeight, $tareWeight]);
            $this->auditLog('CREATE', 'item_pack_extensions', (int)$this->db()->lastInsertId(), [], ['name' => $name]);
        }

        $this->toast('Pack extension saved.', 'success');
        $this->redirect("/items/{$id}#packs");
    }

    public function deactivatePack(string $id, string $packId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->db()->prepare("UPDATE item_pack_extensions SET active = 0 WHERE id = ? AND item_id = ?")->execute([(int)$packId, (int)$id]);
        $this->auditLog('UPDATE', 'item_pack_extensions', (int)$packId, ['active' => 1], ['active' => 0]);
        $this->toast('Pack extension deactivated.', 'success');
        $this->redirect("/items/{$id}#packs");
    }

    public function savePackOverride(string $id, string $packTypeId): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $netOverride = ($_POST['net_weight_override'] ?? '') !== '' ? (float)$_POST['net_weight_override'] : null;
        $active = isset($_POST['active']) ? 1 : 0;

        $this->db()->prepare("
            INSERT INTO item_pack_extension_overrides (item_id, pack_extension_type_id, net_weight_override, active)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE net_weight_override = VALUES(net_weight_override), active = VALUES(active), updated_at = NOW()
        ")->execute([(int)$id, (int)$packTypeId, $netOverride, $active]);

        $this->auditLog('UPDATE', 'item_pack_extension_overrides', (int)$id, [], ['pack_type' => (int)$packTypeId, 'net_override' => $netOverride, 'active' => $active]);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->jsonResponse(['success' => true]);
        } else {
            $this->toast('Pack override saved.', 'success');
            $this->redirect("/items/{$id}#packs");
        }
    }

    // ── Item QC Tests ─────────────────────────────────────────────

    public function saveItemQcTest(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $testDefId = (int)($_POST['qc_test_definition_id'] ?? 0);
        if (!$testDefId) { $this->toast('Test definition required.', 'error'); $this->redirect("/items/{$id}"); return; }

        $minVal = ($_POST['min_value'] ?? '') !== '' ? (float)$_POST['min_value'] : null;
        $maxVal = ($_POST['max_value'] ?? '') !== '' ? (float)$_POST['max_value'] : null;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sequence = (int)($_POST['display_sequence'] ?? 0);

        $this->db()->prepare("
            INSERT INTO item_qc_tests (item_id, qc_test_definition_id, min_value, max_value, is_required, display_sequence, active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE min_value = VALUES(min_value), max_value = VALUES(max_value),
                is_required = VALUES(is_required), display_sequence = VALUES(display_sequence), active = 1, updated_at = NOW()
        ")->execute([(int)$id, $testDefId, $minVal, $maxVal, $isRequired, $sequence]);

        $this->toast('QC test assigned.', 'success');
        $this->redirect("/items/{$id}#qc-tests");
    }

    public function updateItemQcTest(string $id, string $assignmentId): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $minVal = ($_POST['min_value'] ?? '') !== '' ? (float)$_POST['min_value'] : null;
        $maxVal = ($_POST['max_value'] ?? '') !== '' ? (float)$_POST['max_value'] : null;
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sequence = (int)($_POST['display_sequence'] ?? 0);

        $this->db()->prepare("
            UPDATE item_qc_tests SET min_value = ?, max_value = ?, is_required = ?, display_sequence = ?, updated_at = NOW()
            WHERE id = ? AND item_id = ?
        ")->execute([$minVal, $maxVal, $isRequired, $sequence, (int)$assignmentId, (int)$id]);

        $this->toast('QC test updated.', 'success');
        $this->redirect("/items/{$id}#qc-tests");
    }

    public function deactivateItemQcTest(string $id, string $assignmentId): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $this->db()->prepare("UPDATE item_qc_tests SET active = 0, updated_at = NOW() WHERE id = ? AND item_id = ?")->execute([(int)$assignmentId, (int)$id]);
        $this->toast('QC test deactivated for this item.', 'success');
        $this->redirect("/items/{$id}#qc-tests");
    }

    // ── Aliases ────────────────────────────────────────────────────

    public function saveAlias(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getItemOrFail((int)$id);

        $aliasId = (int)($_POST['alias_id'] ?? 0);
        $aliasCode = trim($_POST['alias_code'] ?? '');
        $aliasDesc = trim($_POST['alias_description'] ?? '');
        $customerId = (int)($_POST['customer_id'] ?? 0) ?: null;

        if (!$aliasCode || !$aliasDesc) {
            $this->toast('Alias code and description are required.', 'error');
            $this->redirect("/items/{$id}#aliases");
            return;
        }

        if ($aliasId) {
            $this->db()->prepare("UPDATE item_aliases SET alias_code = ?, alias_description = ?, customer_id = ?, updated_at = NOW() WHERE id = ? AND item_id = ?")
                ->execute([$aliasCode, $aliasDesc, $customerId, $aliasId, (int)$id]);
            $this->auditLog('UPDATE', 'item_aliases', $aliasId, [], ['alias_code' => $aliasCode]);
        } else {
            $this->db()->prepare("INSERT INTO item_aliases (item_id, customer_id, alias_code, alias_description) VALUES (?, ?, ?, ?)")
                ->execute([(int)$id, $customerId, $aliasCode, $aliasDesc]);
            $this->auditLog('CREATE', 'item_aliases', (int)$this->db()->lastInsertId(), [], ['alias_code' => $aliasCode]);
        }

        $this->toast('Alias saved.', 'success');
        $this->redirect("/items/{$id}#aliases");
    }

    public function deactivateAlias(string $id, string $aliasId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->db()->prepare("UPDATE item_aliases SET active = 0 WHERE id = ? AND item_id = ?")->execute([(int)$aliasId, (int)$id]);
        $this->auditLog('UPDATE', 'item_aliases', (int)$aliasId, ['active' => 1], ['active' => 0]);
        $this->toast('Alias deactivated.', 'success');
        $this->redirect("/items/{$id}#aliases");
    }

    // ── Substitutions ──────────────────────────────────────────────

    public function saveSubstitution(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->getItemOrFail((int)$id);

        $subId = (int)($_POST['substitution_id'] ?? 0);
        $substituteItemId = (int)($_POST['substitute_item_id'] ?? 0);
        $priority = max(1, (int)($_POST['priority'] ?? 1));
        $notes = trim($_POST['notes'] ?? '');

        if (!$substituteItemId) {
            $this->toast('Substitute item is required.', 'error');
            $this->redirect("/items/{$id}#substitutions");
            return;
        }

        if ($substituteItemId === (int)$id) {
            $this->toast('An item cannot substitute itself.', 'error');
            $this->redirect("/items/{$id}#substitutions");
            return;
        }

        if ($subId) {
            $this->db()->prepare("UPDATE item_substitutions SET substitute_item_id = ?, priority = ?, notes = ?, updated_at = NOW() WHERE id = ? AND item_id = ?")
                ->execute([$substituteItemId, $priority, $notes ?: null, $subId, (int)$id]);
            $this->auditLog('UPDATE', 'item_substitutions', $subId, [], ['substitute_item_id' => $substituteItemId]);
        } else {
            $this->db()->prepare("INSERT INTO item_substitutions (item_id, substitute_item_id, priority, notes) VALUES (?, ?, ?, ?)")
                ->execute([(int)$id, $substituteItemId, $priority, $notes ?: null]);
            $this->auditLog('CREATE', 'item_substitutions', (int)$this->db()->lastInsertId(), [], ['substitute_item_id' => $substituteItemId]);
        }

        $this->toast('Substitution saved.', 'success');
        $this->redirect("/items/{$id}#substitutions");
    }

    public function deactivateSubstitution(string $id, string $subId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $this->db()->prepare("UPDATE item_substitutions SET active = 0 WHERE id = ? AND item_id = ?")->execute([(int)$subId, (int)$id]);
        $this->auditLog('UPDATE', 'item_substitutions', (int)$subId, ['active' => 1], ['active' => 0]);
        $this->toast('Substitution deactivated.', 'success');
        $this->redirect("/items/{$id}#substitutions");
    }

    // ── Location ───────────────────────────────────────────────────

    public function saveLocation(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $location = trim($_POST['location'] ?? '');

        if (!$facilityId) {
            $this->toast('Facility is required.', 'error');
            $this->redirect("/items/{$id}#locations");
            return;
        }

        $this->db()->prepare("
            INSERT INTO item_facility_locations (item_id, facility_id, location, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE location = VALUES(location), updated_at = NOW()
        ")->execute([(int)$id, $facilityId, $location]);

        $this->auditLog('UPDATE', 'item_facility_locations', (int)$id, [], ['facility_id' => $facilityId, 'location' => $location]);
        $this->toast('Location saved.', 'success');
        $this->redirect("/items/{$id}#locations");
    }

    /**
     * JSON list of customers for lazy-loading into alias dropdown.
     */
    public function customersList(): void
    {
        $rows = $this->db()->query("SELECT id, company_name FROM customers WHERE active = 1 AND deleted_at IS NULL ORDER BY company_name")->fetchAll();
        $this->jsonResponse($rows);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function getItemOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("
            SELECT i.*, u.abbreviation as uom_abbr, u.name as uom_name
            FROM items i
            LEFT JOIN uom u ON i.uom_id = u.id
            WHERE i.id = ? AND i.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            http_response_code(404);
            echo 'Item not found.';
            exit;
        }
        return $item;
    }

    private function extractItemData(): array
    {
        return [
            'item_code' => strtoupper(trim($_POST['item_code'] ?? '')),
            'description' => trim($_POST['description'] ?? ''),
            'item_type' => $_POST['item_type'] ?? 'RAW_MATERIAL',
            'gl_group' => $_POST['gl_group'] ?? 'RAW_MATERIAL',
            'uom_id' => (int)($_POST['uom_id'] ?? 0),
            'unit_cost' => (float)($_POST['unit_cost'] ?? 0),
            'sale_price' => (float)($_POST['sale_price'] ?? 0),
            'reorder_min' => $_POST['reorder_min'] !== '' ? (float)$_POST['reorder_min'] : null,
            'reorder_max' => $_POST['reorder_max'] !== '' ? (float)$_POST['reorder_max'] : null,
            'shelf_life_days' => $_POST['shelf_life_days'] !== '' ? (int)$_POST['shelf_life_days'] : null,
            'requires_inspection' => isset($_POST['requires_inspection']) ? 1 : 0,
            'sds_on_file' => isset($_POST['sds_on_file']) ? 1 : 0,
            'sds_last_received' => $_POST['sds_last_received'] ?? null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
        ];
    }

    private function validateItem(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if (empty($data['item_code'])) {
            $errors[] = 'Item code is required.';
        } else {
            $checkStmt = $this->db()->prepare("SELECT id FROM items WHERE item_code = ? AND deleted_at IS NULL" . ($excludeId ? " AND id != ?" : ""));
            $params = [$data['item_code']];
            if ($excludeId) $params[] = $excludeId;
            $checkStmt->execute($params);
            if ($checkStmt->fetch()) {
                $errors[] = 'Item code already exists.';
            }
        }
        if (empty($data['description'])) $errors[] = 'Description is required.';
        if (!$data['uom_id']) $errors[] = 'Unit of measure is required.';

        $validTypes = ['RAW_MATERIAL', 'FINISHED_GOOD', 'INTERMEDIATE', 'RESALE', 'SERVICE'];
        if (!in_array($data['item_type'], $validTypes)) $errors[] = 'Invalid item type.';
        if (!in_array($data['gl_group'], $validTypes)) $errors[] = 'Invalid GL group.';

        if ($data['reorder_min'] !== null && $data['reorder_max'] !== null && $data['reorder_max'] < $data['reorder_min']) {
            $errors[] = 'Reorder max should not be less than reorder min.';
        }

        return $errors;
    }
}
