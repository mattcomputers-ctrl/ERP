<?php

namespace PrecisionInk\Controllers;

class PriceListController extends BaseController
{
    // ── List ────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->checkPermission('items', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $lists = $this->db()->query("
            SELECT pl.*,
                   (SELECT COUNT(*) FROM price_list_lines WHERE price_list_id = pl.id AND active = 1) as line_count,
                   (SELECT COUNT(*) FROM customer_price_list_assignments WHERE price_list_id = pl.id AND active = 1) as customer_count,
                   (SELECT COUNT(*) FROM supplier_price_list_assignments WHERE price_list_id = pl.id AND active = 1) as supplier_count
            FROM price_lists pl
            ORDER BY pl.list_type, pl.name
        ")->fetchAll();

        $this->renderView('price_lists/index', ['lists' => $lists]);
    }

    // ── Create ──────────────────────────────────────────────────────

    public function createForm(): void
    {
        if (!$this->checkPermission('items', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('price_lists/header_form', ['mode' => 'create', 'list' => null]);
    }

    public function store(): void
    {
        if (!$this->checkPermission('items', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        if (!$data['name'] || !$data['effective_date']) {
            $this->toast('Name and effective date are required.', 'error');
            $this->redirect('/price-lists/create');
            return;
        }

        $this->db()->prepare("
            INSERT INTO price_lists (name, list_type, default_priority, effective_date, expiration_date, notes, active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$data['name'], $data['list_type'], $data['default_priority'], $data['effective_date'],
            $data['expiration_date'], $data['notes'], $data['active']]);

        $id = (int)$this->db()->lastInsertId();
        $this->auditCreate('price_lists', $id, ['name' => $data['name']]);
        $this->toast('Price list created.', 'success');
        $this->redirect("/price-lists/{$id}");
    }

    // ── View / Lines ────────────────────────────────────────────────

    public function view(string $id): void
    {
        if (!$this->checkPermission('items', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $list = $this->getOrFail((int)$id);

        $lines = $this->db()->prepare("
            SELECT pll.*, i.item_code, i.description as item_description, pt.name as package_type_name
            FROM price_list_lines pll
            JOIN items i ON pll.item_id = i.id
            LEFT JOIN package_types pt ON pll.package_type_id = pt.id
            WHERE pll.price_list_id = ?
            ORDER BY pll.active DESC, i.item_code
        ");
        $lines->execute([(int)$id]);

        $packageTypes = $this->db()->query("SELECT id, name FROM package_types WHERE active = 1 ORDER BY name")->fetchAll();

        $this->renderView('price_lists/view', [
            'list' => $list, 'lines' => $lines->fetchAll(), 'packageTypes' => $packageTypes,
        ]);
    }

    // ── Edit Header ─────────────────────────────────────────────────

    public function editForm(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $list = $this->getOrFail((int)$id);
        $this->renderView('price_lists/header_form', ['mode' => 'edit', 'list' => $list]);
    }

    public function update(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $data = $this->extractHeader();
        if (!$data['name'] || !$data['effective_date']) {
            $this->toast('Name and effective date are required.', 'error');
            $this->redirect("/price-lists/{$id}/edit");
            return;
        }

        $this->db()->prepare("
            UPDATE price_lists SET name=?, list_type=?, default_priority=?, effective_date=?, expiration_date=?, notes=?, active=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$data['name'], $data['list_type'], $data['default_priority'], $data['effective_date'],
            $data['expiration_date'], $data['notes'], $data['active'], (int)$id]);

        $this->auditLog('UPDATE', 'price_lists', (int)$id, null, ['name' => $data['name']]);
        $this->toast('Price list updated.', 'success');
        $this->redirect("/price-lists/{$id}");
    }

    // ── Lines CRUD ──────────────────────────────────────────────────

    public function addLine(string $id): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $itemId = (int)($_POST['item_id'] ?? 0);
        if (!$itemId) { $this->toast('Item is required.', 'error'); $this->redirect("/price-lists/{$id}"); return; }

        $extCode = trim($_POST['external_code'] ?? '') ?: null;
        $pkgTypeId = (int)($_POST['package_type_id'] ?? 0) ?: null;
        $qtyPerPkg = $pkgTypeId && ($_POST['qty_per_package'] ?? '') !== '' ? (float)$_POST['qty_per_package'] : null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        $breaks = $this->extractBreaks();

        $this->db()->prepare("
            INSERT INTO price_list_lines (price_list_id, item_id, external_code, package_type_id, qty_per_package,
                break_qty_1, break_price_1, break_qty_2, break_price_2, break_qty_3, break_price_3,
                break_qty_4, break_price_4, break_qty_5, break_price_5, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            (int)$id, $itemId, $extCode, $pkgTypeId, $qtyPerPkg,
            $breaks[0][0], $breaks[0][1], $breaks[1][0], $breaks[1][1],
            $breaks[2][0], $breaks[2][1], $breaks[3][0], $breaks[3][1],
            $breaks[4][0], $breaks[4][1], $notes,
        ]);

        $this->toast('Line added.', 'success');
        $this->redirect("/price-lists/{$id}");
    }

    public function updateLine(string $id, string $lineId): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $extCode = trim($_POST['external_code'] ?? '') ?: null;
        $pkgTypeId = (int)($_POST['package_type_id'] ?? 0) ?: null;
        $qtyPerPkg = $pkgTypeId && ($_POST['qty_per_package'] ?? '') !== '' ? (float)$_POST['qty_per_package'] : null;
        $notes = trim($_POST['notes'] ?? '') ?: null;

        $breaks = $this->extractBreaks();

        $this->db()->prepare("
            UPDATE price_list_lines SET external_code=?, package_type_id=?, qty_per_package=?,
                break_qty_1=?, break_price_1=?, break_qty_2=?, break_price_2=?, break_qty_3=?, break_price_3=?,
                break_qty_4=?, break_price_4=?, break_qty_5=?, break_price_5=?, notes=?, updated_at=NOW()
            WHERE id=? AND price_list_id=?
        ")->execute([
            $extCode, $pkgTypeId, $qtyPerPkg,
            $breaks[0][0], $breaks[0][1], $breaks[1][0], $breaks[1][1],
            $breaks[2][0], $breaks[2][1], $breaks[3][0], $breaks[3][1],
            $breaks[4][0], $breaks[4][1], $notes,
            (int)$lineId, (int)$id,
        ]);

        $this->toast('Line updated.', 'success');
        $this->redirect("/price-lists/{$id}");
    }

    public function deactivateLine(string $id, string $lineId): void
    {
        if (!$this->checkPermission('items', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $this->db()->prepare("UPDATE price_list_lines SET active = 0, updated_at = NOW() WHERE id = ? AND price_list_id = ?")->execute([(int)$lineId, (int)$id]);
        $this->toast('Line deactivated.', 'success');
        $this->redirect("/price-lists/{$id}");
    }

    // ── Clone ───────────────────────────────────────────────────────

    public function cloneList(string $id): void
    {
        if (!$this->checkPermission('items', 'create')) { http_response_code(403); echo 'Access Denied'; exit; }

        $list = $this->getOrFail((int)$id);

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                INSERT INTO price_lists (name, list_type, default_priority, effective_date, expiration_date, notes, active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                'Copy of ' . $list['name'], $list['list_type'], $list['default_priority'],
                $list['effective_date'], $list['expiration_date'], $list['notes'],
            ]);
            $newId = (int)$this->db()->lastInsertId();

            $lines = $this->db()->prepare("SELECT * FROM price_list_lines WHERE price_list_id = ? AND active = 1");
            $lines->execute([(int)$id]);

            $ins = $this->db()->prepare("
                INSERT INTO price_list_lines (price_list_id, item_id, external_code, package_type_id, qty_per_package,
                    break_qty_1, break_price_1, break_qty_2, break_price_2, break_qty_3, break_price_3,
                    break_qty_4, break_price_4, break_qty_5, break_price_5, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($lines->fetchAll() as $ln) {
                $ins->execute([
                    $newId, $ln['item_id'], $ln['external_code'], $ln['package_type_id'], $ln['qty_per_package'],
                    $ln['break_qty_1'], $ln['break_price_1'], $ln['break_qty_2'], $ln['break_price_2'],
                    $ln['break_qty_3'], $ln['break_price_3'], $ln['break_qty_4'], $ln['break_price_4'],
                    $ln['break_qty_5'], $ln['break_price_5'], $ln['notes'],
                ]);
            }

            $this->db()->commit();
            $this->auditCreate('price_lists', $newId, ['cloned_from' => (int)$id]);
            $this->toast('Price list cloned.', 'success');
            $this->redirect("/price-lists/{$newId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error cloning: ' . $e->getMessage(), 'error');
            $this->redirect("/price-lists/{$id}");
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function getOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM price_lists WHERE id = ?");
        $stmt->execute([$id]);
        $list = $stmt->fetch();
        if (!$list) { http_response_code(404); echo 'Price list not found.'; exit; }
        return $list;
    }

    private function extractHeader(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'list_type' => in_array($_POST['list_type'] ?? '', ['CUSTOMER', 'SUPPLIER']) ? $_POST['list_type'] : 'CUSTOMER',
            'default_priority' => (int)($_POST['default_priority'] ?? 10),
            'effective_date' => $_POST['effective_date'] ?? date('Y-m-d'),
            'expiration_date' => ($_POST['expiration_date'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function extractBreaks(): array
    {
        $breaks = [];
        for ($i = 1; $i <= 5; $i++) {
            $qty = ($_POST["break_qty_{$i}"] ?? '') !== '' ? (float)$_POST["break_qty_{$i}"] : null;
            $price = ($_POST["break_price_{$i}"] ?? '') !== '' ? (float)$_POST["break_price_{$i}"] : null;
            $breaks[] = [$qty, $price];
        }
        return $breaks;
    }
}
