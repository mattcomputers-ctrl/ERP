<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Cycle Count — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:600px; margin:24px auto; padding:0 16px;">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">New Cycle Count Session</h1>
            <a href="/inventory/counts" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="/inventory/counts/create" x-data="{ selection: 'all' }">
            <div class="form-section">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Facility <span style="color:red;">*</span></label>
                    <select name="facility_id" class="form-input" style="width:100%;" required>
                        <?php foreach ($facilities as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $activeFacilityId == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                        <input type="checkbox" name="is_blind" value="1" class="form-checkbox"> Blind Count (hide book quantities on count sheet)
                    </label>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Item Selection</label>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="item_selection" value="all" x-model="selection"> All active items
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                            <input type="radio" name="item_selection" value="by_type" x-model="selection"> By item type
                        </label>
                        <div x-show="selection === 'by_type'" x-cloak style="margin-left:24px;">
                            <select name="item_type" class="form-input" style="width:200px;">
                                <option value="RAW_MATERIAL">Raw Material</option>
                                <option value="FINISHED_GOOD">Finished Good</option>
                                <option value="INTERMEDIATE">Intermediate</option>
                                <option value="RESALE">Resale</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Create Count Session</button>
                <a href="/inventory/counts" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var toast = document.getElementById('toast'); if (toast) setTimeout(function() { toast.style.display='none'; }, 4000);</script>
</body>
</html>
