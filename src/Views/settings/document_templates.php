<h2>Document Templates</h2>
<p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Customize outbound PDF documents. Edit HTML templates with merge variables.</p>

<table class="data-table">
    <thead><tr><th>Template</th><th>Key</th><th>Page Size</th><th>Orientation</th><th>Active</th><th>Updated</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($templates)):?><tr><td colspan="7" class="empty-state">No templates defined. Run migration 0020 to seed defaults.</td></tr>
    <?php else:foreach($templates as $t):?>
    <tr>
        <td style="font-weight:500;"><?=htmlspecialchars($t['template_name']??$t['template_key'])?></td>
        <td><code style="font-size:12px;background:#f3f4f6;padding:2px 6px;border-radius:3px;"><?=htmlspecialchars($t['template_key']??'')?></code></td>
        <td><?=htmlspecialchars($t['page_size']??'letter')?></td>
        <td><?=htmlspecialchars($t['page_orientation']??'portrait')?></td>
        <td><span class="badge <?=$t['is_active']?'badge-active':'badge-inactive'?>"><?=$t['is_active']?'Active':'Inactive'?></span></td>
        <td><?=$t['updated_at']?date('M j, Y',strtotime($t['updated_at'])):''?></td>
        <td class="actions-cell">
            <?php if($t['template_key']):?><a href="/settings/document-templates/<?=htmlspecialchars($t['template_key'])?>/edit" class="btn btn-sm btn-secondary">Edit</a><?php endif;?>
        </td>
    </tr>
    <?php endforeach;endif;?>
    </tbody>
</table>
