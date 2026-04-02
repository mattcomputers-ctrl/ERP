<?php

namespace PrecisionInk\Controllers;

class RecipeController extends BaseController
{
    /**
     * List recipe versions for an item.
     */
    public function index(string $itemId): void
    {
        if (!$this->checkPermission('items', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $recipes = $this->getVersionsForItem((int)$itemId);

        $this->renderView('recipes/list', [
            'item' => $item,
            'recipes' => $recipes,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(string $itemId): void
    {
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();

        $this->renderView('recipes/edit', [
            'mode' => 'create',
            'item' => $item,
            'recipe' => null,
            'steps' => [],
            'uoms' => $uoms,
        ]);
    }

    /**
     * Store new recipe version.
     */
    public function store(string $itemId): void
    {
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $data = $this->extractRecipeData();
        $errors = $this->validateRecipe($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
            $this->renderView('recipes/edit', [
                'mode' => 'create',
                'item' => $item,
                'recipe' => $data,
                'steps' => $data['steps'] ?? [],
                'uoms' => $uoms,
            ]);
            return;
        }

        $this->db()->beginTransaction();
        try {
            // Get next version number
            $maxStmt = $this->db()->prepare("SELECT COALESCE(MAX(version_number), 0) FROM recipe_versions WHERE item_id = ?");
            $maxStmt->execute([(int)$itemId]);
            $nextVersion = (int)$maxStmt->fetchColumn() + 1;

            // Check if this is the first version — make it default
            $countStmt = $this->db()->prepare("SELECT COUNT(*) FROM recipe_versions WHERE item_id = ?");
            $countStmt->execute([(int)$itemId]);
            $isFirst = (int)$countStmt->fetchColumn() === 0;

            $versionName = $this->generateVersionName((int)$itemId);

            $stmt = $this->db()->prepare("
                INSERT INTO recipe_versions (item_id, version_number, version_name, yield_percentage, notes, is_default, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$itemId, $nextVersion, $versionName,
                $data['yield_percentage'], $data['notes'] ?: null,
                $isFirst ? 1 : 0, $this->currentUserId(),
            ]);
            $versionId = (int)$this->db()->lastInsertId();

            $this->saveSteps($versionId, $data['steps'] ?? []);
            $this->db()->commit();

            $this->auditCreate('recipe_versions', $versionId, [
                'item_id' => (int)$itemId,
                'version_number' => $nextVersion,
                'version_name' => $data['version_name'],
            ]);
            $this->toast('Recipe version created.', 'success');
            $this->redirect("/items/{$itemId}/recipes/{$versionId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error creating recipe: ' . $e->getMessage(), 'error');
            $this->redirect("/items/{$itemId}/recipes/create");
        }
    }

    /**
     * View recipe version (read-only).
     */
    public function view(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'view')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);
        $steps = $this->getSteps((int)$versionId);

        $this->renderView('recipes/view', [
            'item' => $item,
            'recipe' => $recipe,
            'steps' => $steps,
        ]);
    }

    /**
     * Show edit form.
     */
    public function editForm(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);

        if (!$recipe['is_active']) {
            $this->toast('Cannot edit a deactivated recipe version.', 'error');
            $this->redirect("/items/{$itemId}/recipes/{$versionId}");
            return;
        }

        $steps = $this->getSteps((int)$versionId);
        $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();

        $this->renderView('recipes/edit', [
            'mode' => 'edit',
            'item' => $item,
            'recipe' => $recipe,
            'steps' => $steps,
            'uoms' => $uoms,
        ]);
    }

    /**
     * Save edits to recipe version.
     */
    public function update(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);
        $data = $this->extractRecipeData();
        $errors = $this->validateRecipe($data);

        if (!empty($errors)) {
            $this->toast(implode(' ', $errors), 'error');
            $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();
            $this->renderView('recipes/edit', [
                'mode' => 'edit',
                'item' => $item,
                'recipe' => array_merge($recipe, $data),
                'steps' => $data['steps'] ?? [],
                'uoms' => $uoms,
            ]);
            return;
        }

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("
                UPDATE recipe_versions SET version_name = ?, yield_percentage = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$data['version_name'], $data['yield_percentage'], $data['notes'] ?: null, (int)$versionId]);

            // Delete old steps and re-insert
            $this->db()->prepare("DELETE FROM recipe_steps WHERE recipe_version_id = ?")->execute([(int)$versionId]);
            $this->saveSteps((int)$versionId, $data['steps'] ?? []);

            $this->db()->commit();

            $this->auditUpdate('recipe_versions', (int)$versionId, $recipe, $data);
            $this->toast('Recipe version updated.', 'success');
            $this->redirect("/items/{$itemId}/recipes/{$versionId}");
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error updating recipe: ' . $e->getMessage(), 'error');
            $this->redirect("/items/{$itemId}/recipes/{$versionId}/edit");
        }
    }

