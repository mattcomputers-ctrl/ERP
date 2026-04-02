<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .widget{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;}.widget h3{margin:0 0 8px;font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;}
        .stat-big{font-size:28px;font-weight:700;color:#1d4ed8;}.stat-label{font-size:12px;color:#6b7280;}
        .dash-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}
        .alert-urgent{background:#fef2f2;border-color:#fecaca;color:#991b1b;}.alert-warning{background:#fefce8;border-color:#fde68a;color:#854d0e;}.alert-info{background:#eff6ff;border-color:#bfdbfe;color:#1e40af;}
        .quick-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#fff;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:500;color:#374151;text-decoration:none;cursor:pointer;}.quick-btn:hover{background:#f9fafb;border-color:#9ca3af;}
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex;align-items:center;gap:12px;">
            <!-- Global Search -->
            <div style="position:relative;" x-data="{query:'',results:[]}">
                <input type="text" id="global-search" x-model="query" @input.debounce.300ms="if(query.length>=2)fetch('/search?q='+encodeURIComponent(query)).then(r=>r.json()).then(d=>results=d);else results=[]"
                       placeholder="Search... (/)" style="width:220px;padding:4px 10px;border-radius:6px;border:1px solid #555;background:rgba(255,255,255,0.1);color:#fff;font-size:13px;" autocomplete="off">
                <div x-show="results.length>0" @click.outside="results=[]" style="position:absolute;top:100%;right:0;width:320px;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);margin-top:4px;z-index:200;overflow:hidden;">
                    <template x-for="group in results" :key="group.type">
                        <div>
                            <div style="padding:4px 12px;font-size:11px;font-weight:600;color:#6b7280;background:#f9fafb;text-transform:uppercase;" x-text="group.label"></div>
                            <template x-for="item in group.items" :key="item.url">
                                <a :href="item.url" style="display:block;padding:6px 12px;text-decoration:none;color:#111;font-size:13px;" onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background=''">
                                    <strong x-text="item.code"></strong> <span style="color:#6b7280;" x-text="item.description"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            <!-- Dark Mode Toggle -->
            <button onclick="toggleDarkMode()" style="background:none;border:none;cursor:pointer;color:#fff;font-size:18px;" title="Toggle dark mode">
                <span id="icon-moon">&#9790;</span><span id="icon-sun" style="display:none;">&#9788;</span>
            </button>
            <!-- Shortcuts -->
            <button onclick="document.getElementById('shortcuts-modal').classList.toggle('visible')" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:12px;" title="Keyboard shortcuts (?)">&#9000;</button>
            <?php $u=$_SESSION['user']??null;if($u):?><span class="user-name"><?=htmlspecialchars($u['full_name']??$u['username']??'')?></span><?php endif;?>
        </div>
    </header>

    <div style="max-width:1200px;margin:24px auto;padding:0 16px;" x-data="{dismissed: JSON.parse(localStorage.getItem('dismissed_announcements')||'[]')}">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <!-- Announcements -->
        <?php foreach($announcements??[] as $a):
            $cls=match($a['priority']??'INFO'){'URGENT'=>'alert-urgent','WARNING'=>'alert-warning',default=>'alert-info'};
        ?>
        <div class="widget <?=$cls?>" style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;" x-show="!dismissed.includes(<?=$a['id']?>)">
            <div><strong><?=htmlspecialchars($a['title'])?></strong> — <?=htmlspecialchars($a['message'])?></div>
            <button @click="dismissed.push(<?=$a['id']?>);localStorage.setItem('dismissed_announcements',JSON.stringify(dismissed))" style="background:none;border:none;cursor:pointer;font-size:18px;color:inherit;">&times;</button>
        </div>
        <?php endforeach;?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;">Dashboard</h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if($this->checkPermission('batch_tickets','create')):?><a href="/batches/create" class="quick-btn">+ Batch</a><?php endif;?>
                <?php if($this->checkPermission('sales_orders','create')):?><a href="/orders/create" class="quick-btn">+ Order</a><?php endif;?>
                <?php if($this->checkPermission('purchase_orders','create')):?><a href="/purchase-orders/create" class="quick-btn">+ PO</a><?php endif;?>
                <a href="/crm/dashboard" class="quick-btn">CRM</a>
                <a href="/mrp" class="quick-btn">MRP</a>
                <a href="/settings" class="quick-btn">Settings</a>
            </div>
        </div>

        <div class="dash-grid">
            <!-- Overdue Tasks -->
            <?php if(!empty($overdue_tasks)):?>
            <div class="widget" style="border-left:4px solid #dc2626;">
                <h3>Overdue Tasks (<?=count($overdue_tasks)?>)</h3>
                <?php foreach($overdue_tasks as $t):?>
                <div style="font-size:13px;margin-bottom:4px;"><strong><?=htmlspecialchars($t['title'])?></strong> — <?=htmlspecialchars($t['company_name'])?> <span style="color:#dc2626;font-size:11px;">due <?=date('M j',strtotime($t['due_date']))?></span></div>
                <?php endforeach;?>
                <a href="/crm/tasks" style="font-size:12px;color:#2563eb;">View all tasks &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Pending Requisitions -->
            <?php if(isset($pending_reqs) && $pending_reqs > 0):?>
            <div class="widget" style="border-left:4px solid #f59e0b;">
                <h3>Pending Approvals</h3>
                <div class="stat-big"><?=$pending_reqs?></div>
                <div class="stat-label">requisitions awaiting approval</div>
                <a href="/purchase-requisitions?status=SUBMITTED" style="font-size:12px;color:#2563eb;">Review &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Batch Stats -->
            <?php if(isset($batch_stats)):?>
            <div class="widget">
                <h3>Batch Tickets</h3>
                <div style="display:flex;gap:16px;">
                    <div><div class="stat-big"><?=$batch_stats['OPEN']?></div><div class="stat-label">Open</div></div>
                    <div><div class="stat-big"><?=$batch_stats['IN_PROGRESS']?></div><div class="stat-label">In Progress</div></div>
                </div>
                <?php if($batch_stats['rush']>0):?><div style="margin-top:8px;padding:4px 8px;background:#fef2f2;border-radius:4px;color:#dc2626;font-size:12px;font-weight:600;"><?=$batch_stats['rush']?> RUSH batch(es)</div><?php endif;?>
                <a href="/batches" style="font-size:12px;color:#2563eb;">View batches &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Orders Due -->
            <?php if(isset($orders_due_today)):?>
            <div class="widget">
                <h3>Shipping</h3>
                <div style="display:flex;gap:16px;">
                    <div><div class="stat-big"><?=$orders_due_today?></div><div class="stat-label">Ship Today</div></div>
                    <div><div class="stat-big"><?=$orders_due_week?></div><div class="stat-label">This Week</div></div>
                </div>
                <?php if($backorder_count>0):?><div style="margin-top:8px;padding:4px 8px;background:#fef9c3;border-radius:4px;color:#854d0e;font-size:12px;"><?=$backorder_count?> backordered line(s)</div><?php endif;?>
                <a href="/orders?status=CONFIRMED" style="font-size:12px;color:#2563eb;">View orders &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Sales Stats -->
            <?php if(isset($sales_stats)):
                $thisMonth=(float)($sales_stats['this_month']??0);$lastMonth=(float)($sales_stats['last_month']??0);
                $trend=$thisMonth>$lastMonth?'up':($thisMonth<$lastMonth?'down':'flat');
            ?>
            <div class="widget">
                <h3>Sales (Invoiced)</h3>
                <div style="display:flex;gap:16px;">
                    <div><div class="stat-big">$<?=number_format($thisMonth,0)?></div><div class="stat-label">This Month</div></div>
                    <div><div style="font-size:20px;font-weight:600;color:#6b7280;">$<?=number_format($lastMonth,0)?></div><div class="stat-label">Last Month</div></div>
                </div>
                <?php if(isset($ar_total)):?><div style="margin-top:8px;font-size:12px;color:#6b7280;">Outstanding AR: <strong>$<?=number_format($ar_total,2)?></strong></div><?php endif;?>
            </div>
            <?php endif;?>

            <!-- Low Stock -->
            <?php if(!empty($low_stock)):?>
            <div class="widget" style="border-left:4px solid #f59e0b;">
                <h3>Low Stock (<?=count($low_stock)?>)</h3>
                <?php foreach($low_stock as $ls):?>
                <div style="font-size:12px;margin-bottom:2px;"><strong><?=htmlspecialchars($ls['item_code'])?></strong>: <?=number_format((float)$ls['on_hand'],2)?> / <?=number_format((float)$ls['reorder_min'],2)?> min</div>
                <?php endforeach;?>
                <a href="/mrp" style="font-size:12px;color:#2563eb;">View MRP &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Pending Inspections -->
            <?php if(isset($pending_inspections) && $pending_inspections > 0):?>
            <div class="widget">
                <h3>QC Inspections</h3>
                <div class="stat-big"><?=$pending_inspections?></div>
                <div class="stat-label">lots pending inspection</div>
                <a href="/qc/inspection" style="font-size:12px;color:#2563eb;">Inspect &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Overdue SCARs -->
            <?php if(isset($overdue_scars) && $overdue_scars > 0):?>
            <div class="widget" style="border-left:4px solid #dc2626;">
                <h3>Overdue SCARs</h3>
                <div class="stat-big" style="color:#dc2626;"><?=$overdue_scars?></div>
                <div class="stat-label">past due date</div>
                <a href="/scars?status=OPEN" style="font-size:12px;color:#2563eb;">View &rarr;</a>
            </div>
            <?php endif;?>

            <!-- Credit Holds -->
            <?php if(isset($credit_holds) && $credit_holds > 0):?>
            <div class="widget">
                <h3>Credit Holds</h3>
                <div class="stat-big"><?=$credit_holds?></div>
                <div class="stat-label">customers on hold</div>
                <a href="/customers?active=1" style="font-size:12px;color:#2563eb;">View &rarr;</a>
            </div>
            <?php endif;?>
        </div>

        <!-- Navigation Links -->
        <div style="margin-top:24px;padding:16px;background:#f9fafb;border-radius:8px;">
            <h3 style="margin:0 0 12px;font-size:14px;color:#374151;">Quick Navigation</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/items" class="quick-btn">Items</a>
                <a href="/customers" class="quick-btn">Customers</a>
                <a href="/suppliers" class="quick-btn">Suppliers</a>
                <a href="/inventory" class="quick-btn">Inventory</a>
                <a href="/purchase-requisitions" class="quick-btn">Requisitions</a>
                <a href="/purchase-orders" class="quick-btn">Purchase Orders</a>
                <a href="/orders" class="quick-btn">Sales Orders</a>
                <a href="/quotes" class="quick-btn">Quotes</a>
                <a href="/shipping/create" class="quick-btn">Shipping</a>
                <a href="/invoices" class="quick-btn">Invoices</a>
                <a href="/batches" class="quick-btn">Batches</a>
                <a href="/pick-lists" class="quick-btn">Pick Lists</a>
                <a href="/qc/specs" class="quick-btn">QC</a>
                <a href="/scars" class="quick-btn">SCARs</a>
                <a href="/rma" class="quick-btn">RMA</a>
                <a href="/transfers" class="quick-btn">Transfers</a>
                <a href="/repack" class="quick-btn">Repack</a>
                <a href="/consignment" class="quick-btn">Consignment</a>
                <a href="/traceability" class="quick-btn">Traceability</a>
                <a href="/reports" class="quick-btn">Reports</a>
                <a href="/import" class="quick-btn">Import</a>
                <a href="/export" class="quick-btn">Export</a>
            </div>
        </div>
    </div>

    <!-- Keyboard Shortcuts Modal -->
    <div id="shortcuts-modal" class="shortcuts-modal" onclick="if(event.target===this)this.classList.remove('visible')">
        <div class="shortcuts-box">
            <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;">Keyboard Shortcuts</h3>
            <table style="width:100%;font-size:13px;">
                <tr><td style="width:80px;"><span class="shortcut-key">?</span></td><td>Show this help</td></tr>
                <tr><td><span class="shortcut-key">/</span></td><td>Focus search</td></tr>
                <tr><td><span class="shortcut-key">Ctrl+K</span></td><td>Focus search</td></tr>
                <tr><td><span class="shortcut-key">Ctrl+S</span></td><td>Save form</td></tr>
                <tr><td><span class="shortcut-key">n</span></td><td>New record (list pages)</td></tr>
                <tr><td><span class="shortcut-key">e</span></td><td>Edit record (view pages)</td></tr>
                <tr><td><span class="shortcut-key">Esc</span></td><td>Close modals</td></tr>
            </table>
            <button onclick="document.getElementById('shortcuts-modal').classList.remove('visible')" class="btn btn-secondary" style="width:100%;margin-top:12px;">Close</button>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);</script>
</body>
</html>
