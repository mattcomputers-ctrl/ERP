<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Raw Material Trace — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>@media print{.no-print{display:none!important;}}</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1100px;margin:24px auto;padding:0 16px;">
        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Raw Material Lot Trace</h1>
            <div style="display:flex;gap:8px;">
                <a href="/traceability" class="btn btn-secondary">&larr; Back</a>
                <?php if($results):?><button class="btn btn-secondary" onclick="window.print()">Print</button>
                <a href="/traceability/export?mode=raw_material&lot=<?=urlencode($lot)?>" class="btn btn-secondary">CSV</a><?php endif;?>
            </div>
        </div>

        <form method="POST" action="/traceability/raw-material" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Supplier Lot Number</label><input type="text" name="lot" value="<?=htmlspecialchars($lot)?>" class="form-input" style="width:300px;" required></div>
            <button type="submit" class="btn btn-primary">Trace</button>
        </form>

        <div style="font-size:11px;color:#9ca3af;margin-bottom:8px;">Query: Raw Material Lot "<?=htmlspecialchars($lot)?>" | Run: <?=date('M j, Y g:ia')?> | By: <?=htmlspecialchars($_SESSION['user']['full_name']??'')?></div>

        <?php if($results && !empty($results['receipts'])):
            $totalBatches=0;$customerSet=[];
            foreach($results['receipts'] as $r){$totalBatches+=count($r['batches']);foreach($r['batches'] as $b)foreach($b['shipments'] as $s)$customerSet[$s['company_name']]=1;}
        ?>
        <div style="padding:10px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;margin-bottom:16px;font-size:13px;">
            Found in <strong><?=count($results['receipts'])?></strong> receipt(s), used in <strong><?=$totalBatches?></strong> batch(es), shipped to <strong><?=count($customerSet)?></strong> customer(s).
        </div>

        <?php foreach($results['receipts'] as $r):?>
        <div style="margin-bottom:16px;" x-data="{open:true}">
            <div style="padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;display:flex;justify-content:space-between;" @click="open=!open">
                <div><strong><?=htmlspecialchars($r['item_code'])?></strong> — <?=htmlspecialchars($r['description'])?> | Supplier: <?=htmlspecialchars($r['supplier_name'])?> | PO: <?=htmlspecialchars($r['po_number'])?> | Received: <?=date('M j, Y',strtotime($r['receipt_date']))?></div>
                <span x-text="open?'▼':'▶'" style="color:#6b7280;"></span>
            </div>
            <div x-show="open" style="margin-left:24px;margin-top:8px;">
                <?php if(empty($r['batches'])):?><p style="color:#9ca3af;font-size:13px;">Not yet consumed in any batch.</p>
                <?php else:foreach($r['batches'] as $b):?>
                <div style="margin-bottom:8px;" x-data="{bOpen:true}">
                    <div style="padding:6px 10px;background:#eff6ff;border-radius:4px;cursor:pointer;display:flex;justify-content:space-between;" @click="bOpen=!bOpen">
                        <span>Batch <strong><?=htmlspecialchars($b['batch_number'])?></strong> — <?=htmlspecialchars($b['item_code'])?> <?=htmlspecialchars($b['description'])?> | Used: <?=number_format((float)$b['quantity_used'],4)?> | Closed: <?=$b['closed_at']?date('M j, Y',strtotime($b['closed_at'])):'Open'?></span>
                        <span x-text="bOpen?'▼':'▶'" style="color:#6b7280;"></span>
                    </div>
                    <div x-show="bOpen" style="margin-left:24px;margin-top:4px;">
                        <?php if(empty($b['shipments'])):?><p style="color:#9ca3af;font-size:12px;">No shipments from this batch.</p>
                        <?php else:?>
                        <table class="data-table" style="font-size:12px;">
                            <thead><tr><th>Customer</th><th>Shipment</th><th>SO</th><th>Ship Date</th><th style="text-align:right;">Qty</th><th>Location</th></tr></thead>
                            <tbody><?php foreach($b['shipments'] as $s):?>
                            <tr><td style="font-weight:500;"><?=htmlspecialchars($s['company_name'])?></td><td><?=htmlspecialchars($s['shipment_number'])?></td><td><?=htmlspecialchars($s['so_number'])?></td><td><?=date('M j, Y',strtotime($s['ship_date']))?></td><td style="text-align:right;"><?=number_format((float)$s['quantity_shipped'],4)?></td><td><?=htmlspecialchars(($s['location_name']??'').($s['city']?', '.$s['city']:''))?></td></tr>
                            <?php endforeach;?></tbody>
                        </table>
                        <?php endif;?>
                    </div>
                </div>
                <?php endforeach;endif;?>
            </div>
        </div>
        <?php endforeach;?>
        <?php elseif($results):?><p style="color:#9ca3af;">No receipts found matching "<?=htmlspecialchars($lot)?>".</p><?php endif;?>
    </div>
</body>
</html>
