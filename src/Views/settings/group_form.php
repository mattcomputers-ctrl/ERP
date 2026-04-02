<h2><?=$mode==='edit'?'Edit Group: '.htmlspecialchars($group['name']):'New Group'?></h2>

<form method="POST" action="<?=$mode==='edit'?'/groups/'.$group['id'].'/edit':'/groups/create'?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;max-width:600px;">
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Group Name <span style="color:red;">*</span></label><input type="text" name="name" value="<?=htmlspecialchars($group['name']??'')?>" required class="form-input" style="width:100%;"></div>
        <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Description</label><input type="text" name="description" value="<?=htmlspecialchars($group['description']??'')?>" class="form-input" style="width:100%;"></div>
    </div>
    <div style="margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" name="is_system_admin" value="1" <?=!empty($group['is_system_admin'])?'checked':''?> class="form-checkbox"> <strong>System Administrator</strong> <span style="color:#6b7280;">(bypasses all permission checks)</span></label>
    </div>

    <!-- Permissions Matrix -->
    <h3 style="margin-bottom:8px;">Module Permissions</h3>
    <table class="data-table" style="margin-bottom:16px;font-size:13px;">
        <thead>
            <tr><th style="width:40%;">Module</th><th style="text-align:center;width:15%;">View</th><th style="text-align:center;width:15%;">Create</th><th style="text-align:center;width:15%;">Edit</th><th style="text-align:center;width:15%;">Delete</th></tr>
        </thead>
        <tbody>
        <?php foreach($modules as $category => $mods): ?>
            <tr style="background:#f0f2f5;"><td colspan="5" style="font-weight:600;font-size:12px;text-transform:uppercase;color:#6b7280;padding:6px 8px;"><?=htmlspecialchars($category)?></td></tr>
            <?php foreach($mods as $mod):
                $p = $permissions[$mod] ?? [];
                $label = ucwords(str_replace('_', ' ', $mod));
            ?>
            <tr>
                <td style="padding-left:24px;"><?=htmlspecialchars($label)?></td>
                <td style="text-align:center;"><input type="checkbox" name="perms[<?=$mod?>][view]" value="1" <?=!empty($p['can_view'])?'checked':''?>></td>
                <td style="text-align:center;"><input type="checkbox" name="perms[<?=$mod?>][create]" value="1" <?=!empty($p['can_create'])?'checked':''?>></td>
                <td style="text-align:center;"><input type="checkbox" name="perms[<?=$mod?>][edit]" value="1" <?=!empty($p['can_edit'])?'checked':''?>></td>
                <td style="text-align:center;"><input type="checkbox" name="perms[<?=$mod?>][delete]" value="1" <?=!empty($p['can_delete'])?'checked':''?>></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-bottom:16px;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('input[name*=perms]').forEach(c=>c.checked=true)">Select All</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('input[name*=perms]').forEach(c=>c.checked=false)">Clear All</button>
    </div>

    <!-- Special Permissions -->
    <h3 style="margin-bottom:8px;">Special Permissions</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:16px;">
        <?php foreach($specialPermKeys as $key => $label): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;padding:4px 8px;background:#f9fafb;border-radius:4px;cursor:pointer;">
            <input type="checkbox" name="special[]" value="<?=htmlspecialchars($key)?>" <?=in_array($key,$specialPerms)?'checked':''?>> <?=htmlspecialchars($label)?>
        </label>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?=$mode==='edit'?'Save Changes':'Create Group'?></button>
        <a href="/groups" class="btn btn-secondary">Cancel</a>
    </div>
</form>
