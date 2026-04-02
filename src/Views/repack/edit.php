<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=$mode==='edit'?'Edit Repack':'New Repack'?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}.search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:700px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=$mode==='edit'?'Edit: '.htmlspecialchars($repack['rpk_number']??''):'New Repack Ticket'?></h1>
            <a href="/repack" class="btn btn-secondary">&larr; Back</a>
        </div>
        <form method="POST" action="<?=$mode==='edit'?'/repack/'.$repack['id'].'/edit':'/repack/create'?>">
            <div class="form-section">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Facility</label>
                        <select name="facility_id" class="form-input" style="width:100%;" required>
                        <?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=($repack['facility_id']??$activeFacilityId)==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?>
                        </select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Repack Date</label><input type="date" name="repack_date" value="<?=htmlspecialchars($repack['repack_date']??date('Y-m-d'))?>" class="form-input" style="width:100%;"></div>
                    <div class="search-wrap" style="grid-column:span 2;">
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Item <span style="color:red;">*</span></label>
                        <input type="hidden" name="item_id" id="itemId" value="<?=$repack['item_id']??''?>">
                        <input type="text" id="itemSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search items..."
                               value="<?=isset($repack['item_code'])?htmlspecialchars($repack['item_code'].' — '.$repack['item_description']):''?>">
                        <div class="search-suggestions" id="itemSugs"></div>
                    </div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Source Pack</label>
                        <select name="source_pack_id" id="srcPack" class="form-input" style="width:100%;">
                            <option value="0">Bulk / No Pack</option>
                        </select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Source Quantity <span style="color:red;">*</span></label><input type="number" step="0.0001" name="source_quantity" value="<?=htmlspecialchars($repack['source_quantity']??'')?>" class="form-input" style="width:100%;" required></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Destination Pack</label>
                        <select name="destination_pack_id" id="dstPack" class="form-input" style="width:100%;">
                            <option value="0">Bulk / No Pack</option>
                        </select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Destination Quantity <span style="color:red;">*</span></label><input type="number" step="0.0001" name="destination_quantity" value="<?=htmlspecialchars($repack['destination_quantity']??'')?>" class="form-input" style="width:100%;" required></div>
                </div>
                <div style="margin-top:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Reason</label><textarea name="reason" rows="2" class="form-input" style="width:100%;"><?=htmlspecialchars($repack['reason']??'')?></textarea></div>
            </div>
            <div style="margin-top:16px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create Repack'?></button>
                <a href="/repack" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    (function(){var inp=document.getElementById('itemSearch'),sugs=document.getElementById('itemSugs'),hid=document.getElementById('itemId'),timer;
    inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){sugs.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.textContent=i.display;div.addEventListener('click',function(){inp.value=i.item_code+' — '+i.description;hid.value=i.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});
    document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});})();
    </script>
</body>
</html>
