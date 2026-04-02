<h2>Groups &amp; Permissions</h2>
<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="/groups/create" class="btn btn-primary">+ New Group</a>
</div>

<table class="data-table">
    <thead><tr><th>Group Name</th><th>Description</th><th>System Admin</th><th>Users</th><th>Active</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($groups)):?><tr><td colspan="6" class="empty-state">No groups defined.</td></tr>
    <?php else:foreach($groups as $g):?>
    <tr>
        <td style="font-weight:500;"><?=htmlspecialchars($g['name'])?></td>
        <td style="color:#6b7280;font-size:12px;"><?=htmlspecialchars($g['description']??'')?></td>
        <td><?=$g['is_system_admin']?'<span class="badge badge-danger">Admin</span>':''?></td>
        <td><?=(int)$g['user_count']?></td>
        <td><span class="badge <?=$g['active']?'badge-active':'badge-inactive'?>"><?=$g['active']?'Active':'Inactive'?></span></td>
        <td class="actions-cell">
            <a href="/groups/<?=$g['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a>
            <?php if((int)$g['user_count']===0):?>
            <form method="POST" action="/groups/<?=$g['id']?>/delete" style="display:inline;"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Delete group?')">Delete</button></form>
            <?php endif;?>
        </td>
    </tr>
    <?php endforeach;endif;?>
    </tbody>
</table>
