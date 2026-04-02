<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$mode==='edit'?'Edit':'New'?> QC Spec — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<style>
    .test-row{display:flex;gap:8px;align-items:center;padding:8px;background:#f9fafb;border-radius:4px;margin-bottom:6px;flex-wrap:wrap;}
    .test-row .form-input,.test-row select{font-size:13px;padding:4px 8px;}
    .search-wrap{position:relative;}.search-suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:200px;overflow-y:auto;z-index:100;display:none;}
    .search-suggestions div{padding:6px 10px;font-size:13px;cursor:pointer;}.search-suggestions div:hover{background:#eff6ff;}
</style></head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:900px;margin:24px auto;padding:0 16px;">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=$mode==='edit'?'Edit QC Spec: v'.$spec['version_number'].' — '.htmlspecialchars($spec['item_code']):'New QC Spec'?></h1>
            <a href="/qc/specs" class="btn btn-secondary">&larr; Back</a>
        </div>

        <form method="POST" action="<?=$mode==='edit'?'/qc/specs/'.$spec['id'].'/edit':'/qc/specs/create'?>">
            <?php if($mode==='create'):?>
            <div class="form-section" style="margin-bottom:16px;">
                <div class="search-wrap">
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Item <span style="color:red;">*</span></label>
                    <input type="hidden" name="item_id" id="itemId" value="<?=htmlspecialchars($prefilledItem['id']??'')?>">
                    <input type="text" id="itemSearch" class="form-input" style="width:100%;" autocomplete="off" placeholder="Search items..."
                           value="<?=$prefilledItem?htmlspecialchars($prefilledItem['item_code'].' — '.$prefilledItem['description']):''?>">
                    <div class="search-suggestions" id="itemSugs"></div>
                </div>
            </div>
            <?php else:?>
                <input type="hidden" name="item_id" value="<?=$spec['item_id']?>">
            <?php endif;?>

            <h3 style="margin-bottom:8px;">Tests</h3>
            <div id="tests-container">
                <?php if(!empty($tests)):foreach($tests as $idx=>$t):?>
                <div class="test-row" data-index="<?=$idx?>">
                    <div style="flex:2;min-width:180px;"><input type="text" name="tests[<?=$idx?>][test_name]" value="<?=htmlspecialchars($t['test_name'])?>" class="form-input" style="width:100%;" placeholder="Test name" required></div>
                    <div style="width:130px;">
                        <select name="tests[<?=$idx?>][test_type]" class="form-input type-sel" style="width:100%;" onchange="toggleRange(this)">
                            <option value="PASS_FAIL" <?=$t['test_type']==='PASS_FAIL'?'selected':''?>>Pass/Fail</option>
                            <option value="NUMERIC_RANGE" <?=$t['test_type']==='NUMERIC_RANGE'?'selected':''?>>Numeric Range</option>
                        </select>
                    </div>
                    <div class="range-fields" style="display:<?=$t['test_type']==='NUMERIC_RANGE'?'flex':'none'?>;gap:4px;">
                        <input type="number" step="0.0001" name="tests[<?=$idx?>][min_value]" value="<?=$t['min_value']??''?>" class="form-input" style="width:80px;" placeholder="Min">
                        <input type="number" step="0.0001" name="tests[<?=$idx?>][max_value]" value="<?=$t['max_value']??''?>" class="form-input" style="width:80px;" placeholder="Max">
                        <input type="text" name="tests[<?=$idx?>][uom]" value="<?=htmlspecialchars($t['uom']??'')?>" class="form-input" style="width:60px;" placeholder="UOM">
                    </div>
                    <label style="display:flex;align-items:center;gap:4px;font-size:12px;"><input type="checkbox" name="tests[<?=$idx?>][is_required]" value="1" <?=$t['is_required']?'checked':''?>> Req</label>
                    <button type="button" class="btn btn-sm btn-warning" onclick="removeTest(this)">&times;</button>
                </div>
                <?php endforeach;endif;?>
            </div>
            <button type="button" class="btn btn-secondary" onclick="addTest()" style="margin-top:4px;">+ Add Test</button>

            <div style="margin-top:24px;display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save as New Version':'Create Spec'?></button>
                <a href="/qc/specs" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    var testIdx=<?=!empty($tests)?count($tests):0?>;
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);
    function addTest(){var c=document.getElementById('tests-container');var r=document.createElement('div');r.className='test-row';r.innerHTML='<div style="flex:2;min-width:180px;"><input type="text" name="tests['+testIdx+'][test_name]" class="form-input" style="width:100%;" placeholder="Test name" required></div><div style="width:130px;"><select name="tests['+testIdx+'][test_type]" class="form-input type-sel" style="width:100%;" onchange="toggleRange(this)"><option value="PASS_FAIL">Pass/Fail</option><option value="NUMERIC_RANGE">Numeric Range</option></select></div><div class="range-fields" style="display:none;gap:4px;"><input type="number" step="0.0001" name="tests['+testIdx+'][min_value]" class="form-input" style="width:80px;" placeholder="Min"><input type="number" step="0.0001" name="tests['+testIdx+'][max_value]" class="form-input" style="width:80px;" placeholder="Max"><input type="text" name="tests['+testIdx+'][uom]" class="form-input" style="width:60px;" placeholder="UOM"></div><label style="display:flex;align-items:center;gap:4px;font-size:12px;"><input type="checkbox" name="tests['+testIdx+'][is_required]" value="1" checked> Req</label><button type="button" class="btn btn-sm btn-warning" onclick="removeTest(this)">&times;</button>';c.appendChild(r);testIdx++;}
    function removeTest(btn){if(document.querySelectorAll('.test-row').length<=1){alert('At least one test required.');return;}btn.closest('.test-row').remove();}
    function toggleRange(sel){var rf=sel.closest('.test-row').querySelector('.range-fields');rf.style.display=sel.value==='NUMERIC_RANGE'?'flex':'none';}
    // Item search
    (function(){var inp=document.getElementById('itemSearch');if(!inp)return;var sugs=document.getElementById('itemSugs');var hid=document.getElementById('itemId');var timer;inp.addEventListener('input',function(){var self=this;clearTimeout(timer);timer=setTimeout(function(){var q=self.value.trim();if(q.length<1){sugs.style.display='none';return;}fetch('/items/search?q='+encodeURIComponent(q)+'&limit=10').then(function(r){return r.json();}).then(function(d){sugs.innerHTML='';d.forEach(function(i){var div=document.createElement('div');div.textContent=i.display;div.addEventListener('click',function(){inp.value=i.item_code+' — '+i.description;hid.value=i.id;sugs.style.display='none';});sugs.appendChild(div);});sugs.style.display=d.length?'block':'none';});},300);});document.addEventListener('click',function(e){if(!inp.contains(e.target))sugs.style.display='none';});})();
    if(testIdx===0)addTest();
    </script>
</body>
</html>
