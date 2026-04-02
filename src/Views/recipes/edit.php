<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'edit' ? 'Edit' : 'New' ?> Recipe — <?= htmlspecialchars($item['item_code']) ?> — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .step-row { display:flex; gap:8px; align-items:flex-start; padding:10px 12px; background:#f9fafb; border-radius:6px; margin-bottom:6px; border-left:4px solid #d1d5db; position:relative; }
        .step-row.type-ingredient { border-left-color:#16a34a; }
        .step-row.type-instruction { border-left-color:#2563eb; }
        .step-handle { cursor:grab; color:#9ca3af; font-size:18px; line-height:1; user-select:none; padding:4px 2px; }
        .step-handle:active { cursor:grabbing; }
        .step-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; text-transform:uppercase; letter-spacing:0.5px; }
        .step-badge-ing { background:#dcfce7; color:#166534; }
        .step-badge-ins { background:#dbeafe; color:#1e40af; }
        .step-fields { flex:1; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .step-actions { display:flex; gap:4px; flex-shrink:0; align-items:flex-start; }
        .weight-pct { font-size:12px; color:#6b7280; min-width:55px; text-align:right; }
        .item-search-wrap { position:relative; flex:2; min-width:200px; }
        .item-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 6px rgba(0,0,0,0.1); }
        .item-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .item-suggestions div:hover { background:#eff6ff; }
        .formula-summary { padding:12px 16px; border-radius:6px; margin-top:12px; font-size:14px; font-weight:500; }
        .formula-ok { background:#dcfce7; color:#166534; }
        .formula-warn { background:#fef9c3; color:#854d0e; }
        .step-notes-toggle { font-size:11px; color:#6b7280; cursor:pointer; text-decoration:underline; }
        .step-notes-area { margin-top:6px; width:100%; }
        .dragging { opacity:0.4; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?>
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div style="max-width:960px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast">
                <?= htmlspecialchars($_SESSION['toast']['message']) ?>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">
                <?php if ($mode === 'edit'): ?>
                    Edit Recipe: <?= htmlspecialchars($recipe['version_name']) ?> (v<?= (int)$recipe['version_number'] ?>)
                <?php else: ?>
                    New Recipe Version
                <?php endif; ?>
                <span style="font-size:14px; font-weight:400; color:#6b7280;"> — <?= htmlspecialchars($item['item_code']) ?></span>
            </h1>
            <a href="/items/<?= $item['id'] ?>/recipes" class="btn btn-secondary">&larr; Back to Recipes</a>
        </div>

        <form method="POST" action="<?= $mode === 'edit' ? "/items/{$item['id']}/recipes/{$recipe['id']}/edit" : "/items/{$item['id']}/recipes/create" ?>" id="recipeForm">
            <!-- Header fields -->
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Version Name <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="version_name" value="<?= htmlspecialchars($recipe['version_name'] ?? '') ?>"
                               required class="form-input" style="width:100%;" placeholder="e.g. Standard Formula, Summer Blend">
                    </div>
                    <div>
                        <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">
                            Yield Percentage
                        </label>
                        <div style="display:flex; align-items:center; gap:4px;">
                            <input type="number" step="0.01" name="yield_percentage"
                                   value="<?= htmlspecialchars($recipe['yield_percentage'] ?? '100.00') ?>"
                                   class="form-input" style="width:100px;">
                            <span style="color:#6b7280;">%</span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea name="notes" rows="2" class="form-input" style="width:100%;"><?= htmlspecialchars($recipe['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Step Editor -->
            <h3 style="margin-bottom:8px;">Formula Steps</h3>
            <div id="steps-container">
                <?php if (!empty($steps)): ?>
                    <?php foreach ($steps as $idx => $step): ?>
                    <div class="step-row type-<?= strtolower($step['step_type'] === 'INGREDIENT' ? 'ingredient' : 'instruction') ?>"
                         data-index="<?= $idx ?>" draggable="true">
                        <span class="step-handle" title="Drag to reorder">&#10495;</span>
                        <input type="hidden" name="steps[<?= $idx ?>][step_type]" value="<?= $step['step_type'] ?>">
                        <?php if ($step['step_type'] === 'INGREDIENT'): ?>
                            <span class="step-badge step-badge-ing">ING</span>
                            <div class="step-fields">
                                <div class="item-search-wrap">
                                    <input type="hidden" name="steps[<?= $idx ?>][item_id]" value="<?= $step['item_id'] ?>" class="step-item-id">
                                    <input type="text" class="form-input step-item-search" placeholder="Search items..."
                                           value="<?= htmlspecialchars(($step['item_code'] ?? '') . ($step['item_code'] ? ' — ' : '') . ($step['item_description'] ?? '')) ?>"
                                           autocomplete="off" style="width:100%; font-size:13px; padding:4px 8px;">
                                    <div class="item-suggestions"></div>
                                </div>
                                <div style="width:100px;">
                                    <input type="number" step="0.0001" min="0" name="steps[<?= $idx ?>][quantity]"
                                           value="<?= $step['quantity'] ?>" class="form-input step-qty"
                                           placeholder="Qty" style="width:100%; font-size:13px; padding:4px 8px;">
                                </div>
                                <div style="width:100px;">
                                    <select name="steps[<?= $idx ?>][uom_id]" class="form-input" style="width:100%; font-size:13px; padding:4px 8px;">
                                        <?php foreach ($uoms as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($step['uom_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['abbreviation']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="weight-pct" data-pct>—%</span>
                            </div>
                        <?php else: ?>
                            <span class="step-badge step-badge-ins">INS</span>
                            <div class="step-fields">
                                <textarea name="steps[<?= $idx ?>][instruction_text]" rows="2" class="form-input"
                                          placeholder="Instruction text..." style="flex:1; font-size:13px; padding:4px 8px;"><?= htmlspecialchars($step['instruction_text'] ?? '') ?></textarea>
                            </div>
                        <?php endif; ?>
                        <div class="step-actions">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, -1)" title="Move up">&uarr;</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, 1)" title="Move down">&darr;</button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="removeStep(this)" title="Remove">&times;</button>
                        </div>
                        <?php if (!empty($step['notes'])): ?>
                        <div class="step-notes-area" style="width:100%; margin-top:4px; margin-left:30px;">
                            <input type="text" name="steps[<?= $idx ?>][notes]" value="<?= htmlspecialchars($step['notes'] ?? '') ?>"
                                   class="form-input" placeholder="Step notes..." style="width:100%; font-size:12px; padding:2px 6px;">
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="steps[<?= $idx ?>][notes]" value="">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Formula summary -->
            <div id="formula-summary" class="formula-summary formula-ok" style="display:none;"></div>

            <div style="display:flex; gap:8px; margin-top:12px;">
                <button type="button" class="btn btn-secondary" onclick="addIngredient()">+ Add Ingredient</button>
                <button type="button" class="btn btn-secondary" onclick="addInstruction()">+ Add Instruction</button>
            </div>

            <div style="display:flex; gap:8px; margin-top:24px;">
                <button type="submit" class="btn btn-primary"><?= $mode === 'edit' ? 'Save Changes' : 'Create Recipe' ?></button>
                <a href="/items/<?= $item['id'] ?>/recipes" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    var stepIndex = <?= !empty($steps) ? count($steps) : 0 ?>;
    var uomOptions = '<?php
        $opts = '';
        foreach ($uoms as $u) {
            $opts .= '<option value="' . $u['id'] . '">' . htmlspecialchars($u['abbreviation'], ENT_QUOTES) . '</option>';
        }
        echo addslashes($opts);
    ?>';

    // Toast
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function() { toast.style.display = 'none'; }, 4000);

    // ── Add Steps ──────────────────────────────────────────────

    function addIngredient() {
        var container = document.getElementById('steps-container');
        var row = document.createElement('div');
        row.className = 'step-row type-ingredient';
        row.dataset.index = stepIndex;
        row.draggable = true;
        row.innerHTML =
            '<span class="step-handle" title="Drag to reorder">&#10495;</span>' +
            '<input type="hidden" name="steps[' + stepIndex + '][step_type]" value="INGREDIENT">' +
            '<span class="step-badge step-badge-ing">ING</span>' +
            '<div class="step-fields">' +
                '<div class="item-search-wrap">' +
                    '<input type="hidden" name="steps[' + stepIndex + '][item_id]" value="" class="step-item-id">' +
                    '<input type="text" class="form-input step-item-search" placeholder="Search items..." autocomplete="off" style="width:100%; font-size:13px; padding:4px 8px;">' +
                    '<div class="item-suggestions"></div>' +
                '</div>' +
                '<div style="width:100px;">' +
                    '<input type="number" step="0.0001" min="0" name="steps[' + stepIndex + '][quantity]" class="form-input step-qty" placeholder="Qty" style="width:100%; font-size:13px; padding:4px 8px;">' +
                '</div>' +
                '<div style="width:100px;">' +
                    '<select name="steps[' + stepIndex + '][uom_id]" class="form-input" style="width:100%; font-size:13px; padding:4px 8px;">' + uomOptions + '</select>' +
                '</div>' +
                '<span class="weight-pct" data-pct>—%</span>' +
            '</div>' +
            '<div class="step-actions">' +
                '<button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, -1)" title="Move up">&uarr;</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, 1)" title="Move down">&darr;</button>' +
                '<button type="button" class="btn btn-sm btn-warning" onclick="removeStep(this)" title="Remove">&times;</button>' +
            '</div>' +
            '<input type="hidden" name="steps[' + stepIndex + '][notes]" value="">';
        container.appendChild(row);
        bindItemSearch(row.querySelector('.step-item-search'));
        bindQtyChange(row.querySelector('.step-qty'));
        bindDrag(row);
        stepIndex++;
        updateFormulaSummary();
    }

    function addInstruction() {
        var container = document.getElementById('steps-container');
        var row = document.createElement('div');
        row.className = 'step-row type-instruction';
        row.dataset.index = stepIndex;
        row.draggable = true;
        row.innerHTML =
            '<span class="step-handle" title="Drag to reorder">&#10495;</span>' +
            '<input type="hidden" name="steps[' + stepIndex + '][step_type]" value="INSTRUCTION">' +
            '<span class="step-badge step-badge-ins">INS</span>' +
            '<div class="step-fields">' +
                '<textarea name="steps[' + stepIndex + '][instruction_text]" rows="2" class="form-input" placeholder="Instruction text..." style="flex:1; font-size:13px; padding:4px 8px;"></textarea>' +
            '</div>' +
            '<div class="step-actions">' +
                '<button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, -1)" title="Move up">&uarr;</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" onclick="moveStep(this, 1)" title="Move down">&darr;</button>' +
                '<button type="button" class="btn btn-sm btn-warning" onclick="removeStep(this)" title="Remove">&times;</button>' +
            '</div>' +
            '<input type="hidden" name="steps[' + stepIndex + '][notes]" value="">';
        container.appendChild(row);
        bindDrag(row);
        stepIndex++;
    }

    function removeStep(btn) {
        btn.closest('.step-row').remove();
        reindexSteps();
        updateFormulaSummary();
    }

    function moveStep(btn, direction) {
        var row = btn.closest('.step-row');
        var container = document.getElementById('steps-container');
        var rows = Array.from(container.children);
        var idx = rows.indexOf(row);
        var target = idx + direction;
        if (target < 0 || target >= rows.length) return;
        if (direction === -1) {
            container.insertBefore(row, rows[target]);
        } else {
            container.insertBefore(row, rows[target].nextSibling);
        }
        reindexSteps();
    }

    // ── Re-index step names after reorder/remove ──────────────

    function reindexSteps() {
        var rows = document.querySelectorAll('#steps-container .step-row');
        rows.forEach(function(row, i) {
            row.dataset.index = i;
            row.querySelectorAll('[name]').forEach(function(el) {
                el.name = el.name.replace(/steps\[\d+\]/, 'steps[' + i + ']');
            });
        });
        stepIndex = rows.length;
    }

    // ── Formula Calculation ───────────────────────────────────

    function updateFormulaSummary() {
        var qtyInputs = document.querySelectorAll('.step-qty');
        var total = 0;
        var pctSpans = document.querySelectorAll('[data-pct]');

        qtyInputs.forEach(function(inp) {
            total += parseFloat(inp.value) || 0;
        });

        pctSpans.forEach(function(span) {
            var row = span.closest('.step-row');
            var qty = parseFloat(row.querySelector('.step-qty').value) || 0;
            if (total > 0) {
                span.textContent = (qty / total * 100).toFixed(2) + '%';
            } else {
                span.textContent = '—%';
            }
        });

        var summary = document.getElementById('formula-summary');
        if (qtyInputs.length === 0) {
            summary.style.display = 'none';
            return;
        }
        summary.style.display = 'block';
        // The total of weight percentages always equals 100% when total > 0
        if (total > 0) {
            summary.className = 'formula-summary formula-ok';
            summary.textContent = 'Formula total: 100.00% — ' + total.toFixed(4) + ' total quantity across ' + qtyInputs.length + ' ingredients';
        } else {
            summary.className = 'formula-summary formula-warn';
            summary.textContent = 'No ingredient quantities entered.';
        }
    }

    function bindQtyChange(input) {
        input.addEventListener('input', updateFormulaSummary);
    }

    // ── Item Search ──────────────────────────────────────────

    function bindItemSearch(input) {
        var timer;
        input.addEventListener('input', function() {
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function() {
                var q = self.value.trim();
                var sugBox = self.parentNode.querySelector('.item-suggestions');
                if (q.length < 1) { sugBox.style.display = 'none'; return; }
                fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        sugBox.innerHTML = '';
                        items.forEach(function(item) {
                            var d = document.createElement('div');
                            d.textContent = item.display;
                            d.addEventListener('click', function() {
                                self.value = item.item_code + ' — ' + item.description;
                                self.parentNode.querySelector('.step-item-id').value = item.id;
                                sugBox.style.display = 'none';
                            });
                            sugBox.appendChild(d);
                        });
                        sugBox.style.display = items.length ? 'block' : 'none';
                    });
            }, 300);
        });
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target)) {
                var sugBox = input.parentNode.querySelector('.item-suggestions');
                if (sugBox) sugBox.style.display = 'none';
            }
        });
    }

    // ── Drag and Drop ────────────────────────────────────────

    var dragSrc = null;

    function bindDrag(row) {
        row.addEventListener('dragstart', function(e) {
            dragSrc = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            dragSrc = null;
        });
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            if (dragSrc !== this) {
                var container = document.getElementById('steps-container');
                var rows = Array.from(container.children);
                var srcIdx = rows.indexOf(dragSrc);
                var tgtIdx = rows.indexOf(this);
                if (srcIdx < tgtIdx) {
                    container.insertBefore(dragSrc, this.nextSibling);
                } else {
                    container.insertBefore(dragSrc, this);
                }
                reindexSteps();
                updateFormulaSummary();
            }
        });
    }

    // ── Init ─────────────────────────────────────────────────

    (function() {
        document.querySelectorAll('.step-item-search').forEach(bindItemSearch);
        document.querySelectorAll('.step-qty').forEach(bindQtyChange);
        document.querySelectorAll('.step-row').forEach(bindDrag);
        updateFormulaSummary();

        // Re-index on form submit to ensure clean arrays
        document.getElementById('recipeForm').addEventListener('submit', function() {
            reindexSteps();
        });
    })();
    </script>
</body>
</html>
