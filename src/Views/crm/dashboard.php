<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .crm-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
        @media (max-width:1024px) { .crm-grid { grid-template-columns:1fr; } }
        .panel { background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
        .panel-header { padding:12px 16px; background:#f9fafb; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
        .panel-header h3 { margin:0; font-size:14px; }
        .panel-body { padding:0; max-height:400px; overflow-y:auto; }
        .panel-row { padding:8px 16px; border-bottom:1px solid #f3f4f6; font-size:13px; display:flex; justify-content:space-between; align-items:center; }
        .panel-row:last-child { border-bottom:none; }
        .panel-row:hover { background:#f9fafb; }
        .slide-over { position:fixed; top:0; right:0; width:400px; height:100vh; background:#fff; box-shadow:-4px 0 15px rgba(0,0,0,0.1); z-index:300; transform:translateX(100%); transition:transform 0.3s ease; padding:24px; overflow-y:auto; }
        .slide-over.open { transform:translateX(0); }
        .slide-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.3); z-index:250; display:none; }
        .slide-backdrop.open { display:block; }
        .search-wrap { position:relative; }
        .search-suggestions { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:4px; max-height:200px; overflow-y:auto; z-index:100; display:none; }
        .search-suggestions div { padding:6px 10px; font-size:13px; cursor:pointer; }
        .search-suggestions div:hover { background:#eff6ff; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div>
        <div class="header-right" style="display:flex; align-items:center; gap:16px;">
            <?php $user = $_SESSION['user'] ?? null; ?>
            <?php if ($user): ?><span class="user-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></span><?php endif; ?>
        </div>
    </header>
    <div style="max-width:1400px; margin:24px auto; padding:0 16px;" x-data="crmDash()">
        <?php if (isset($_SESSION['toast'])): ?>
            <div class="toast toast-<?= htmlspecialchars($_SESSION['toast']['type']) ?>" id="toast"><?= htmlspecialchars($_SESSION['toast']['message']) ?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div>
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h1 style="margin:0;">CRM Dashboard</h1>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-primary" @click="showPanel = 'activity'">+ Log Activity</button>
                <button class="btn btn-secondary" @click="showPanel = 'task'">+ Add Task</button>
                <a href="/crm/tasks" class="btn btn-secondary">All Tasks</a>
            </div>
        </div>

        <div class="crm-grid">
            <!-- Left: My Customers -->
            <div class="panel">
                <div class="panel-header"><h3>My Customers (<?= count($myCustomers) ?>)</h3></div>
                <div class="panel-body">
                    <?php if (empty($myCustomers)): ?>
                        <div class="panel-row" style="color:#9ca3af;">No customers assigned.</div>
                    <?php else: foreach ($myCustomers as $c):
                        $contactOverdue = $c['next_contact_date'] && $c['next_contact_date'] < date('Y-m-d');
                    ?>
                    <div class="panel-row" style="flex-direction:column; align-items:stretch;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <a href="/customers/<?= $c['id'] ?>" style="color:#2563eb; text-decoration:none; font-weight:500;"><?= htmlspecialchars($c['company_name']) ?></a>
                            <div style="display:flex; gap:4px;">
                                <?php if ($c['account_hold']): ?><span class="badge badge-danger" style="font-size:10px;">HOLD</span><?php endif; ?>
                                <?php if ((int)$c['open_tasks'] > 0): ?><span class="badge badge-info" style="font-size:10px;"><?= $c['open_tasks'] ?> tasks</span><?php endif; ?>
                            </div>
                        </div>
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                            Last activity: <?= $c['last_activity_date'] ? date('M j', strtotime($c['last_activity_date'])) : '—' ?>
                            | Last order: <?= $c['last_order_date'] ? date('M j', strtotime($c['last_order_date'])) : '—' ?>
                            | Next contact: <span style="<?= $contactOverdue ? 'color:#dc2626;font-weight:600;' : '' ?>"><?= $c['next_contact_date'] ? date('M j', strtotime($c['next_contact_date'])) : '—' ?></span>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Center: My Open Tasks -->
            <div class="panel">
                <div class="panel-header"><h3>My Open Tasks (<?= count($myTasks) ?>)</h3></div>
                <div class="panel-body" id="taskPanel">
                    <?php if (empty($myTasks)): ?>
                        <div class="panel-row" style="color:#9ca3af;">No open tasks.</div>
                    <?php else: foreach ($myTasks as $t):
                        $overdue = $t['due_date'] < date('Y-m-d');
                        $pb = match($t['priority']) { 'URGENT'=>'badge-danger','HIGH'=>'badge-warning','NORMAL'=>'badge-info','LOW'=>'badge-inactive', default=>'' };
                    ?>
                    <div class="panel-row" style="flex-direction:column; align-items:stretch;" id="task-<?= $t['id'] ?>">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <a href="/customers/<?= $t['cust_id'] ?>#tasks" style="color:#1d4ed8; text-decoration:none; font-weight:500;"><?= htmlspecialchars($t['title']) ?></a>
                                <span class="badge <?= $pb ?>" style="font-size:10px; margin-left:4px;"><?= $t['priority'] ?></span>
                            </div>
                            <button class="btn btn-sm btn-primary" style="font-size:11px; padding:2px 8px;"
                                    onclick="completeTask(<?= $t['id'] ?>, this)">&#10003;</button>
                        </div>
                        <div style="font-size:11px; color:#6b7280; margin-top:2px;">
                            <?= htmlspecialchars($t['company_name']) ?> — Due: <span style="<?= $overdue ? 'color:#dc2626;font-weight:600;' : '' ?>"><?= date('M j', strtotime($t['due_date'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Right: Quotes, Orders, Recent Activity -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                <!-- Open Quotes -->
                <div class="panel">
                    <div class="panel-header"><h3>Open Quotes (<?= count($myQuotes) ?>)</h3></div>
                    <div class="panel-body" style="max-height:180px;">
                        <?php if (empty($myQuotes)): ?>
                            <div class="panel-row" style="color:#9ca3af;">No open quotes.</div>
                        <?php else: foreach ($myQuotes as $q):
                            $nearExpiry = $q['expiration_date'] && $q['expiration_date'] <= date('Y-m-d', strtotime('+3 days'));
                        ?>
                        <div class="panel-row" style="<?= $nearExpiry ? 'background:#fefce8;' : '' ?>">
                            <div>
                                <span style="font-weight:500;"><?= htmlspecialchars($q['quote_number']) ?></span>
                                <span style="color:#6b7280; font-size:12px;"> <?= htmlspecialchars($q['company_name']) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-weight:500;">$<?= number_format((float)$q['total_value'], 2) ?></span>
                                <div style="font-size:11px; <?= $nearExpiry ? 'color:#dc2626;font-weight:600;' : 'color:#6b7280;' ?>">Exp: <?= date('M j', strtotime($q['expiration_date'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Open Orders -->
                <div class="panel">
                    <div class="panel-header"><h3>Open Orders (<?= count($myOrders) ?>)</h3></div>
                    <div class="panel-body" style="max-height:180px;">
                        <?php if (empty($myOrders)): ?>
                            <div class="panel-row" style="color:#9ca3af;">No open orders.</div>
                        <?php else: foreach ($myOrders as $o): ?>
                        <div class="panel-row">
                            <div>
                                <span style="font-weight:500;"><?= htmlspecialchars($o['so_number']) ?></span>
                                <span style="color:#6b7280; font-size:12px;"> <?= htmlspecialchars($o['company_name']) ?></span>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-weight:500;">$<?= number_format((float)$o['value'], 2) ?></span>
                                <div style="font-size:11px; color:#6b7280;">Ship: <?= $o['promised_ship_date'] ? date('M j', strtotime($o['promised_ship_date'])) : '—' ?></div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="panel">
                    <div class="panel-header"><h3>Recent Activity</h3></div>
                    <div class="panel-body" style="max-height:250px;">
                        <?php foreach ($recentActivity as $a):
                            $tb = match($a['activity_type']) { 'CALL'=>'badge-info','EMAIL'=>'badge-active','MEETING'=>'badge-warning','SITE_VISIT'=>'badge-warning','COMPLAINT'=>'badge-danger', default=>'badge-inactive' };
                        ?>
                        <div class="panel-row" style="flex-direction:column; align-items:stretch;">
                            <div style="display:flex; justify-content:space-between;">
                                <span><span class="badge <?= $tb ?>" style="font-size:10px;"><?= $a['activity_type'] ?></span> <a href="/customers/<?= $a['cust_id'] ?>#activities" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($a['company_name']) ?></a></span>
                                <span style="font-size:11px; color:#9ca3af;"><?= date('M j g:ia', strtotime($a['created_at'])) ?></span>
                            </div>
                            <div style="font-size:12px; color:#374151; margin-top:2px;"><?= htmlspecialchars($a['subject']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide-over backdrop -->
        <div class="slide-backdrop" :class="{ open: showPanel }" @click="showPanel = null"></div>

        <!-- Activity Slide-over -->
        <div class="slide-over" :class="{ open: showPanel === 'activity' }">
            <h2 style="margin:0 0 16px;">Log Activity</h2>
            <form @submit.prevent="submitActivity()">
                <div class="search-wrap" style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Customer <span style="color:red;">*</span></label>
                    <input type="text" x-model="actForm.customerSearch" @input.debounce.300ms="searchCustomers('act')" class="form-input" style="width:100%;" placeholder="Search customers..." autocomplete="off">
                    <input type="hidden" x-model="actForm.customer_id">
                    <div class="search-suggestions" x-show="actSugs.length > 0">
                        <template x-for="s in actSugs" :key="s.id">
                            <div @click="actForm.customer_id = s.id; actForm.customerSearch = s.display; actSugs = []" x-text="s.display"></div>
                        </template>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Type</label>
                    <select x-model="actForm.activity_type" class="form-input" style="width:100%;">
                        <option value="CALL">Call</option><option value="EMAIL">Email</option><option value="MEETING">Meeting</option>
                        <option value="SITE_VISIT">Site Visit</option><option value="DEMO">Demo</option><option value="COMPLAINT">Complaint</option>
                        <option value="PRICING_DISCUSSION">Pricing Discussion</option><option value="PROPOSAL_SENT">Proposal Sent</option><option value="GENERAL">General</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Subject <span style="color:red;">*</span></label>
                    <input type="text" x-model="actForm.subject" class="form-input" style="width:100%;" required>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Notes</label>
                    <textarea x-model="actForm.notes" rows="3" class="form-input" style="width:100%;"></textarea>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">Save Activity</button>
                    <button type="button" class="btn btn-secondary" @click="showPanel = null">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Task Slide-over -->
        <div class="slide-over" :class="{ open: showPanel === 'task' }">
            <h2 style="margin:0 0 16px;">Add Task</h2>
            <form @submit.prevent="submitTask()">
                <div class="search-wrap" style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Customer <span style="color:red;">*</span></label>
                    <input type="text" x-model="taskForm.customerSearch" @input.debounce.300ms="searchCustomers('task')" class="form-input" style="width:100%;" placeholder="Search customers..." autocomplete="off">
                    <input type="hidden" x-model="taskForm.customer_id">
                    <div class="search-suggestions" x-show="taskSugs.length > 0">
                        <template x-for="s in taskSugs" :key="s.id">
                            <div @click="taskForm.customer_id = s.id; taskForm.customerSearch = s.display; taskSugs = []" x-text="s.display"></div>
                        </template>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Title <span style="color:red;">*</span></label>
                    <input type="text" x-model="taskForm.title" class="form-input" style="width:100%;" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Due Date <span style="color:red;">*</span></label><input type="date" x-model="taskForm.due_date" class="form-input" style="width:100%;" required></div>
                    <div><label style="font-size:13px; font-weight:500; display:block; margin-bottom:4px;">Priority</label>
                        <select x-model="taskForm.priority" class="form-input" style="width:100%;"><option value="LOW">Low</option><option value="NORMAL" selected>Normal</option><option value="HIGH">High</option><option value="URGENT">Urgent</option></select>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">Create Task</button>
                    <button type="button" class="btn btn-secondary" @click="showPanel = null">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var toast = document.getElementById('toast'); if (toast) setTimeout(function(){toast.style.display='none';}, 4000);

    function completeTask(id, btn) {
        fetch('/crm/tasks/' + id + '/complete', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    var row = document.getElementById('task-' + id);
                    if (row) { row.style.opacity = '0.3'; setTimeout(function() { row.remove(); }, 300); }
                }
            });
    }

    function crmDash() {
        return {
            showPanel: null,
            actForm: { customer_id: '', customerSearch: '', activity_type: 'CALL', subject: '', notes: '' },
            taskForm: { customer_id: '', customerSearch: '', title: '', due_date: '', priority: 'NORMAL' },
            actSugs: [], taskSugs: [],

            searchCustomers(target) {
                var q = target === 'act' ? this.actForm.customerSearch : this.taskForm.customerSearch;
                if (q.length < 1) { if (target === 'act') this.actSugs = []; else this.taskSugs = []; return; }
                var self = this;
                fetch('/customers/search?q=' + encodeURIComponent(q) + '&limit=10')
                    .then(function(r) { return r.json(); })
                    .then(function(d) { if (target === 'act') self.actSugs = d; else self.taskSugs = d; });
            },

            submitActivity() {
                var self = this;
                var body = new FormData();
                body.append('customer_id', this.actForm.customer_id);
                body.append('activity_type', this.actForm.activity_type);
                body.append('subject', this.actForm.subject);
                body.append('notes', this.actForm.notes);
                fetch('/crm/activity/create', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) { self.showPanel = null; self.actForm = { customer_id: '', customerSearch: '', activity_type: 'CALL', subject: '', notes: '' }; location.reload(); }
                        else alert(d.message || 'Error');
                    });
            },

            submitTask() {
                var self = this;
                var body = new FormData();
                body.append('customer_id', this.taskForm.customer_id);
                body.append('title', this.taskForm.title);
                body.append('due_date', this.taskForm.due_date);
                body.append('priority', this.taskForm.priority);
                fetch('/crm/tasks/create', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) { self.showPanel = null; self.taskForm = { customer_id: '', customerSearch: '', title: '', due_date: '', priority: 'NORMAL' }; location.reload(); }
                        else alert(d.message || 'Error');
                    });
            }
        };
    }
    </script>
</body>
</html>
