<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lot Traceability — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.tab-bar{display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:16px;}.tab-btn{padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;border:none;background:none;color:#6b7280;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;}.tab-btn.active{color:#2563eb;border-bottom-color:#2563eb;}.tab-panel{display:none;}.tab-panel.active{display:block;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <h1 style="margin:0 0 16px;">Lot Traceability &amp; Recall</h1>
        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('raw')">Raw Material Lot</button>
            <button class="tab-btn" onclick="switchTab('finished')">Finished Good Lot</button>
            <button class="tab-btn" onclick="switchTab('complaint')">Customer Complaint</button>
        </div>
        <div id="tab-raw" class="tab-panel active">
            <p style="color:#6b7280;margin-bottom:12px;">Trace a supplier lot number forward through batches and shipments.</p>
            <form method="POST" action="/traceability/raw-material" style="display:flex;gap:8px;align-items:end;">
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Supplier Lot Number</label><input type="text" name="lot" class="form-input" style="width:300px;" required placeholder="Enter lot number (partial match)..."></div>
                <button type="submit" class="btn btn-primary">Trace</button>
            </form>
        </div>
        <div id="tab-finished" class="tab-panel">
            <p style="color:#6b7280;margin-bottom:12px;">Trace a finished good batch number to see where it was shipped.</p>
            <form method="POST" action="/traceability/finished-good" style="display:flex;gap:8px;align-items:end;">
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Batch Number</label><input type="text" name="batch" class="form-input" style="width:300px;" required placeholder="e.g. 260402001"></div>
                <button type="submit" class="btn btn-primary">Trace</button>
            </form>
        </div>
        <div id="tab-complaint" class="tab-panel">
            <p style="color:#6b7280;margin-bottom:12px;">Trace all lots shipped to a customer in a date range.</p>
            <form method="POST" action="/traceability/complaint" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Customer ID</label><input type="number" name="customer_id" class="form-input" style="width:120px;" required></div>
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">SO# (optional)</label><input type="number" name="so_id" class="form-input" style="width:120px;"></div>
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">From</label><input type="date" name="date_from" class="form-input"></div>
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">To</label><input type="date" name="date_to" class="form-input"></div>
                <button type="submit" class="btn btn-primary">Trace</button>
            </form>
        </div>
    </div>
    <script>
    function switchTab(n){document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));document.getElementById('tab-'+n).classList.add('active');event.target.classList.add('active');}
    </script>
</body>
</html>
