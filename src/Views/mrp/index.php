<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>MRP — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}}</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1400px;margin:24px auto;padding:0 16px;">
        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">MRP — Material Requirements</h1>
            <div style="display:flex;gap:8px;">
                <a href="/production/calendar" class="btn btn-secondary">Production Calendar</a>
                <a href="/production/schedule" class="btn btn-secondary">Schedule View</a>
                <a href="/mrp/export?facility_id=<?=$facilityId?>" class="btn btn-secondary">Export CSV</a>
                <button class="btn btn-secondary" onclick="window.print()">Print</button>
            </div>
        </div>

        <form method="GET" action="/mrp" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label>
                <select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;">
                <?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=$facilityId==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?>
                </select></div>
            <button type="submit" class="btn btn-primary" style="height:32px;">Run MRP</button>
        </form>

        <?php
        $orderCount = count(array_filter($results, fn($r) => $r['action'] === 'ORDER'));
        $reorderCount = count(array_filter($results, fn($r) => $r['action'] === 'REORDER'));
        ?>
        <div style="display:flex;gap:16px;margin-bottom:16px;">
            <div style="padding:8px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:13px;"><strong style="color:#dc2626;"><?=$orderCount?></strong> items need ordering</div>
            <div style="padding:8px 16px;background:#fefce8;border:1px solid #fde68a;border-radius:6px;font-size:13px;"><strong style="color:#854d0e;"><?=$reorderCount?></strong> items at reorder point</div>
            <div style="padding:8px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:13px;"><strong style="color:#166534;"><?=count($results)-$orderCount-$reorderCount?></strong> items OK</div>
        </div>

        <table class="data-table" style="font-size:12px;">
            <thead><tr><th>Item</th><th>Type</th><th style="text-align:right;">On Hand</th><th style="text-align:right;">Available</th><th style="text-align:right;">SO Demand</th><th style="text-align:right;">PO Supply</th><th style="text-align:right;">Batch Supply</th><th style="text-align:right;">Net</th><th>Action</th><th style="text-align:right;">Suggested</th><th>Supplier</th><th>Other Facilities</th><th class="no-print">Actions</th></tr></thead>
            <tbody>
            <?php if(empty($results)):?><tr><td colspan="13" class="empty-state">No items to display.</td></tr>
            <?php else:foreach($results as $r):
                $bg = match($r['action']){'ORDER'=>'background:#fef2f2;','REORDER'=>'background:#fefce8;',default=>''};
            ?>
            <tr style="<?=$bg?>">
                <td><a href="/items/<?=$r['id']?>" style="color:#2563eb;text-decoration:none;font-weight:500;"><?=htmlspecialchars($r['item_code'])?></a> <span style="color:#6b7280;font-size:11px;"><?=htmlspecialchars($r['description'])?></span></td>
                <td style="font-size:11px;"><?=str_replace('_',' ',ucwords(strtolower($r['item_type']),'_'))?></td>
                <td style="text-align:right;"><?=number_format($r['on_hand'],2)?></td>
                <td style="text-align:right;"><?=number_format($r['available'],2)?></td>
                <td style="text-align:right;<?=$r['so_demand']>0?'font-weight:600;':''?>"><?=$r['so_demand']>0?number_format($r['so_demand'],2):'—'?></td>
                <td style="text-align:right;"><?=$r['po_supply']>0?number_format($r['po_supply'],2):'—'?></td>
                <td style="text-align:right;"><?=$r['batch_supply']>0?number_format($r['batch_supply'],2):'—'?></td>
                <td style="text-align:right;font-weight:600;<?=$r['net_position']<0?'color:#dc2626;':''?>"><?=number_format($r['net_position'],2)?></td>
                <td><span class="badge <?=match($r['action']){'ORDER'=>'badge-danger','REORDER'=>'badge-warning',default=>'badge-active'}?>"><?=$r['action']?></span></td>
                <td style="text-align:right;font-weight:600;"><?=$r['suggested_order_qty']>0?number_format($r['suggested_order_qty'],2):'—'?></td>
                <td style="font-size:11px;"><?=$r['preferred_supplier']?htmlspecialchars($r['preferred_supplier']['supplier_code']).' ('.($r['preferred_supplier']['lead_time_days']??'?').'d)':'—'?></td>
                <td style="font-size:11px;"><?=!empty($r['other_facility'])?implode(', ',$r['other_facility']):'—'?></td>
                <td class="actions-cell no-print">
                    <?php if($r['action']!=='OK'):?>
                        <?php if(in_array($r['item_type'],['RAW_MATERIAL','RESALE']) && $r['preferred_supplier']):?>
                            <a href="/purchase-orders/create?item_id=<?=$r['id']?>&supplier_id=<?=$r['preferred_supplier']['supplier_id']?>" class="btn btn-sm btn-primary" style="font-size:11px;">PO</a>
                        <?php elseif(in_array($r['item_type'],['FINISHED_GOOD','INTERMEDIATE'])):?>
                            <a href="/batches/create?item_id=<?=$r['id']?>&target_quantity=<?=$r['suggested_order_qty']?>" class="btn btn-sm btn-primary" style="font-size:11px;">Batch</a>
                        <?php endif;?>
                        <?php if(!empty($r['other_facility'])):?><a href="/transfers/create" class="btn btn-sm btn-secondary" style="font-size:11px;">Transfer</a><?php endif;?>
                    <?php endif;?>
                </td>
            </tr>
            <?php endforeach;endif;?>
            </tbody>
        </table>
    </div>
    <script src="/assets/js/settings.js"></script>
</body>
</html>
