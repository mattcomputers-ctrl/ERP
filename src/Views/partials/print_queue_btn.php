<?php
/**
 * Print Queue "Add" button partial.
 * Include on: invoice view, packing slip view, BOL view, batch ticket view, COA view.
 *
 * Required variables:
 *   $pqType  — document type string (e.g. 'invoice', 'packing_slip', 'bol', 'batch_ticket', 'coa')
 *   $pqId    — record ID (int)
 *   $pqLabel — human-readable label (e.g. 'Invoice SHP-2026-0001')
 */
$pqType  = $pqType ?? '';
$pqId    = (int) ($pqId ?? 0);
$pqLabel = $pqLabel ?? ($pqType . ' #' . $pqId);
?>
<button type="button" class="btn btn-secondary btn-sm"
        onclick="addToPrintQueue('<?= htmlspecialchars($pqType, ENT_QUOTES) ?>', <?= $pqId ?>, '<?= htmlspecialchars($pqLabel, ENT_QUOTES) ?>')">
    &#128424; Add to Print Queue
</button>

<script>
if (typeof addToPrintQueue === 'undefined') {
    function addToPrintQueue(type, id, label) {
        fetch('/print-queue/add', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'type=' + encodeURIComponent(type) + '&id=' + id + '&label=' + encodeURIComponent(label)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var badge = document.querySelector('.print-queue-badge');
            if (badge) {
                badge.textContent = data.count;
                badge.parentElement.style.display = 'inline';
            }
            // Brief visual feedback
            var toast = document.getElementById('toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast';
                toast.className = 'toast toast-success';
                document.querySelector('.settings-content, .dashboard-content, main')?.prepend(toast);
            }
            toast.textContent = 'Added to print queue (' + data.count + ' items)';
            toast.style.opacity = '1';
            setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
        });
    }
}
</script>