    /**
     * Activate a recipe version.
     */
    public function activate(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);

        $this->db()->prepare("UPDATE recipe_versions SET is_active = 1, deactivated_by = NULL, deactivated_at = NULL, updated_at = NOW() WHERE id = ?")
            ->execute([(int)$versionId]);

        $this->auditLog('UPDATE', 'recipe_versions', (int)$versionId, ['is_active' => 0], ['is_active' => 1]);
        $this->toast('Recipe version reactivated.', 'success');
        $this->redirect("/items/{$itemId}/recipes/{$versionId}");
    }

    /**
     * Deactivate a recipe version.
     */
    public function deactivate(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);

        // Cannot deactivate the only active default version
        if ($recipe['is_default']) {
            $activeCount = $this->db()->prepare("SELECT COUNT(*) FROM recipe_versions WHERE item_id = ? AND is_active = 1");
            $activeCount->execute([(int)$itemId]);
            if ((int)$activeCount->fetchColumn() <= 1) {
                $this->toast('Cannot deactivate the only active default version.', 'error');
                $this->redirect("/items/{$itemId}/recipes/{$versionId}");
                return;
            }
            // Unset default since we're deactivating
            $this->db()->prepare("UPDATE recipe_versions SET is_default = 0 WHERE id = ?")->execute([(int)$versionId]);
        }

        $this->db()->prepare("UPDATE recipe_versions SET is_active = 0, deactivated_by = ?, deactivated_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$this->currentUserId(), (int)$versionId]);

        $this->auditLog('UPDATE', 'recipe_versions', (int)$versionId, ['is_active' => 1], ['is_active' => 0]);
        $this->toast('Recipe version deactivated.', 'success');
        $this->redirect("/items/{$itemId}/recipes/{$versionId}");
    }

    /**
     * Set a version as the default.
     */
    public function setDefault(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'edit')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);

        if (!$recipe['is_active']) {
            $this->toast('Only active versions can be set as default.', 'error');
            $this->redirect("/items/{$itemId}/recipes/{$versionId}");
            return;
        }

        $this->db()->beginTransaction();
        try {
            $this->db()->prepare("UPDATE recipe_versions SET is_default = 0 WHERE item_id = ?")->execute([(int)$itemId]);
            $this->db()->prepare("UPDATE recipe_versions SET is_default = 1, updated_at = NOW() WHERE id = ?")->execute([(int)$versionId]);
            $this->db()->commit();

            $this->auditLog('UPDATE', 'recipe_versions', (int)$versionId, ['is_default' => 0], ['is_default' => 1]);
            $this->toast('Default version updated.', 'success');
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->toast('Error: ' . $e->getMessage(), 'error');
        }

        $this->redirect("/items/{$itemId}/recipes/{$versionId}");
    }

    /**
     * Clone a recipe version.
     */
    /**
     * Clone: opens create form pre-populated (does NOT auto-save).
     * Changed from POST to GET — renders unsaved form.
     */
    public function cloneVersion(string $itemId, string $versionId): void
    {
        if (!$this->checkPermission('items', 'create')) {
            http_response_code(403);
            echo 'Access Denied';
            exit;
        }

        $item = $this->getItemOrFail((int)$itemId);
        $recipe = $this->getVersionOrFail((int)$versionId, (int)$itemId);
        $steps = $this->getSteps((int)$versionId);
        $uoms = $this->db()->query("SELECT id, abbreviation, name FROM uom WHERE active = 1 ORDER BY abbreviation")->fetchAll();

        // Pre-fill recipe data from clone source
        $cloneData = [
            'version_name' => '', // Will be auto-generated on save
            'yield_percentage' => $recipe['yield_percentage'],
            'notes' => $recipe['notes'],
        ];

        $this->renderView('recipes/edit', [
            'mode' => 'create',
            'item' => $item,
            'recipe' => $cloneData,
            'steps' => $steps,
            'uoms' => $uoms,
            'cloneNotice' => true,
        ]);
    }

    /**
     * SDS API endpoint — returns active recipe versions for an item.
     */
    public function sdsApi(string $itemId): void
    {
        // API key authentication
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (!$apiKey) {
            $this->jsonResponse(['error' => 'Missing X-API-Key header.'], 401);
            return;
        }

        $keyStmt = $this->db()->prepare("SELECT id FROM api_keys WHERE key_value = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $keyStmt->execute([$apiKey]);
        if (!$keyStmt->fetch()) {
            $this->jsonResponse(['error' => 'Invalid or expired API key.'], 403);
            return;
        }

        $itemStmt = $this->db()->prepare("SELECT id, item_code, description FROM items WHERE id = ? AND deleted_at IS NULL");
        $itemStmt->execute([(int)$itemId]);
        $item = $itemStmt->fetch();
        if (!$item) {
            $this->jsonResponse(['error' => 'Item not found.'], 404);
            return;
        }

        $versionStmt = $this->db()->prepare("SELECT * FROM recipe_versions WHERE item_id = ? AND is_active = 1 ORDER BY version_number");
        $versionStmt->execute([(int)$itemId]);
        $versions = $versionStmt->fetchAll();

        $result = [
            'item_id' => (int)$item['id'],
            'item_code' => $item['item_code'],
            'description' => $item['description'],
            'versions' => [],
        ];

        foreach ($versions as $v) {
            $stepsStmt = $this->db()->prepare("
                SELECT rs.item_id, i.item_code, i.description, rs.quantity,
                       u.abbreviation as uom
                FROM recipe_steps rs
                LEFT JOIN items i ON rs.item_id = i.id
                LEFT JOIN uom u ON rs.uom_id = u.id
                WHERE rs.recipe_version_id = ? AND rs.step_type = 'INGREDIENT'
                ORDER BY rs.sequence
            ");
            $stepsStmt->execute([(int)$v['id']]);
            $ingredients = $stepsStmt->fetchAll();

            // Calculate weight percentages
            $totalQty = array_sum(array_column($ingredients, 'quantity'));

            $versionData = [
                'version_id' => (int)$v['id'],
                'version_number' => (int)$v['version_number'],
                'version_name' => $v['version_name'],
                'is_default' => (bool)$v['is_default'],
                'yield_percentage' => (float)$v['yield_percentage'],
                'ingredients' => [],
            ];

            foreach ($ingredients as $ing) {
                $versionData['ingredients'][] = [
                    'item_id' => (int)$ing['item_id'],
                    'item_code' => $ing['item_code'],
                    'description' => $ing['description'],
                    'quantity' => (float)$ing['quantity'],
                    'uom' => $ing['uom'],
                    'weight_percentage' => $totalQty > 0 ? round((float)$ing['quantity'] / $totalQty * 100, 4) : 0,
                ];
            }

            $result['versions'][] = $versionData;
        }

        $this->jsonResponse($result);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function getItemOrFail(int $id): array
    {
        $stmt = $this->db()->prepare("SELECT i.*, u.abbreviation as uom_abbr FROM items i LEFT JOIN uom u ON i.uom_id = u.id WHERE i.id = ? AND i.deleted_at IS NULL");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            http_response_code(404);
            echo 'Item not found.';
            exit;
        }
        return $item;
    }

    private function getVersionOrFail(int $versionId, int $itemId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rv.*, u.full_name as created_by_name, du.full_name as deactivated_by_name
            FROM recipe_versions rv
            LEFT JOIN users u ON rv.created_by = u.id
            LEFT JOIN users du ON rv.deactivated_by = du.id
            WHERE rv.id = ? AND rv.item_id = ?
        ");
        $stmt->execute([$versionId, $itemId]);
        $recipe = $stmt->fetch();
        if (!$recipe) {
            http_response_code(404);
            echo 'Recipe version not found.';
            exit;
        }
        return $recipe;
    }

    private function getVersionsForItem(int $itemId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rv.*, u.full_name as created_by_name
            FROM recipe_versions rv
            LEFT JOIN users u ON rv.created_by = u.id
            WHERE rv.item_id = ?
            ORDER BY rv.version_number DESC
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    private function getSteps(int $versionId): array
    {
        $stmt = $this->db()->prepare("
            SELECT rs.*, i.item_code, i.description as item_description, u.abbreviation as uom_abbr
            FROM recipe_steps rs
            LEFT JOIN items i ON rs.item_id = i.id
            LEFT JOIN uom u ON rs.uom_id = u.id
            WHERE rs.recipe_version_id = ?
            ORDER BY rs.sequence ASC, rs.id ASC
        ");
        $stmt->execute([$versionId]);
        return $stmt->fetchAll();
    }

    /**
     * JSON API: return recipe versions for an item (for batch ticket dropdown).
     */
    public function recipeVersionsJson(string $itemId): void
    {
        $stmt = $this->db()->prepare("SELECT id, version_name, version_number, is_default, is_active FROM recipe_versions WHERE item_id = ? AND is_active = 1 ORDER BY version_number DESC");
        $stmt->execute([(int)$itemId]);
        $this->jsonResponse($stmt->fetchAll());
    }

    private function generateVersionName(int $itemId): string
    {
        $stmt = $this->db()->prepare('SELECT item_code FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $itemCode = $stmt->fetchColumn() ?: 'ITEM';

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM recipe_versions WHERE item_id = ?');
        $stmt->execute([$itemId]);
        $count = (int)$stmt->fetchColumn();

        return $itemCode . '.' . str_pad($count + 1, 2, '0', STR_PAD_LEFT);
    }

    private function extractRecipeData(): array
    {
        $steps = [];
        $rawSteps = $_POST['steps'] ?? [];
        foreach ($rawSteps as $s) {
            $type = $s['step_type'] ?? 'INGREDIENT';
            $percentage = ($type === 'INGREDIENT' && ($s['percentage'] ?? '') !== '') ? (float)$s['percentage'] : null;
            $steps[] = [
                'step_type' => $type,
                'item_id' => ($type === 'INGREDIENT' && !empty($s['item_id'])) ? (int)$s['item_id'] : null,
                'percentage' => $percentage,
                'quantity' => $percentage, // quantity = percentage for backward compat
                'uom_id' => ($type === 'INGREDIENT' && !empty($s['uom_id'])) ? (int)$s['uom_id'] : null,
                'instruction_text' => ($type === 'INSTRUCTION') ? trim($s['instruction_text'] ?? '') : null,
                'notes' => trim($s['notes'] ?? '') ?: null,
            ];
        }
        return [
            'version_name' => trim($_POST['version_name'] ?? ''),
            'yield_percentage' => (float)($_POST['yield_percentage'] ?? 100),
            'notes' => trim($_POST['notes'] ?? ''),
            'steps' => $steps,
        ];
    }

    private function validateRecipe(array $data): array
    {
        $errors = [];
        if ($data['yield_percentage'] <= 0 || $data['yield_percentage'] > 999) $errors[] = 'Yield percentage must be between 0 and 999.';

        $totalPct = 0;
        foreach ($data['steps'] as $i => $step) {
            $n = $i + 1;
            if ($step['step_type'] === 'INGREDIENT') {
                if (!$step['item_id']) $errors[] = "Step {$n}: Ingredient item is required.";
                if (!$step['percentage'] || $step['percentage'] <= 0) $errors[] = "Step {$n}: Percentage must be greater than 0.";
                $totalPct += (float)($step['percentage'] ?? 0);
            } elseif ($step['step_type'] === 'INSTRUCTION') {
                if (empty($step['instruction_text'])) $errors[] = "Step {$n}: Instruction text is required.";
            }
        }

        // Hard block if percentages don't sum to 100
        if ($totalPct > 0 && abs($totalPct - 100) > 0.001) {
            $errors[] = "Formula must equal exactly 100%. Current total: " . number_format($totalPct, 3) . "%";
        }

        return $errors;
    }

    private function saveSteps(int $versionId, array $steps): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO recipe_steps (recipe_version_id, step_type, sequence, item_id, quantity, percentage, uom_id, instruction_text, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($steps as $i => $step) {
            $sequence = ($i + 1) * 10;
            $stmt->execute([
                $versionId, $step['step_type'], $sequence,
                $step['item_id'], $step['quantity'], $step['percentage'] ?? null, $step['uom_id'],
                $step['instruction_text'], $step['notes'],
            ]);
        }
    }
}
