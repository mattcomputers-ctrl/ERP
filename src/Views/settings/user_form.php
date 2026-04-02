<h2><?=$mode==='edit'?'Edit User: '.htmlspecialchars($editUser['username']):'New User'?></h2>

<form method="POST" action="<?=$mode==='edit'?'/users/'.$editUser['id'].'/edit':'/users/create'?>" style="max-width:600px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Username <span style="color:red;">*</span></label><input type="text" name="username" value="<?=htmlspecialchars($editUser['username']??'')?>" required class="form-input" style="width:100%;"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Full Name <span style="color:red;">*</span></label><input type="text" name="full_name" value="<?=htmlspecialchars($editUser['full_name']??'')?>" required class="form-input" style="width:100%;"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Email <span style="color:red;">*</span></label><input type="email" name="email" value="<?=htmlspecialchars($editUser['email']??'')?>" required class="form-input" style="width:100%;"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Group</label><select name="group_id" class="form-input" style="width:100%;"><option value="">— No Group —</option><?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=($editUser['group_id']??0)==$g['id']?'selected':''?>><?=htmlspecialchars($g['name'])?></option><?php endforeach;?></select></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Password<?=$mode==='create'?' <span style="color:red;">*</span>':' (leave blank to keep)'?></label><input type="password" name="password" <?=$mode==='create'?'required':''?> class="form-input" style="width:100%;" autocomplete="new-password"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Confirm Password</label><input type="password" name="password_confirm" class="form-input" style="width:100%;" autocomplete="new-password"></div>
    </div>

    <div style="margin-bottom:12px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" name="active" value="1" <?=($editUser===null||$editUser['active'])?'checked':''?> class="form-checkbox"> Active</label>
    </div>

    <?php if(!empty($facilities)):?>
    <div style="margin-bottom:16px;">
        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Facility Restrictions <span style="font-weight:400;color:#6b7280;">(leave all unchecked for full access)</span></label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach($facilities as $f):?>
            <label style="display:flex;align-items:center;gap:4px;font-size:13px;padding:4px 8px;background:#f9fafb;border-radius:4px;cursor:pointer;">
                <input type="checkbox" name="facilities[]" value="<?=$f['id']?>" <?=in_array($f['id'],$userFacilities)?'checked':''?>> <?=htmlspecialchars($f['name'])?>
            </label>
            <?php endforeach;?>
        </div>
    </div>
    <?php endif;?>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create User'?></button>
        <a href="/users" class="btn btn-secondary">Cancel</a>
    </div>
</form>
