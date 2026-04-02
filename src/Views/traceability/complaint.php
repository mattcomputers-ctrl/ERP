<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Customer Complaint Trace — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>@media print{.no-print{display:none!important;}}</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Customer Complaint Trace</h1>
            <div style="display:flex;gap:8px;"><a href="/traceability" class="btn btn-secondary">&larr; Back</a>
                <?php if($results):?><button class="btn btn-secondary" onclick="window.print()">Print</button><a href="/traceability/export?mode=complaint&customer_id=<?=$customerId?>&so_id=<?=$soId?>&date_from=<?=urlencode($dateFrom)?>&date_to=<?=urlencode($dateTo)?>" class="btn btn-secondary">CSV</a><?php endif;?>
            </div>
        </div>

        <form method="POST" action="/traceability/complaint" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;flex-wrap:wrap;">
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Customer ID <span style="color:red;">*</span></label><input type="number" name="customer_id" value="<?=$customerId?>" class="form-input" style="width:120px;" required></div>
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">SO# (optional)</label><input type="number" name="so_id" value="<?=$soId?>" class="form-input" style="width:120px;"></div>
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">From</label><input type="date" name="date_from" value="<?=htmlspecialchars($dateFrom)?>" class="form-input"></div>
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">To</label><input type="date" name="date_to" value="<?=htmlspecialchars($dateTo)?>" class="form-input"></div>
            <button type="submit" class="btn btn-primary">Trace</button>
        </form>

        <?php if($results):?>
        <div style="font-size:11px;color:#9ca3af;margin-bottom:8px;">Query: Customer #<?=$customerId?><?=$soId?' SO #'.$soId:''?><?=$dateFrom?' from '.$dateFrom:''?><?=$dateTo?' to '.$dateTo:''?> | Run: <?=date('M j, Y g:ia')?></div>

        <?php if(empty($results['shipment_lines'])):?><p style="color:#9ca3af;">No shipment lines found matching criteria.</p>
        <?php else:?>
        <p style="font-size:13px;color:#6b7280;margin-bottom:8px;">Found <?=count($results['shipment_lines'])?> lot(s) shipped.</p>

        <?php foreach($results['shipment_lines'] as $l):?>
        <div style="margin-bottom:8px;" x-data="{open:false}">
            <div style="padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;cursor:pointer;display:flex;justify-content:space-between;font-size:13px;" @click="open=!open">
                <div>
                    <strong><?=htmlspecialchars($l['item_code'])?></strong> <?=htmlspecialchars($l['description'])?> |
                    Lot: <strong><?=htmlspecialchars($l['lot_number'])?></strong> |
                    Ship: <?=htmlspecialchars($l['shipment_number'])?> (<?=date('M j',strtotime($l['ship_date']))?>) |
                    SO: <?=htmlspecialchars($l['so_number'])?> |
                    Qty: <?=number_format((float)$l['quantity_shipped'],4)?>
                </div>
                <span x-text="open?'▼':'▶'" style="color:#6b7280;"></span>
            </div>
            <div x-show="open" style="margin-left:24px;margin-top:4px;padding:8px;background:#fff;border:1px solid #e5e7eb;border-radius:4px;">
                <?php if(!empty($l['batch'])):?>
                <p style="font-size:12px;"><strong>Batch:</strong> <?=htmlspecialchars($l['batch']['batch_number'])?> — <?=htmlspecialchars($l['batch']['item_code'])?> | Closed: <?=$l['batch']['closed_at']?date('M j, Y',strtotime($l['batch']['closed_at'])):'Open'?> | Yield: <?=number_format((float)($l['batch']['actual_yield']??0),4)?></p>
                <?php if(!empty($l['ingredients'])):?>
                <p style="font-size:12px;font-weight:600;margin-top:8px;">Ingredients:</p>
                <table class="data-table" style="font-size:11px;">
                    <thead><tr><th>Item</th><th>Supplier Lot</th><th style="text-align:right;">Qty Used</th></tr></thead>
                    <tbody><?php foreach($l['ingredients'] as $ing):?>
                    <tr><td><?=htmlspecialchars($ing['item_code'])?> — <?=htmlspecialchars($ing['description'])?></td><td><?=htmlspecialchars($ing['supplier_lot_number']??'—')?></td><td style="text-align:right;"><?=number_format((float)$ing['quantity_used'],4)?></td></tr>
                    <?php endforeach;?></tbody>
                </table>
                <?php endif;?>
                <?php else:?><p style="font-size:12px;color:#9ca3af;">No batch data available for this lot.</p><?php endif;?>
            </div>
        </div>
        <?php endforeach;?>
        <?php endif;?>
        <?php endif;?>
    </div>
</body>
</html>
