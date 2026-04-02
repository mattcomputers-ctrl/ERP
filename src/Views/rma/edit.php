<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=$mode==='edit'?'Edit RMA':'New RMA'?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}.search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}.line-row{display:flex;gap:6px;align-items:center;padding:8px;background:#f9fafb;border-radius:4px;margin-bottom:6px;flex-wrap:wrap;}.line-row .form-input,.line-row select{font-size:13px;padding:4px 8px;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:960px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=$mode==='edit'?'Edit RMA: '.htmlspecialchars($rma['rma_number']??''):'New RMA'?></h1>
            <a href="/rma" class="btn btn-secondary">&larr; Back</a>
        </div>
        <form method="POST" action="<?=$mode==='edit'?'/rma/'.$rma['id'].'/edit':'/rma/create'?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="search-wrap">
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Customer <span style="color:red;">*</span></label>
                        <input type="hidden" name="customer_id" id="custId" value="<?=$rma['customer_id']??''?>">
                        <input type="text" id="custSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search..."
                               value="<?=isset($rma['customer_code'])?htmlspecialchars($rma['customer_code'].' — '.$rma['customer_name']):''?>">
                        <div class="search-suggestions" id="custSugs"></div>
                    </div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">RMA Date</label><input type="date" name="rma_date" value="<?=htmlspecialchars($rma['rma_date']??date('Y-m-d'))?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Return Reason <span style="color:red;">*</span></label>
                        <select name="return_reason" required class="form-input" style="width:100%;"><option value="">— Select —</option><?php foreach($reasons as $r):?><option value="<?=htmlspecialchars($r['name'])?>" <?=($rma['return_reason']??'')===$r['name']?'selected':''?>><?=htmlspecialchars($r['name'])?></option><?php endforeach;?></select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Receiving Facility</label><select name="facility_id" class="form-input" style="width:100%;"><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($rma['facility_id']??$activeFacilityId)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Linked SO (optional)</label><input type="number" name="so_id" value="<?=$rma['so_id']??''?>" class="form-input" style="width:100%;" placeholder="SO ID"></div>
                </div>
                <div style="margin-top:8px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Notes</label><textarea name="notes" rows="2" class="form-input" style="width:100%;"><?=htmlspecialchars($rma['notes']??'')?></textarea></div>
            </div>

            <h3 style="margin-bottom:8px;">Return Lines</h3>
            <div id="lines-container">
                <?php if(!empty($lines)):foreach($lines as $idx=>$l):?>
                <div class="line-row" data-index="<?=$idx?>">
                    <div class="search-wrap" style="flex:2;min-width:160px;"><input type="hidden" name="lines[<?=$idx?>][item_id]" value="<?=$l['item_id']?>" class="line-item-id"><input type="text" class="form-input item-search" autocomplete="off" style="width:100%;" value="<?=htmlspecialchars(($l['item_code']??'').' — '.($l['item_description']??''))?>"><div class="search-suggestions item-sugs"></div></div>
                    <div style="width:90px;"><input type="number" step="0.0001" name="lines[<?=$idx?>][authorized_quantity]" value="<?=$l['authorized_quantity']?>" class="form-input" placeholder="Qty" required style="width:100%;"></div>
                    <div style="width:110px;"><input type="text" name="lines[<?=$idx?>][lot_number]" value="<?=htmlspecialchars($l['lot_number']??'')?>" class="form-input" placeholder="Lot #" style="width:100%;"></div>
                    <div style="width:150px;"><select name="lines[<?=$idx?>][disposition]" class="form-input" style="width:100%;"><option value="RETURN_TO_STOCK" <?=($l['disposition']??'')==='RETURN_TO_STOCK'?'selected':''?>>Return to Stock</option><option value="WRITE_OFF" <?=($l['disposition']??'')==='WRITE_OFF'?'selected':''?>>Write Off</option><option value="HOLD_FOR_INSPECTION" <?=($l['disposition']??'')==='HOLD_FOR_INSPECTION'?'selected':''?>>Hold for Inspection</option></select></div>
                    <input type="hidden" name="lines[<?=$idx?>][pack_extension_id]" value="<?=$l['pack_extension_id']??''?>">
                    <input type="hidden" name="lines[<?=$idx?>][condition_notes]" value="<?=htmlspecialchars($l['condition_notes']??'')?>">
                    <button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>
                </div>
                <?php endforeach;endif;?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addLine()" style="margin-top:4px;">+ Add Line</button>

            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create RMA'?></button>
                <a href="/rma" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var lineIdx=<?=!empty($lines)?count($lines):0?>;
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);

    (function(){var inp=document.getElementById('custSearch'),sugs=document.getElementById('custSugs'),hid=document.getElementById('custId'),timer;
    inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/customers/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){sugs.innerHTML='';d.forEach(function(c){var div=document.createElement('div');div.textContent=c.display;div.addEventListener('click',function(){inp.value=c.display;hid.value=c.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});
    document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});})();

    function addLine(){var c=document.getElementById('lines-container');var r=document.createElement('div');r.className='line-row';r.innerHTML='<div class="search-wrap" style="flex:2;min-width:160px;"><input type="hidden" name="lines['+lineIdx+'][item_id]" value="" class="line-item-id"><input type="text" class="form-input item-search" autocomplete="off" style="width:100%;" placeholder="Search items..."><div class="search-suggestions item-sugs"></div></div><div style="width:90px;"><input type="number" step="0.0001" name="lines['+lineIdx+'][authorized_quantity]" class="form-input" placeholder="Qty" required style="width:100%;"></div><div style="width:110px;"><input type="text" name="lines['+lineIdx+'][lot_number]" class="form-input" placeholder="Lot #" style="width:100%;"></div><div style="width:150px;"><select name="lines['+lineIdx+'][disposition]" class="form-input" style="width:100%;"><option value="RETURN_TO_STOCK">Return to Stock</option><option value="WRITE_OFF">Write Off</option><option value="HOLD_FOR_INSPECTION" selected>Hold for Inspection</option></select></div><input type="hidden" name="lines['+lineIdx+'][pack_extension_id]" value=""><input type="hidden" name="lines['+lineIdx+'][condition_notes]" value=""><button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>';c.appendChild(r);bindItemSearch(r.querySelector('.item-search'));lineIdx++;}
    function removeLine(btn){if(document.querySelectorAll('.line-row').length<=1){alert('At least one line.');return;}btn.closest('.line-row').remove();}
    function bindItemSearch(inp){var timer;inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();var box=self.parentNode.querySelector('.item-sugs');if(q.length<1){box.style.display='none';return;}fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){box.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.textContent=i.display;div.addEventListener('click',function(){self.value=i.item_code+' — '+i.description;self.parentNode.querySelector('.line-item-id').value=i.id;box.style.display='none';});box.appendChild(div);});box.style.display=d.length?'block':'none';});},300);});document.addEventListener('click',function(e){if(!inp.contains(e.target)){var box=inp.parentNode.querySelector('.item-sugs');if(box)box.style.display='none';}});}
    document.querySelectorAll('.item-search').forEach(bindItemSearch);
    if(lineIdx===0)addLine();
    </script>
</body>
</html>
