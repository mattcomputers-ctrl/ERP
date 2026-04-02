<h2>Edit Template: <?=htmlspecialchars($template['template_name']??$template['template_key'])?></h2>
<p style="font-size:12px;color:#6b7280;margin-bottom:16px;">Key: <code><?=htmlspecialchars($template['template_key'])?></code></p>

<div style="display:grid;grid-template-columns:3fr 1fr;gap:16px;">
    <div>
        <form method="POST" action="/settings/document-templates/<?=htmlspecialchars($template['template_key'])?>/edit" id="templateForm">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Template Name</label><input type="text" name="template_name" value="<?=htmlspecialchars($template['template_name']??'')?>" class="form-input" style="width:100%;"></div>
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Page Size</label><select name="page_size" class="form-input" style="width:100%;"><option value="letter" <?=($template['page_size']??'')==='letter'?'selected':''?>>Letter</option><option value="a4" <?=($template['page_size']??'')==='a4'?'selected':''?>>A4</option></select></div>
                <div><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Orientation</label><select name="page_orientation" class="form-input" style="width:100%;"><option value="portrait" <?=($template['page_orientation']??'')==='portrait'?'selected':''?>>Portrait</option><option value="landscape" <?=($template['page_orientation']??'')==='landscape'?'selected':''?>>Landscape</option></select></div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">HTML Content</label>
                <textarea name="html_content" id="htmlEditor" rows="25" class="form-input" style="width:100%;font-family:monospace;font-size:12px;"><?=htmlspecialchars($template['html_content']??'')?></textarea>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px;">Footer HTML</label>
                <textarea name="footer_html" rows="3" class="form-input" style="width:100%;font-family:monospace;font-size:12px;"><?=htmlspecialchars($template['footer_html']??'')?></textarea>
            </div>

            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Save Template</button>
                <a href="/settings/document-templates" class="btn btn-secondary">Back</a>
            </div>
        </form>

        <form method="POST" action="/settings/document-templates/<?=htmlspecialchars($template['template_key'])?>/preview" target="_blank" style="margin-top:8px;">
            <input type="hidden" name="html_content" id="previewContent">
            <input type="hidden" name="page_size" id="previewPageSize">
            <input type="hidden" name="page_orientation" id="previewOrientation">
            <button type="submit" class="btn btn-secondary" onclick="document.getElementById('previewContent').value=document.getElementById('htmlEditor').value;document.getElementById('previewPageSize').value=document.querySelector('[name=page_size]').value;document.getElementById('previewOrientation').value=document.querySelector('[name=page_orientation]').value;">Preview PDF</button>
        </form>
    </div>

    <!-- Variable Reference -->
    <div style="padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;overflow-y:auto;max-height:700px;">
        <h4 style="margin:0 0 8px;">Available Variables</h4>
        <p style="color:#6b7280;margin-bottom:8px;">Use <code>{{variable}}</code> syntax. For lists use <code>{{#each lines}}...{{/each}}</code>. For conditionals use <code>{{#if var}}...{{/if}}</code>.</p>

        <div style="margin-bottom:8px;"><strong>Company</strong><br>
            <code>{{company_name}}</code><br><code>{{company_address}}</code><br><code>{{company_phone}}</code></div>

        <div style="margin-bottom:8px;"><strong>Customer/Supplier</strong><br>
            <code>{{customer_name}}</code><br><code>{{supplier_name}}</code><br><code>{{billing_address}}</code><br><code>{{ship_to_name}}</code><br><code>{{ship_to_address}}</code></div>

        <div style="margin-bottom:8px;"><strong>Document Numbers</strong><br>
            <code>{{invoice_number}}</code><br><code>{{po_number}}</code><br><code>{{quote_number}}</code><br><code>{{so_number}}</code><br><code>{{shipment_number}}</code><br><code>{{batch_number}}</code><br><code>{{scar_number}}</code></div>

        <div style="margin-bottom:8px;"><strong>Dates</strong><br>
            <code>{{invoice_date}}</code><br><code>{{due_date}}</code><br><code>{{order_date}}</code><br><code>{{quote_date}}</code><br><code>{{expiration_date}}</code></div>

        <div style="margin-bottom:8px;"><strong>Totals</strong><br>
            <code>{{subtotal}}</code><br><code>{{total_due}}</code><br><code>{{freight_amount}}</code><br><code>{{deposit_amount}}</code></div>

        <div style="margin-bottom:8px;"><strong>Line Items</strong><br>
            <code>{{#each lines}}</code><br>
            &nbsp;&nbsp;<code>{{item_code}}</code><br>
            &nbsp;&nbsp;<code>{{description}}</code><br>
            &nbsp;&nbsp;<code>{{quantity}}</code><br>
            &nbsp;&nbsp;<code>{{unit_price}}</code><br>
            &nbsp;&nbsp;<code>{{extended}}</code><br>
            <code>{{/each}}</code></div>

        <div><strong>Conditionals</strong><br>
            <code>{{#if external_notes}}...{{/if}}</code><br>
            <code>{{#if freight_amount}}...{{/if}}</code></div>
    </div>
</div>
