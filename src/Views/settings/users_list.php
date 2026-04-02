<h2>Users</h2>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <form method="GET" action="/users" style="display:flex;gap:8px;align-items:end;">
        <div><label style="font-size:12px;display:block;margin-bottom:2px;">Active</label><select name="active" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="1" <?=($filters['active']??'1')==='1'?'selected':''?>>Active</option><option value="0" <?=($filters['active']??'')==='0'?'selected':''?>>Inactive</option><option value="" <?=($filters['active']??'1')===''?'selected':''?>>All</option></select></div>
        <div><label style="font-size:12px;display:block;margin-bottom:2px;">Group</label><select name="group_id" class="form-input" style="font-size:13px;padding:4px 8px;"><option value="">All</option><?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=($filters['group_id']??0)==$g['id']?'selected':''?>><?=htmlspecialchars($g['name'])?></option><?php endforeach;?></select></div>
        <button type="submit" class="btn btn-secondary" style="height:32px;">Filter</button>
    </form>
    <a href="/users/create" class="btn btn-primary">+ New User</a>
</div>

<table class="data-table">
    <thead><tr><th>Username</th><th>Full Name</th><th>Email</th><th>Group</th><th>Last Login</th><th>Active</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($users)):?><tr><td colspan="7" class="empty-state">No users found.</td></tr>
    <?php else:foreach($users as $u):?>
    <tr class="<?=!$u['active']?'inactive-row':''?>">
        <td style="font-weight:500;"><?=htmlspecialchars($u['username'])?></td>
        <td><?=htmlspecialchars($u['full_name'])?></td>
        <td><?=htmlspecialchars($u['email'])?></td>
        <td><?=$u['group_name']?'<span class="badge '.($u['group_is_admin']?'badge-danger':'badge-info').'">'.htmlspecialchars($u['group_name']).'</span>':'<span style="color:#9ca3af;">—</span>'?></td>
        <td><?=$u['last_login']?date('M j, Y g:ia',strtotime($u['last_login'])):'<span style="color:#9ca3af;">Never</span>'?></td>
        <td><span class="badge <?=$u['active']?'badge-active':'badge-inactive'?>"><?=$u['active']?'Active':'Inactive'?></span></td>
        <td class="actions-cell">
            <a href="/users/<?=$u['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a>
            <?php if($u['active']):?>
            <form method="POST" action="/users/<?=$u['id']?>/deactivate" style="display:inline;"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate?')">Deactivate</button></form>
            <form method="POST" action="/users/<?=$u['id']?>/reset-password" style="display:inline;"><button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Reset password?')">Reset PW</button></form>
            <?php endif;?>
        </td>
    </tr>
    <?php endforeach;endif;?>
    </tbody>
</table>
