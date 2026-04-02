<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>MRP — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>@media print{.no-print{display:none!important;}}
.cascade-row{background:#fff7ed;font-size:11px;}
.cascade-row td{padding:4px 8px;border-top:none!important;}
</style>
</head>
<body>
    <header class="app-header no-print"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1500px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">MRP — Material Requirements</h1>
            <div style="display:flex;gap:8px;">
                <a href="/production/calendar" class="btn btn-secondary">Production Calendar</a>
                <a href="/production/schedule" class="btn btn-secondary">Schedule View</a>
                <a href="/mrp/export?facility_id=<?=$facilityId?>" class="btn btn-secondary">Export CSV</a>
                <button class="btn btn-secondary" onclick="window.print()">Print</button>
            </div>
        </div>

        <!-- Filter bar -->
        <form method="GET" action="/mrp" class="no-print" style="display:flex;gap:8px;margin-bottom:16px;align-items:end;flex-wrap:wrap;">
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Facility</label>
                <select name="facility_id" class="form-input" style="font-size:13px;padding:4px 8px;">
                <?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=$facilityId==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?>
                </select></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Search</label>
                <input type="text" name="search" value="<?=htmlspecialchars($filters['search']??'')?>" placeholder="Item code or description" class="form-input" style="font-size:13px;padding:4px 8px;width:180px;"></div>
            <div><label style="font-size:12px;display:block;margin-bottom:2px;">Item Type</label>
                <select name="item_type" class="form-input" style="font-size:13px;padding:4px 8px;">
                    <option value="ALL">All Types</option>
                    <?php foreach(['RAW_MATERIAL','FINISHED_GOOD','INTERMEDIATE','PACKAGING','RESALE'] as $t):?>
                    <option value="<?=$t?>" <?=($filters['item_type']??'')===$t?'selected':''?>><?=str_replace('_',' ',ucwords(strtolower($t),'_'))?></option>
                    <?php endforeach;?>
                </select></div>
            <div style="display:flex;flex-direction:column;gap:2px;">
                <label style="font-size:12px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" name="show_ok" value="1" <?=!empty($filters['show_ok'])?'checked':''?>> Show OK items
                </label>
                <label style="font-size:12px;display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" name="so_triggered_only" value="1" <?=!empty($filters['so_triggered_only'])?'checked':''?>> SO-triggered only
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="height:32px;">Run MRP</button>
            <a href="/mrp?facility_id=<?=$facilityId?>" class="btn btn-secondary" style="height:32px;text-decoration:none;">Clear</a>
        </form>

        <?php
        $orderCount = count(array_filter($results, fn($r) => $r['action'] === 'ORDER'));
        $reorderCount = count(array_filter($results, fn($r) => $r['action'] === 'REORDER'));
        $soTriggeredCount = count(array_filter($results, fn($r) => !empty($r['so_would_push_below_min'])));
        $cascadeCount = count(array_filter($results, fn($r) => !empty($r['cascade_warnings'])));

        // Collect all cascade PO suggestions for bulk PO form
        $allCascadeSuggestions = [];
        foreach ($results as $r) {
            foreach ($r['cascade_warnings'] ?? [] as $cw) {
                if ($cw['suggested_po_qty'] > 0 && !empty($cw['preferred_supplier'])) {
                    $key = $cw['item_id'];
                    if (!isset($allCascadeSuggestions[$key]) || $cw['suggested_po_qty'] > $allCascadeSuggestions[$key]['qty']) {
                        $allCascadeSuggestions[$key] = [
                            'item_id' => $cw['item_id'], 'item_code' => $cw['item_code'],
                            'qty' => $cw['suggested_po_qty'],
                            'supplier' => $cw['preferred_supplier'],
                        ];
                    }
                }
            }
        }
        ?>
        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="padding:8px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:13px;"><strong style="color:#dc2626;"><?=$orderCount?></strong> items need ordering</div>
            <div style="padding:8px 16px;background:#fefce8;border:1px solid #fde68a;border-radius:6px;font-size:13px;"><strong style="color:#854d0e;"><?=$reorderCount?></strong> items at reorder point</div>
            <?php if($soTriggeredCount > 0):?><div style="padding:8px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;font-size:13px;"><strong style="color:#c2410c;"><?=$soTriggeredCount?></strong> SO-triggered</div><?php endif;?>
            <?php if($cascadeCount > 0):?><div style="padding:8px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;font-size:13px;"><strong style="color:#92400e;"><?=$cascadeCount?></strong> with raw material cascade</div><?php endif;?>
            <div style="padding:8px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:13px;"><strong style="color:#166534;"><?=count($results)-$orderCount-$reorderCount?></strong> items OK</div>
        </div>

        <table class="data-table" style="font-size:12px;">
            <thead><tr>
                <th>Item</th><th>Type</th><th style="text-align:right;">On Hand</th><th style="text-align:right;">Available</th>
                <th style="text-align:right;">SO Demand</th><th style="text-align:right;">PO Supply</th><th style="text-align:right;">Batch Supply</th>
                <th style="text-align:right;">Net</th><th>Action</th><th>Trigger</th><th style="text-align:right;">Suggested</th>
                <th>Supplier</th><th>Other Facilities</th><th class="no-print">Actions</th>
            </tr></thead>
            <tbody>
            <?php if(empty($results)):?><tr><td colspan="14" class="empty-state">No items to display.</td></tr>
            <?php else:foreach($results as $ri => $r):
                $bg = match($r['action']){'ORDER'=>'background:#fef2f2;','REORDER'=>($r['so_would_push_below_min']?'background:#fff7ed;':'background:#fefce8;'),default=>''};
                $hasCascade = !empty($r['cascade_warnings']);
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
                <td style="font-size:11px;">
                    <?php if ($r['trigger_reason']): ?>
                        <?php if ($r['so_would_push_below_min']): ?>
                            <span style="background:#fed7aa;color:#9a3412;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;">SO Demand</span>
                        <?php else: ?>
                            <?=htmlspecialchars($r['trigger_reason'])?>
                        <?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:600;"><?=$r['suggested_qty']>0?number_format($r['suggested_qty'],2):'—'?></td>
                <td style="font-size:11px;"><?=$r['preferred_supplier']?htmlspecialchars($r['preferred_supplier']['supplier_code']).' ('.($r['preferred_supplier']['lead_time_days']??'?').'d)':'—'?></td>
                <td style="font-size:11px;"><?=!empty($r['other_facility'])?implode(', ',$r['other_facility']):'—'?></td>
                <td class="actions-cell no-print">
                    <?php if($r['action']!=='OK'):?>
                        <?php if(in_array($r['item_type'],['RAW_MATERIAL','RESALE','PACKAGING']) && $r['preferred_supplier']):?>
                            <a href="/purchase-orders/create?item_id=<?=$r['id']?>&supplier_id=<?=$r['preferred_supplier']['supplier_id']?>&quantity=<?=$r['suggested_qty']?>" class="btn btn-sm btn-primary" style="font-size:11px;">PO</a>
                        <?php elseif(in_array($r['item_type'],['FINISHED_GOOD','INTERMEDIATE'])):?>
                            <a href="/batches/create?item_id=<?=$r['id']?>&target_quantity=<?=$r['suggested_qty']?>" class="btn btn-sm btn-primary" style="font-size:11px;">Batch</a>
                        <?php endif;?>
                        <?php if(!empty($r['other_facility'])):?><a href="/transfers/create" class="btn btn-sm btn-secondary" style="font-size:11px;">Transfer</a><?php endif;?>
                        <?php if($hasCascade):?><button type="button" class="btn btn-sm btn-warning" style="font-size:11px;" onclick="toggleCascade(<?=$ri?>)">Cascade</button><?php endif;?>
                    <?php endif;?>
                </td>
            </tr>
            <?php if($hasCascade):?>
            <tr class="cascade-row" id="cascade-<?=$ri?>" style="display:none;">
                <td colspan="14" style="padding:8px 16px;">
                    <div style="font-weight:600;color:#9a3412;margin-bottom:4px;">Raw material shortage if this batch runs:</div>
                    <?php foreach($r['cascade_warnings'] as $cw):?>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;padding:4px 8px;background:#fff;border:1px solid #fed7aa;border-radius:4px;">
                        <a href="/items/<?=$cw['item_id']?>" style="color:#2563eb;font-weight:500;text-decoration:none;"><?=htmlspecialchars($cw['item_code'])?></a>
                        <span style="color:#6b7280;">(<?=htmlspecialchars($cw['description'])?>)</span>
                        <span><?=number_format($cw['available'],2)?> lbs avail, needs <?=number_format($cw['needed_for_batch'],2)?> lbs</span>
                        <span style="color:#dc2626;font-weight:600;">&rarr; <?=number_format($cw['after_batch'],2)?> lbs (<?=number_format($cw['after_batch'] - $cw['reorder_min'],2)?> below min)</span>
                        <?php if($cw['suggested_po_qty'] > 0):?>
                            <span style="color:#166534;">Suggested PO: <?=number_format($cw['suggested_po_qty'],2)?> lbs</span>
                            <?php if(!empty($cw['preferred_supplier'])):?>
                                <a href="/purchase-orders/create?item_id=<?=$cw['item_id']?>&supplier_id=<?=$cw['preferred_supplier']['supplier_id']?>&quantity=<?=$cw['suggested_po_qty']?>" class="btn btn-sm btn-primary" style="font-size:10px;">Create PO</a>
                            <?php endif;?>
                        <?php endif;?>
                    </div>
                    <?php endforeach;?>
                </td>
            </tr>
            <?php endif;?>
            <?php endforeach;endif;?>
            </tbody>
        </table>

        <?php if(!empty($allCascadeSuggestions)):?>
        <div class="no-print" style="margin-top:24px;padding:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <h3 style="margin:0;font-size:14px;">Bulk PO Suggestions from Cascade Analysis</h3>
                <form method="POST" action="/mrp/bulk-pos" id="bulkPoForm">
                    <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']??''?>">
                    <input type="hidden" name="facility_id" value="<?=$facilityId?>">
                    <?php foreach($allCascadeSuggestions as $cs):?>
                    <input type="hidden" name="items[<?=$cs['item_id']?>]" value="<?=$cs['qty']?>">
                    <?php endforeach;?>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Create <?=count($allCascadeSuggestions)?> line(s) as DRAFT PO(s) grouped by supplier?')">
                        Create All Suggested POs (<?=count($allCascadeSuggestions)?> items)
                    </button>
                </form>
            </div>
            <table class="data-table" style="font-size:12px;">
                <thead><tr><th>Item</th><th>Supplier</th><th style="text-align:right;">Suggested Qty</th></tr></thead>
                <tbody>
                <?php foreach($allCascadeSuggestions as $cs):?>
                <tr>
                    <td><a href="/items/<?=$cs['item_id']?>" style="color:#2563eb;text-decoration:none;"><?=htmlspecialchars($cs['item_code'])?></a></td>
                    <td><?=htmlspecialchars($cs['supplier']['supplier_code'] ?? '—')?></td>
                    <td style="text-align:right;"><?=number_format($cs['qty'],2)?> lbs</td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
        </div>
        <?php endif;?>
    </div>
    <script src="/assets/js/settings.js"></script>
    <script>
    var toast=document.getElementById('toast');if(toast)setTimeout(function(){toast.style.display='none';},4000);
    function toggleCascade(idx){
        var row=document.getElementById('cascade-'+idx);
        if(row)row.style.display=row.style.display==='none'?'':'none';
    }
    </script>
</body>
</html>
