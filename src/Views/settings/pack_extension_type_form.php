<h2><?=$packType?'Edit: '.htmlspecialchars($packType['name']):'New Pack Extension Type'?></h2>
<form method="POST" action="/settings/pack-extensions/save" style="max-width:700px;">
    <input type="hidden" name="id" value="<?=$packType['id']??0?>">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Code <span style="color:red;">*</span></label><input type="text" name="code" value="<?=htmlspecialchars($packType['code']??'')?>" required class="form-input" style="width:100%;text-transform:uppercase;" placeholder="e.g. -50"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Name <span style="color:red;">*</span></label><input type="text" name="name" value="<?=htmlspecialchars($packType['name']??'')?>" required class="form-input" style="width:100%;" placeholder="e.g. 50lb Fiber Drum"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Display Order</label><input type="number" name="display_sequence" value="<?=$packType['display_sequence']??0?>" class="form-input" style="width:100%;"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Default Net Weight (lbs)</label><input type="number" step="0.0001" name="default_net_weight" value="<?=$packType['default_net_weight']??''?>" class="form-input" style="width:100%;" required></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Tare Weight (lbs)</label><input type="number" step="0.0001" name="tare_weight" value="<?=$packType['tare_weight']??''?>" class="form-input" style="width:100%;" required></div>
        <div style="display:flex;align-items:end;"><label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" name="active" value="1" <?=($packType===null||$packType['active'])?'checked':''?> class="form-checkbox"> Active</label></div>
    </div>

    <h3 style="margin:16px 0 8px;">Packaging Materials</h3>
    <p style="font-size:12px;color:#6b7280;margin-bottom:8px;">Items consumed when packing into this container type (drum, lid, label, etc.):</p>
    <div id="materials-container">
        <?php foreach($materials as $idx=>$m):?>
        <div class="mat-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
            <input type="hidden" name="mat_item_id[]" value="<?=$m['item_id']?>">
            <span style="font-size:13px;flex:2;"><?=htmlspecialchars($m['item_code'].' — '.$m['item_description'])?></span>
            <input type="number" step="0.0001" name="mat_quantity[]" value="<?=$m['quantity_per_pack']?>" class="form-input" style="width:100px;font-size:13px;" placeholder="Qty">
            <button type="button" class="btn btn-sm btn-warning" onclick="this.closest('.mat-row').remove()">&times;</button>
        </div>
        <?php endforeach;?>
    </div>
    <div style="display:flex;gap:8px;align-items:end;margin-bottom:16px;">
        <div style="position:relative;flex:2;">
            <input type="text" id="matSearch" class="form-input" style="width:100%;font-size:13px;" placeholder="Search items to add..." autocomplete="off">
            <div id="matSugs" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;"></div>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="/settings/pack-extensions" class="btn btn-secondary">Cancel</a>
    </div>
</form>
<script>
(function(){
    var inp=document.getElementById('matSearch'),sugs=document.getElementById('matSugs'),timer;
    inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(function(d){sugs.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.style.cssText='padding:6px 10px;font-size:13px;cursor:pointer;';div.textContent=i.display;div.addEventListener('click',function(){var c=document.getElementById('materials-container');var row=document.createElement('div');row.className='mat-row';row.style.cssText='display:flex;gap:8px;align-items:center;margin-bottom:6px;';row.innerHTML='<input type="hidden" name="mat_item_id[]" value="'+i.id+'"><span style="font-size:13px;flex:2;">'+i.item_code+' — '+i.description+'</span><input type="number" step="0.0001" name="mat_quantity[]" value="1" class="form-input" style="width:100px;font-size:13px;" placeholder="Qty"><button type="button" class="btn btn-sm btn-warning" onclick="this.closest(\'.mat-row\').remove()">&times;</button>';c.appendChild(row);inp.value='';sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});
    document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});
})();
</script>
