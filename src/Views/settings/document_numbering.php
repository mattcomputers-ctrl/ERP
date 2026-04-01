<h1>Document Numbering</h1>

<form method="POST" action="/settings/document-numbering" class="settings-form">
    <div class="form-section">
        <h2>Numbering Sequences</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sequence</th>
                    <th>Prefix</th>
                    <th>Next Number</th>
                    <th>Preview</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sequences as $seq): ?>
                    <tr>
                        <td><?= htmlspecialchars($labels[$seq['sequence_key']] ?? $seq['sequence_key']) ?></td>
                        <td>
                            <input type="text" name="prefix_<?= htmlspecialchars($seq['sequence_key']) ?>"
                                   value="<?= htmlspecialchars($seq['prefix']) ?>"
                                   style="width: 80px;" maxlength="20"
                                   class="seq-prefix" data-key="<?= htmlspecialchars($seq['sequence_key']) ?>">
                        </td>
                        <td>
                            <input type="number" name="next_<?= htmlspecialchars($seq['sequence_key']) ?>"
                                   value="<?= (int) $seq['next_number'] ?>"
                                   min="1" step="1" style="width: 120px;"
                                   class="seq-number" data-key="<?= htmlspecialchars($seq['sequence_key']) ?>">
                        </td>
                        <td class="seq-preview" id="preview-<?= htmlspecialchars($seq['sequence_key']) ?>" style="color: #6b7280; font-size: 12px;">
                            <?= htmlspecialchars($seq['prefix']) ?><?= str_pad($seq['next_number'], 5, '0', STR_PAD_LEFT) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-section">
        <h2>Batch Numbers</h2>
        <p style="color: #6b7280; font-size: 13px;">
            Batch numbers use <strong>YYMMDDNNN</strong> format (e.g. <code>260331001</code> = first batch on March 31, 2026). This is not configurable.
        </p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Numbering</button>
    </div>
</form>

<script>
function updatePreviews() {
    document.querySelectorAll('.seq-number').forEach(function(input) {
        var key = input.dataset.key;
        var prefix = document.querySelector('.seq-prefix[data-key="' + key + '"]').value;
        var num = parseInt(input.value) || 1;
        var padded = String(num).padStart(5, '0');
        document.getElementById('preview-' + key).textContent = prefix + padded;
    });
}

document.querySelectorAll('.seq-number, .seq-prefix').forEach(function(input) {
    input.addEventListener('input', updatePreviews);
});
</script>
