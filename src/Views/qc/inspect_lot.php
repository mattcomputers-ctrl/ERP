<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Inspect Lot — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;}.modal-overlay.active{display:flex;}.modal-box{background:#fff;border-radius:8px;padding:24px;max-width:500px;width:90%;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:800px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Inspect Lot: <?=htmlspecialchars($lot['lot_number'])?></h1>
            <a href="/qc/inspection" class="btn btn-secondary">&larr; Back to Queue</a>
        </div>

        <!-- Lot Details -->
        <div class="form-section" style="margin-bottom:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Item</strong><p style="margin:2px 0;"><a href="/items/<?=$lot['item_id']?>" style="color:#2563eb;"><?=htmlspecialchars($lot['item_code'])?></a> — <?=htmlspecialchars($lot['item_description'])?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Quantity</strong><p style="margin:2px 0;"><?=number_format((float)$lot['remaining_quantity'],4)?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Facility / Location</strong><p style="margin:2px 0;"><?=htmlspecialchars($lot['facility_name'])?> <?=$lot['location']?'/ '.htmlspecialchars($lot['location']):''?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Receipt Date</strong><p style="margin:2px 0;"><?=date('M j, Y',strtotime($lot['created_at']))?></p></div>
                <div><strong style="font-size:12px;color:#6b7280;text-transform:uppercase;">Expiration</strong><p style="margin:2px 0;"><?=$lot['expiration_date']?date('M j, Y',strtotime($lot['expiration_date'])):'—'?></p></div>
            </div>
        </div>

        <!-- QC Test Entry -->
        <form method="POST" action="/qc/inspection/<?=$insp['id']?>/pass" id="passForm">
        <?php if($spec && !empty($tests)):?>
            <h3 style="margin-bottom:8px;">QC Tests (Spec v<?=(int)$spec['version_number']?>)</h3>
            <table class="data-table" style="margin-bottom:12px;">
                <thead><tr><th>Test</th><th>Type</th><th>Spec</th><th>Result</th><th>Pass/Fail</th></tr></thead>
                <tbody>
                <?php foreach($tests as $t):?>
                <tr>
                    <td style="font-weight:500;"><?=htmlspecialchars($t['test_name'])?> <?=$t['is_required']?'<span style="color:red;">*</span>':''?></td>
                    <td><span class="badge <?=$t['test_type']==='PASS_FAIL'?'badge-info':'badge-warning'?>"><?=$t['test_type']==='PASS_FAIL'?'P/F':'Numeric'?></span></td>
                    <td><?=$t['test_type']==='NUMERIC_RANGE'?number_format((float)($t['min_value']??0),2).' — '.number_format((float)($t['max_value']??0),2).' '.htmlspecialchars($t['uom']??''):'—'?></td>
                    <td>
                        <?php if($t['test_type']==='PASS_FAIL'):?>
                            <select name="results[<?=$t['id']?>][value]" class="form-input" style="width:100px;font-size:13px;padding:3px 6px;">
                                <option value="PASS">PASS</option><option value="FAIL">FAIL</option>
                            </select>
                            <input type="hidden" name="results[<?=$t['id']?>][pass_fail]" value="PASS">
                        <?php else:?>
                            <input type="number" step="0.0001" name="results[<?=$t['id']?>][value]" class="form-input" style="width:120px;font-size:13px;padding:3px 6px;"
                                   <?=$t['is_required']?'required':''?>>
                            <input type="hidden" name="results[<?=$t['id']?>][pass_fail]" value="PASS">
                        <?php endif;?>
                    </td>
                    <td><span style="color:#16a34a;">&#10003;</span></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        <?php else:?>
            <p style="color:#9ca3af;margin-bottom:12px;">No QC spec defined for this item. Visual inspection only.</p>
        <?php endif;?>
            <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Notes</label><textarea name="notes" rows="2" class="form-input" style="width:100%;"></textarea></div>
        </form>

        <div style="display:flex;gap:8px;">
            <button type="submit" form="passForm" class="btn btn-primary" onclick="return confirm('Pass this lot? It will be released to AVAILABLE.')">Pass — Release Lot</button>
            <button class="btn btn-danger" onclick="document.getElementById('failModal').classList.add('active')">Fail</button>
        </div>
    </div>

    <!-- Fail Modal -->
    <div id="failModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-box">
            <h3 style="margin:0 0 16px;">Fail Inspection</h3>
            <form method="POST" action="/qc/inspection/<?=$insp['id']?>/fail">
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Disposition</label>
                    <select name="disposition" class="form-input" style="width:100%;">
                        <option value="QUARANTINE">Quarantine</option>
                        <option value="RETURN_TO_SUPPLIER">Return to Supplier (creates SCAR)</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Reason <span style="color:red;">*</span></label>
                    <textarea name="reason" rows="3" class="form-input" style="width:100%;" required></textarea>
                </div>
                <div style="display:flex;gap:8px;"><button type="submit" class="btn btn-danger">Fail Lot</button><button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').classList.remove('active')">Cancel</button></div>
            </form>
        </div>
    </div>
    <script src="/assets/js/settings.js"></script><script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
