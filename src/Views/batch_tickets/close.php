<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Close Batch <?=htmlspecialchars($batch['batch_number'])?> — Precision Ink ERP</title>
<link rel="stylesheet" href="/assets/css/settings.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>.section-header{font-size:14px;font-weight:600;color:#1d4ed8;border-bottom:2px solid #bfdbfe;padding-bottom:4px;margin:24px 0 12px;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Close Batch: <?=htmlspecialchars($batch['batch_number'])?></h1>
            <a href="/batches/<?=$batch['id']?>" class="btn btn-secondary">&larr; Back</a>
        </div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Item: <strong><?=htmlspecialchars($batch['item_code'].' — '.$batch['item_description'])?></strong> | Target: <?=number_format((float)$batch['target_quantity'],4)?> | Facility: <?=htmlspecialchars($batch['facility_name'])?></p>

        <form method="POST" action="/batches/<?=$batch['id']?>/close">

            <!-- Section 1: Actual Ingredients -->
            <div class="section-header">1. Actual Ingredients</div>
            <?php foreach($lines as $l): $lots=$lotsPerItem[$l['item_id']]??[]; ?>
            <div style="padding:12px;background:#f9fafb;border-radius:6px;margin-bottom:8px;border-left:4px solid #16a34a;" x-data="{actualQty:'<?=(float)$l['theoretical_quantity']?>'}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div><strong><?=htmlspecialchars($l['item_code'])?></strong> <span style="color:#6b7280;"><?=htmlspecialchars($l['item_description'])?></span></div>
                    <div style="font-size:12px;color:#6b7280;">Theoretical: <?=number_format((float)$l['theoretical_quantity'],4)?> <?=htmlspecialchars($l['uom_abbr']??'')?></div>
                </div>
                <div style="display:flex;gap:12px;align-items:end;margin-bottom:8px;">
                    <div><label style="font-size:12px;display:block;margin-bottom:2px;">Actual Quantity</label>
                        <input type="number" step="0.0001" name="ingredients[<?=$l['id']?>][actual_quantity]" x-model="actualQty" class="form-input" style="width:140px;" required>
                    </div>
                </div>
                <?php if(!empty($lots)):?>
                <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Available FIFO Lots (oldest first):</div>
                <table style="width:100%;font-size:12px;border-collapse:collapse;">
                    <tr style="color:#6b7280;"><th style="text-align:left;padding:2px 6px;">Lot</th><th style="text-align:right;padding:2px 6px;">Available</th><th style="text-align:right;padding:2px 6px;">Cost</th><th style="padding:2px 6px;">Expiration</th><th style="padding:2px 6px;">Location</th></tr>
                    <?php foreach($lots as $lot):?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:2px 6px;font-weight:500;"><?=htmlspecialchars($lot['lot_number'])?></td>
                        <td style="padding:2px 6px;text-align:right;"><?=number_format((float)$lot['remaining_quantity'],4)?></td>
                        <td style="padding:2px 6px;text-align:right;">$<?=number_format((float)$lot['unit_cost'],4)?></td>
                        <td style="padding:2px 6px;"><?=$lot['expiration_date']?date('M j, Y',strtotime($lot['expiration_date'])):'—'?></td>
                        <td style="padding:2px 6px;"><?=htmlspecialchars($lot['location'])?></td>
                    </tr>
                    <?php endforeach;?>
                </table>
                <?php else:?>
                <p style="color:#dc2626;font-size:12px;">No available lots at this facility.</p>
                <?php endif;?>
            </div>
            <?php endforeach;?>

            <!-- Section 2: Actual Yield -->
            <div class="section-header">2. Actual Yield</div>
            <table class="data-table" style="margin-bottom:12px;">
                <thead><tr><th>Pack</th><th style="text-align:right;">Target</th><th>Actual Qty</th><th>Containers</th></tr></thead>
                <tbody>
                <?php foreach($packs as $p):?>
                <tr>
                    <td style="font-weight:500;"><?=htmlspecialchars($p['pack_name'])?></td>
                    <td style="text-align:right;"><?=number_format((float)$p['target_quantity'],4)?></td>
                    <td><input type="number" step="0.0001" name="packs[<?=$p['id']?>][actual_quantity]" value="<?=$p['target_quantity']?>" class="form-input" style="width:120px;" required></td>
                    <td><input type="number" name="packs[<?=$p['id']?>][container_count]" class="form-input" style="width:80px;" placeholder="#"></td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>

            <!-- Section 3: Scrap -->
            <div class="section-header">3. Scrap (Existing)</div>
            <?php if(empty($scrap)):?>
                <p style="color:#9ca3af;margin-bottom:12px;">No scrap entries. Add scrap from the batch view if needed.</p>
            <?php else:?>
                <table class="data-table" style="margin-bottom:12px;font-size:13px;">
                    <thead><tr><th>Material</th><th style="text-align:right;">Qty</th><th>Type</th></tr></thead>
                    <tbody><?php foreach($scrap as $s):?><tr><td><?=htmlspecialchars($s['material_description'])?></td><td style="text-align:right;"><?=number_format((float)$s['quantity'],4)?> <?=htmlspecialchars($s['uom_abbr']??'')?></td><td><span class="badge"><?=$s['scrap_type']?></span></td></tr><?php endforeach;?></tbody>
                </table>
            <?php endif;?>

            <!-- Section 4: QC Results -->
            <div class="section-header">4. QC Results</div>
            <?php if(empty($tests)):?>
                <p style="color:#9ca3af;margin-bottom:12px;">No QC spec defined for this item.</p>
            <?php else:?>
                <table class="data-table" style="margin-bottom:12px;">
                    <thead><tr><th>Test</th><th>Type</th><th>Spec</th><th>Result</th><th>Pass/Fail</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach($tests as $t):?>
                    <tr>
                        <td style="font-weight:500;"><?=htmlspecialchars($t['test_name'])?> <?=$t['is_required']?'<span style="color:red;">*</span>':''?></td>
                        <td><span class="badge <?=$t['test_type']==='PASS_FAIL'?'badge-info':'badge-warning'?>"><?=$t['test_type']==='PASS_FAIL'?'P/F':'Numeric'?></span></td>
                        <td><?=$t['test_type']==='NUMERIC_RANGE'?number_format((float)($t['min_value']??0),2).' — '.number_format((float)($t['max_value']??0),2).' '.htmlspecialchars($t['uom']??''):'—'?></td>
                        <td>
                            <?php if($t['test_type']==='PASS_FAIL'):?>
                                <select name="qc[<?=$t['id']?>][result_value]" class="form-input" style="width:100px;" <?=$t['is_required']?'required':''?>>
                                    <option value="">—</option><option value="PASS">PASS</option><option value="FAIL">FAIL</option>
                                </select>
                                <input type="hidden" name="qc[<?=$t['id']?>][pass_fail]" value="PASS">
                            <?php else:?>
                                <input type="number" step="0.0001" name="qc[<?=$t['id']?>][result_value]" class="form-input" style="width:120px;" <?=$t['is_required']?'required':''?>
                                       placeholder="<?=($t['min_value']??'').' - '.($t['max_value']??'')?>">
                                <input type="hidden" name="qc[<?=$t['id']?>][pass_fail]" value="PASS">
                                <input type="hidden" name="qc[<?=$t['id']?>][is_out_of_spec]" value="0">
                            <?php endif;?>
                        </td>
                        <td>—</td>
                        <td><input type="text" name="qc[<?=$t['id']?>][notes]" class="form-input" style="width:120px;font-size:12px;padding:3px 6px;"></td>
                    </tr>
                    <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>

            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Close this batch? This will consume inventory and create finished good lots.')">Close Batch</button>
                <a href="/batches/<?=$batch['id']?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
