<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?=$mode==='edit'?'Edit SCAR':'New SCAR'?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>.search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}.search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:700px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=$mode==='edit'?'Edit SCAR: '.htmlspecialchars($scar['scar_number']??''):'New SCAR'?></h1>
            <a href="/scars" class="btn btn-secondary">&larr; Back</a>
        </div>
        <form method="POST" action="<?=$mode==='edit'?'/scars/'.$scar['id'].'/edit':'/scars/create'?>">
            <div class="form-section">
                <div class="search-wrap" style="margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Supplier <span style="color:red;">*</span></label>
                    <input type="hidden" name="supplier_id" id="suppId" value="<?=$scar['supplier_id']??''?>">
                    <input type="text" id="suppSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search suppliers..."
                           value="<?=isset($scar['supplier_code'])?htmlspecialchars($scar['supplier_code'].' — '.$scar['supplier_name']):''?>">
                    <div class="search-suggestions" id="suppSugs"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Issue Date</label><input type="date" name="issue_date" value="<?=htmlspecialchars($scar['issue_date']??date('Y-m-d'))?>" class="form-input" style="width:100%;"></div>
                    <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Due Date</label><input type="date" name="due_date" value="<?=htmlspecialchars($scar['due_date']??'')?>" class="form-input" style="width:100%;"></div>
                </div>
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Lot Number</label><input type="text" name="lot_number" value="<?=htmlspecialchars($scar['lot_number']??'')?>" class="form-input" style="width:100%;"></div>
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Description <span style="color:red;">*</span></label><textarea name="description" rows="4" required class="form-input" style="width:100%;"><?=htmlspecialchars($scar['description']??'')?></textarea></div>
                <div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Required Action</label><textarea name="required_action" rows="3" class="form-input" style="width:100%;"><?=htmlspecialchars($scar['required_action']??'')?></textarea></div>
                <input type="hidden" name="po_receipt_id" value="<?=$scar['po_receipt_id']??''?>">
            </div>
            <div style="margin-top:16px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create SCAR'?></button>
                <a href="/scars" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    (function(){var inp=document.getElementById('suppSearch');var sugs=document.getElementById('suppSugs');var hid=document.getElementById('suppId');var timer;
    inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/suppliers/search?q='+encodeURIComponent(q)+'&limit=10').then(function(r){return r.json();}).then(function(d){sugs.innerHTML='';d.forEach(function(s){var div=document.createElement('div');div.textContent=s.display;div.addEventListener('click',function(){inp.value=s.display;hid.value=s.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});
    document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});})();
    </script>
</body>
</html>
