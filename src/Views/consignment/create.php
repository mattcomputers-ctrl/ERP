<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>New Consignment Placement — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}.search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:700px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">New Consignment Placement</h1>
            <a href="/consignment" class="btn btn-secondary">&larr; Back</a>
        </div>
        <form method="POST" action="/consignment/create">
            <div class="form-section">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="search-wrap">
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Customer <span style="color:red;">*</span></label>
                        <input type="hidden" name="customer_id" id="custId"><input type="text" id="custSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search customers..."><div class="search-suggestions" id="custSugs"></div>
                    </div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Ship-To (optional)</label><input type="number" name="ship_to_id" class="form-input" style="width:100%;" placeholder="Ship-To ID"></div>
                    <div class="search-wrap">
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Item <span style="color:red;">*</span></label>
                        <input type="hidden" name="item_id" id="itemId"><input type="text" id="itemSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search items..."><div class="search-suggestions" id="itemSugs"></div>
                    </div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Quantity <span style="color:red;">*</span></label><input type="number" step="0.0001" name="quantity_placed" class="form-input" style="width:100%;" required></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">UOM</label><select name="uom_id" class="form-input" style="width:100%;"><?php foreach($uoms as $u):?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['abbreviation'])?></option><?php endforeach;?></select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Ship From Facility</label><select name="facility_id" class="form-input" style="width:100%;"><?php foreach($facilities as $f):?><option value="<?=$f['id']?>" <?=$activeFacilityId==$f['id']?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?></select></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Placement Date</label><input type="date" name="placement_date" value="<?=date('Y-m-d')?>" class="form-input" style="width:100%;"></div>
                </div>
            </div>
            <div style="margin-top:16px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Create Placement</button>
                <a href="/consignment" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function bindSearch(inputId, sugsId, hiddenId, url){var inp=document.getElementById(inputId),sugs=document.getElementById(sugsId),hid=document.getElementById(hiddenId),timer;inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch(url+'?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){sugs.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.textContent=i.display;div.addEventListener('click',function(){inp.value=i.display;hid.value=i.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});}
    bindSearch('custSearch','custSugs','custId','/customers/search');
    bindSearch('itemSearch','itemSugs','itemId','/items/search');
    </script>
</body>
</html>
