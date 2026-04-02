<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Finished Good Trace — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}}</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Finished Good Lot Trace</h1>
            <div style="display:flex;gap:8px;"><a href="/traceability" class="btn btn-secondary">&larr; Back</a>
                <?php if($results && empty($results['error'])):?><button class="btn btn-secondary" onclick="window.print()">Print</button><a href="/traceability/export?mode=finished_good&batch=<?=urlencode($batch)?>" class="btn btn-secondary">CSV</a><?php endif;?>
            </div>
        </div>

        <form method="POST" action="/traceability/finished-good" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Batch Number</label><input type="text" name="batch" value="<?=htmlspecialchars($batch)?>" class="form-input" style="width:300px;" required></div>
            <button type="submit" class="btn btn-primary">Trace</button>
        </form>

        <?php if($results && !empty($results['error'])):?><p style="color:#dc2626;"><?=htmlspecialchars($results['error'])?></p>
        <?php elseif($results):
            $b=$results['batch'];$totalShipped=array_sum(array_column($results['shipments'],'quantity_shipped'));$totalRemaining=array_sum(array_column($results['currentInventory'],'remaining_quantity'));$uniqueCustomers=count(array_unique(array_column($results['shipments'],'company_name')));
        ?>
        <div style="font-size:11px;color:#9ca3af;margin-bottom:8px;">Query: Batch "<?=htmlspecialchars($batch)?>" | Run: <?=date('M j, Y g:ia')?></div>

        <div style="padding:10px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;margin-bottom:16px;font-size:13px;">
            <strong><?=htmlspecialchars($b['item_code'])?></strong> — <?=htmlspecialchars($b['description'])?> | Yield: <?=number_format((float)$b['actual_yield'],4)?> | Shipped: <?=number_format($totalShipped,4)?> to <?=$uniqueCustomers?> customer(s) | Remaining: <?=number_format($totalRemaining,4)?>
        </div>

        <!-- Current Inventory -->
        <?php if(!empty($results['currentInventory'])):?>
        <h3 style="margin-bottom:8px;">Current Inventory</h3>
        <table class="data-table" style="margin-bottom:16px;">
            <thead><tr><th>Facility</th><th>Lot</th><th>Status</th><th style="text-align:right;">Remaining</th></tr></thead>
            <tbody><?php foreach($results['currentInventory'] as $ci):?>
            <tr><td><?=htmlspecialchars($ci['facility_name'])?></td><td><?=htmlspecialchars($ci['lot_number'])?></td><td><span class="badge <?=$ci['status']==='AVAILABLE'?'badge-active':'badge-warning'?>"><?=$ci['status']?></span></td><td style="text-align:right;"><?=number_format((float)$ci['remaining_quantity'],4)?></td></tr>
            <?php endforeach;?></tbody>
        </table>
        <?php endif;?>

        <!-- Shipments -->
        <h3 style="margin-bottom:8px;">Shipments</h3>
        <?php if(empty($results['shipments'])):?><p style="color:#9ca3af;">No shipments from this batch.</p>
        <?php else:?>
        <table class="data-table" style="margin-bottom:16px;">
            <thead><tr><th>Customer</th><th>Shipment</th><th>SO</th><th>Ship Date</th><th style="text-align:right;">Qty</th><th>Ship-To</th></tr></thead>
            <tbody><?php foreach($results['shipments'] as $s):?>
            <tr><td style="font-weight:500;"><?=htmlspecialchars($s['company_name'])?></td><td><?=htmlspecialchars($s['shipment_number'])?></td><td><?=htmlspecialchars($s['so_number'])?></td><td><?=date('M j, Y',strtotime($s['ship_date']))?></td><td style="text-align:right;"><?=number_format((float)$s['quantity_shipped'],4)?></td><td><?=htmlspecialchars(($s['location_name']??'').($s['city']?', '.$s['city']:''))?></td></tr>
            <?php endforeach;?></tbody>
        </table>
        <?php endif;?>

        <!-- Ingredients -->
        <h3 style="margin-bottom:8px;">Ingredients Used</h3>
        <?php if(empty($results['ingredients'])):?><p style="color:#9ca3af;">No ingredient data (batch may not be closed).</p>
        <?php else:?>
        <table class="data-table">
            <thead><tr><th>Item</th><th>Description</th><th>Supplier Lot</th><th style="text-align:right;">Qty Used</th><th style="text-align:right;">Unit Cost</th></tr></thead>
            <tbody><?php foreach($results['ingredients'] as $ing):?>
            <tr><td style="font-weight:500;"><?=htmlspecialchars($ing['item_code'])?></td><td><?=htmlspecialchars($ing['description'])?></td><td><?=htmlspecialchars($ing['supplier_lot_number']??'—')?></td><td style="text-align:right;"><?=number_format((float)$ing['quantity_used'],4)?></td><td style="text-align:right;">$<?=number_format((float)$ing['unit_cost'],4)?></td></tr>
            <?php endforeach;?></tbody>
        </table>
        <?php endif;?>
        <?php endif;?>
    </div>
</body>
</html>
