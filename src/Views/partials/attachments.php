<!-- Attachments panel — include on any record view/edit page -->
<?php
// $attachments, $recordType, $recordId must be set in the including template
?>
<div class="mt-6 border-t pt-4" style="margin-top:24px; border-top:1px solid #e5e7eb; padding-top:16px;">
  <h3 style="font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Attachments</h3>

  <?php if (!empty($attachments)): ?>
  <ul style="list-style:none; padding:0; margin:0 0 16px 0;">
    <?php foreach ($attachments as $att): ?>
    <li style="padding:8px 0; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between;">
      <div>
        <a href="/attachments/download/<?= $att['id'] ?>"
           style="color:#2563eb; text-decoration:none; font-size:14px; font-weight:500;">
          <?= htmlspecialchars($att['original_filename']) ?>
        </a>
        <span style="font-size:12px; color:#9ca3af; margin-left:8px;">
          <?= round($att['file_size']/1024, 1) ?> KB &bull;
          <?= htmlspecialchars($att['uploaded_by_name'] ?? '') ?> &bull;
          <?= date('M j, Y', strtotime($att['created_at'])) ?>
        </span>
        <?php if (!empty($att['notes'])): ?>
        <p style="font-size:12px; color:#6b7280; margin:2px 0 0;"><?= htmlspecialchars($att['notes']) ?></p>
        <?php endif; ?>
      </div>
      <button onclick="deleteAttachment(<?= $att['id'] ?>)"
              style="color:#ef4444; font-size:12px; background:none; border:none; cursor:pointer; text-decoration:underline; margin-left:16px;">Remove</button>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p style="font-size:14px; color:#9ca3af; margin-bottom:16px;">No attachments yet.</p>
  <?php endif; ?>

  <form id="attachment-upload-form" enctype="multipart/form-data">
    <div style="display:flex; gap:8px; align-items:center;">
      <input type="file" name="attachment" id="attachment-file" style="font-size:13px;">
      <input type="text" name="notes" placeholder="Notes (optional)" class="form-input" style="font-size:13px; padding:4px 8px;">
      <button type="button" onclick="uploadAttachment('<?= htmlspecialchars($recordType) ?>', <?= (int)$recordId ?>)"
              class="btn btn-sm btn-secondary">Upload</button>
    </div>
    <p style="font-size:12px; color:#9ca3af; margin-top:4px;">Allowed: PDF, JPG, PNG, TIFF, DOCX, XLSX</p>
  </form>
</div>

<script>
function uploadAttachment(recordType, recordId) {
    var form = new FormData();
    var fileInput = document.getElementById('attachment-file');
    var notesInput = document.querySelector('#attachment-upload-form [name="notes"]');
    if (!fileInput.files[0]) { alert('Please select a file.'); return; }
    form.append('attachment', fileInput.files[0]);
    form.append('notes', notesInput.value);
    form.append('record_type', recordType);
    form.append('record_id', recordId);

    fetch('/attachments/upload', {method:'POST', body: form})
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { location.reload(); }
            else { alert('Upload failed: ' + data.error); }
        });
}

function deleteAttachment(id) {
    if (!confirm('Remove this attachment?')) return;
    fetch('/attachments/delete/' + id, {method:'POST'})
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) location.reload(); });
}
</script>
