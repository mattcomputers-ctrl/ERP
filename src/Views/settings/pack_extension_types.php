<h2>Pack Extension Types</h2>
<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="/settings/pack-extensions/create" class="btn btn-primary">+ New Pack Type</a>
</div>
<table class="data-table">
    <thead><tr><th>Code</th><th>Name</th><th style="text-align:right;">Default Net Wt</th><th style="text-align:right;">Tare Wt</th><th>Materials</th><th>Order</th><th>Active</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($types)):?><tr><td colspan="8" class="empty-state">No pack extension types defined.</td></tr>
    <?php else:foreach($types as $t):?>
    <tr class="<?=!$t['active']?'inactive-row':''?>">
        <td style="font-weight:600;font-family:monospace;"><?=htmlspecialchars($t['code'])?></td>
        <td><?=htmlspecialchars($t['name'])?></td>
        <td style="text-align:right;"><?=number_format((float)$t['default_net_weight'],4)?></td>
        <td style="text-align:right;"><?=number_format((float)$t['tare_weight'],4)?></td>
        <td><?=(int)$t['material_count']?></td>
        <td><?=(int)$t['display_sequence']?></td>
        <td><span class="badge <?=$t['active']?'badge-active':'badge-inactive'?>"><?=$t['active']?'Active':'Inactive'?></span></td>
        <td class="actions-cell"><a href="/settings/pack-extensions/<?=$t['id']?>/edit" class="btn btn-sm btn-secondary">Edit</a></td>
    </tr>
    <?php endforeach;endif;?>
    </tbody>
</table>
