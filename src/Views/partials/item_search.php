<!-- Item Search Typeahead — include on any form that needs item selection -->
<!-- Expects: $fieldName (hidden input name), $fieldLabel, optional $fieldValue, $fieldDisplay -->
<?php
$fieldName = $fieldName ?? 'item_id';
$fieldLabel = $fieldLabel ?? 'Item';
$fieldValue = $fieldValue ?? '';
$fieldDisplay = $fieldDisplay ?? '';
$fieldId = 'itemSearch_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fieldName);
?>
<div style="position:relative;">
    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;"><?= htmlspecialchars($fieldLabel) ?></label>
    <input type="text" id="<?= $fieldId ?>_input"
           value="<?= htmlspecialchars($fieldDisplay) ?>"
           placeholder="Search by code or description..."
           class="form-input" style="width:100%;" autocomplete="off">
    <input type="hidden" name="<?= htmlspecialchars($fieldName) ?>" id="<?= $fieldId ?>_value" value="<?= htmlspecialchars($fieldValue) ?>">
    <div id="<?= $fieldId ?>_suggestions" style="position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none;"></div>
</div>
<script>
(function() {
    var input = document.getElementById('<?= $fieldId ?>_input');
    var hidden = document.getElementById('<?= $fieldId ?>_value');
    var suggestions = document.getElementById('<?= $fieldId ?>_suggestions');
    var timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { suggestions.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('/items/search?q=' + encodeURIComponent(q) + '&limit=10')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    suggestions.innerHTML = '';
                    data.forEach(function(item) {
                        var div = document.createElement('div');
                        div.textContent = item.display;
                        div.style.cssText = 'padding:6px 10px; font-size:13px; cursor:pointer;';
                        div.addEventListener('mouseenter', function() { this.style.background = '#eff6ff'; });
                        div.addEventListener('mouseleave', function() { this.style.background = ''; });
                        div.addEventListener('click', function() {
                            input.value = item.display;
                            hidden.value = item.id;
                            suggestions.style.display = 'none';
                        });
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = data.length ? 'block' : 'none';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
})();
</script>
