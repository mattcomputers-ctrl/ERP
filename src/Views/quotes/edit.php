<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$mode==='edit'?'Edit Quote':'New Quote'?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}.search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}.line-row{display:flex;gap:8px;align-items:center;padding:8px;background:#f9fafb;border-radius:4px;margin-bottom:6px;flex-wrap:wrap;}.line-row .form-input,.line-row select{font-size:13px;padding:4px 8px;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:960px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=$mode==='edit'?'Edit Quote: '.htmlspecialchars($quote['quote_number']??''):'New Quote'?></h1>
            <a href="/quotes" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="<?=$mode==='edit'?'/quotes/'.$quote['id'].'/edit':'/quotes/create'?>">
            <div class="form-section" style="margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="search-wrap">
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Customer <span style="color:red;">*</span></label>
                        <input type="hidden" name="customer_id" id="custId" value="<?=$quote['customer_id']??''?>">
                        <input type="text" id="custSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search customers..."
                               value="<?=isset($quote['customer_code'])?htmlspecialchars($quote['customer_code'].' — '.$quote['customer_name']):''?>">
                        <div class="search-suggestions" id="custSugs"></div>
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Ship-To (optional)</label>
                        <select name="ship_to_id" class="form-input" style="width:100%;" id="shipToSelect"><option value="">— None —</option></select>
                    </div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Quote Date</label><input type="date" name="quote_date" value="<?=htmlspecialchars($quote['quote_date']??date('Y-m-d'))?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Expiration Date</label><input type="date" name="expiration_date" value="<?=htmlspecialchars($quote['expiration_date']??'')?>" class="form-input" style="width:100%;"></div>
                </div>
                <div style="margin-top:12px;"><label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" name="soft_reserve" value="1" <?=!empty($quote['soft_reserve'])?'checked':''?> class="form-checkbox"> Reserve inventory for this quote</label></div>
                <div style="margin-top:8px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Notes</label><textarea name="notes" rows="2" class="form-input" style="width:100%;"><?=htmlspecialchars($quote['notes']??'')?></textarea></div>
            </div>

            <h3 style="margin-bottom:8px;">Line Items</h3>
            <div id="lines-container">
                <?php if(!empty($lines)):foreach($lines as $idx=>$l):?>
                <div class="line-row" data-index="<?=$idx?>">
                    <div class="search-wrap" style="flex:2;min-width:180px;">
                        <input type="hidden" name="lines[<?=$idx?>][item_id]" value="<?=$l['item_id']?>" class="line-item-id">
                        <input type="text" class="form-input item-search" autocomplete="off" style="width:100%;" value="<?=htmlspecialchars(($l['item_code']??'').' — '.($l['item_description']??''))?>">
                        <div class="search-suggestions item-sugs"></div>
                    </div>
                    <div style="width:90px;"><input type="number" step="0.0001" min="0.0001" name="lines[<?=$idx?>][quantity]" value="<?=$l['quantity']?>" class="form-input line-qty" placeholder="Qty" required style="width:100%;"></div>
                    <div style="width:80px;"><select name="lines[<?=$idx?>][uom_id]" class="form-input" required style="width:100%;"><?php foreach($uoms as $u):?><option value="<?=$u['id']?>" <?=($l['uom_id']??'')==$u['id']?'selected':''?>><?=htmlspecialchars($u['abbreviation'])?></option><?php endforeach;?></select></div>
                    <div style="width:100px;"><input type="number" step="0.0001" name="lines[<?=$idx?>][unit_price]" value="<?=$l['unit_price']?>" class="form-input line-price" placeholder="Price" style="width:100%;"></div>
                    <div style="width:70px;text-align:right;font-size:12px;color:#6b7280;" class="line-ext">—</div>
                    <input type="hidden" name="lines[<?=$idx?>][pack_extension_id]" value="<?=$l['pack_extension_id']??''?>">
                    <input type="hidden" name="lines[<?=$idx?>][notes]" value="<?=htmlspecialchars($l['notes']??'')?>">
                    <button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>
                </div>
                <?php endforeach;endif;?>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                <button type="button" class="btn btn-secondary" onclick="addLine()">+ Add Line</button>
                <div style="font-size:14px;font-weight:600;">Total: $<span id="quoteTotal">0.00</span></div>
            </div>

            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create Quote'?></button>
                <a href="/quotes" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var lineIdx=<?=!empty($lines)?count($lines):0?>;
    var uomOpts='<?php $o="";foreach($uoms as $u){$o.="<option value=\"{$u['id']}\">".htmlspecialchars($u['abbreviation'],ENT_QUOTES)."</option>";}echo addslashes($o);?>';
    var custIdEl=document.getElementById('custId');
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);

    // Customer search
    (function(){var inp=document.getElementById('custSearch'),sugs=document.getElementById('custSugs'),timer;
    inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/customers/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){sugs.innerHTML='';d.forEach(function(c){var div=document.createElement('div');div.textContent=c.display;if(c.account_hold)div.style.color='#dc2626';div.addEventListener('click',function(){inp.value=c.display;custIdEl.value=c.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});
    document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});})();

    function addLine(){var c=document.getElementById('lines-container');var r=document.createElement('div');r.className='line-row';r.innerHTML='<div class="search-wrap" style="flex:2;min-width:180px;"><input type="hidden" name="lines['+lineIdx+'][item_id]" value="" class="line-item-id"><input type="text" class="form-input item-search" autocomplete="off" style="width:100%;" placeholder="Search items..."><div class="search-suggestions item-sugs"></div></div><div style="width:90px;"><input type="number" step="0.0001" min="0.0001" name="lines['+lineIdx+'][quantity]" class="form-input line-qty" placeholder="Qty" required style="width:100%;"></div><div style="width:80px;"><select name="lines['+lineIdx+'][uom_id]" class="form-input" required style="width:100%;">'+uomOpts+'</select></div><div style="width:100px;"><input type="number" step="0.0001" name="lines['+lineIdx+'][unit_price]" class="form-input line-price" placeholder="Price" style="width:100%;"></div><div style="width:70px;text-align:right;font-size:12px;color:#6b7280;" class="line-ext">—</div><input type="hidden" name="lines['+lineIdx+'][pack_extension_id]" value=""><input type="hidden" name="lines['+lineIdx+'][notes]" value=""><button type="button" class="btn btn-sm btn-warning" onclick="removeLine(this)">&times;</button>';c.appendChild(r);bindItemSearch(r.querySelector('.item-search'));bindCalc(r);lineIdx++;}
    function removeLine(btn){if(document.querySelectorAll('.line-row').length<=1){alert('At least one line required.');return;}btn.closest('.line-row').remove();updateTotal();}
    function bindItemSearch(inp){var timer;inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();var box=self.parentNode.querySelector('.item-sugs');if(q.length<1){box.style.display='none';return;}fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){box.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.textContent=i.display;div.addEventListener('click',function(){self.value=i.item_code+' — '+i.description;self.parentNode.querySelector('.line-item-id').value=i.id;box.style.display='none';var row=self.closest('.line-row');var qtyEl=row.querySelector('.line-qty');var qty=parseFloat(qtyEl.value)||1;var cid=custIdEl.value;fetch('/quotes/price?item_id='+i.id+'&customer_id='+(cid||0)+'&qty='+qty).then(r=>r.json()).then(function(p){if(p.unit_price)row.querySelector('.line-price').value=p.unit_price;updateTotal();});});box.appendChild(div);});box.style.display=d.length?'block':'none';});},300);});document.addEventListener('click',function(e){if(!inp.contains(e.target)){var box=inp.parentNode.querySelector('.item-sugs');if(box)box.style.display='none';}});}
    function bindCalc(row){var q=row.querySelector('.line-qty'),p=row.querySelector('.line-price');if(q)q.addEventListener('input',updateTotal);if(p)p.addEventListener('input',updateTotal);}
    function updateTotal(){var total=0;document.querySelectorAll('.line-row').forEach(function(r){var q=parseFloat(r.querySelector('.line-qty')?.value)||0;var p=parseFloat(r.querySelector('.line-price')?.value)||0;var ext=r.querySelector('.line-ext');if(ext)ext.textContent=q*p>0?'$'+(q*p).toFixed(2):'—';total+=q*p;});document.getElementById('quoteTotal').textContent=total.toFixed(2);}
    document.querySelectorAll('.item-search').forEach(bindItemSearch);
    document.querySelectorAll('.line-row').forEach(bindCalc);
    if(lineIdx===0)addLine();updateTotal();
    </script>
</body>
</html>
