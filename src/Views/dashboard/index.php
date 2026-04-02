<?php
$isDark = $_COOKIE['theme'] ?? ($currentUser['theme'] ?? 'dark');
$initials = '';
if (!empty($currentUser['full_name'])) {
    $parts = explode(' ', $currentUser['full_name']);
    $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}
$currentPath = '/dashboard';

// Card display names
$cardNames = [
    'orders_to_ship_today'=>'Shipping Today','orders_to_ship_week'=>'Shipping This Week',
    'unfulfillable_orders'=>'Unfulfillable Orders','uncovered_demand'=>'Uncovered Demand',
    'open_batches'=>'Open Batches','rush_batches'=>'RUSH Batches','low_stock'=>'Low Stock',
    'pending_inspections'=>'Pending Inspections','pending_requisitions'=>'Pending Requisitions',
    'overdue_scars'=>'Overdue SCARs','my_tasks'=>'My Tasks','ar_summary'=>'AR Summary',
    'sales_mtd'=>'Sales MTD','backorder_count'=>'Backorders','announcements'=>'Announcements',
];

// Nav structure
$navGroups = [
    ['label'=>'Production','items'=>[
        ['label'=>'Batch Tickets','url'=>'/batches','prefix'=>'/batches','icon'=>'batch'],
        ['label'=>'MRP','url'=>'/mrp','prefix'=>'/mrp','icon'=>'mrp'],
        ['label'=>'Schedule','url'=>'/production/schedule','prefix'=>'/production','icon'=>'calendar'],
    ]],
    ['label'=>'Inventory','items'=>[
        ['label'=>'Stock on Hand','url'=>'/inventory','prefix'=>'/inventory','icon'=>'inventory'],
        ['label'=>'Transfers','url'=>'/transfers','prefix'=>'/transfers','icon'=>'transfer'],
        ['label'=>'Traceability','url'=>'/traceability','prefix'=>'/traceability','icon'=>'trace'],
    ]],
    ['label'=>'Purchasing','items'=>[
        ['label'=>'Purchase Orders','url'=>'/purchase-orders','prefix'=>'/purchase-orders','icon'=>'po'],
        ['label'=>'Requisitions','url'=>'/requisitions','prefix'=>'/requisitions','icon'=>'req'],
        ['label'=>'Suppliers','url'=>'/suppliers','prefix'=>'/suppliers','icon'=>'supplier'],
    ]],
    ['label'=>'Sales','items'=>[
        ['label'=>'Sales Orders','url'=>'/orders','prefix'=>'/orders','icon'=>'order'],
        ['label'=>'Quotes','url'=>'/quotes','prefix'=>'/quotes','icon'=>'quote'],
        ['label'=>'Customers','url'=>'/customers','prefix'=>'/customers','icon'=>'customer'],
        ['label'=>'Shipping','url'=>'/shipping','prefix'=>'/shipping','icon'=>'shipping'],
        ['label'=>'Invoices','url'=>'/invoices','prefix'=>'/invoices','icon'=>'invoice'],
    ]],
    ['label'=>'Quality','items'=>[
        ['label'=>'Inspections','url'=>'/qc/inspection','prefix'=>'/qc','icon'=>'qc'],
        ['label'=>'QC Specs','url'=>'/qc/specs','prefix'=>'/qc/specs','icon'=>'qc'],
        ['label'=>'SCARs','url'=>'/scars','prefix'=>'/scars','icon'=>'scar'],
    ]],
    ['label'=>'Finance','items'=>[
        ['label'=>'Price Lists','url'=>'/price-lists','prefix'=>'/price-lists','icon'=>'finance'],
        ['label'=>'QB Sync','url'=>'/qb-sync','prefix'=>'/qb-sync','icon'=>'finance'],
        ['label'=>'Reports','url'=>'/reports','prefix'=>'/reports','icon'=>'report'],
    ]],
    ['label'=>'Admin','items'=>[
        ['label'=>'Items','url'=>'/items','prefix'=>'/items','icon'=>'inventory'],
        ['label'=>'Recipes','url'=>'/items','prefix'=>'/recipes','icon'=>'recipe'],
        ['label'=>'Settings','url'=>'/settings/company','prefix'=>'/settings','icon'=>'settings'],
    ]],
];

// SVG icons map (simple)
$iconSvg = [
    'batch'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
    'mrp'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
    'calendar'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'inventory'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>',
    'transfer'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>',
    'trace'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
    'po'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    'req'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
    'supplier'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'order'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>',
    'quote'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    'customer'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
    'shipping'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'invoice'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    'qc'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    'scar'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
    'finance'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
    'report'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
    'settings'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
    'recipe'=>'<svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en" class="<?= $isDark === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Precision Ink ERP</title>
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<div class="app-layout">
  <!-- SIDEBAR -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-logo-mark">PI</div>
      <div class="sidebar-logo-text">Precision Ink<small>ERP System</small></div>
    </div>
    <div class="sidebar-nav">
      <a href="/dashboard" class="nav-item active" data-label="Dashboard">
        <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span class="nav-item-label">Dashboard</span>
      </a>
      <?php foreach ($navGroups as $group): ?>
      <div class="nav-group">
        <div class="nav-group-label"><?= htmlspecialchars($group['label']) ?></div>
        <?php foreach ($group['items'] as $item): ?>
        <a href="<?= $item['url'] ?>" class="nav-item" data-label="<?= htmlspecialchars($item['label']) ?>">
          <?= $iconSvg[$item['icon']] ?? '' ?>
          <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="sidebar-collapse-btn">
      <button onclick="toggleSidebar()" title="Collapse sidebar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
    </div>
  </nav>

  <!-- MAIN -->
  <div class="main-wrapper">
    <header class="top-header">
      <div class="header-breadcrumb"><span class="current">Dashboard</span></div>
      <div class="header-right">
        <!-- Search -->
        <div class="global-search">
          <svg class="global-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="global-search-input" placeholder="Search... (/)" autocomplete="off">
          <div class="global-search-results" id="searchResults" style="display:none"></div>
        </div>
        <?php if (count($userFacilities ?? []) > 1): ?>
        <div class="facility-badge">
          <form method="POST" action="/facility/switch" id="facility-form" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <select name="facility_id" onchange="document.getElementById('facility-form').submit()">
              <?php foreach ($userFacilities as $f): ?>
              <option value="<?= $f['id'] ?>" <?= $f['id'] == ($activeFacility['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <?php endif; ?>
        <button class="dark-mode-btn" onclick="toggleDarkMode()" title="Toggle dark mode">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        </button>
        <div class="user-menu" style="position:relative">
          <div class="user-menu-trigger" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <span class="user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></span>
          </div>
          <div class="user-dropdown" style="display:none">
            <a href="/settings/company">Settings</a>
            <div class="user-dropdown-sep"></div>
            <a href="/auth/logout">Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <?php if(isset($_SESSION['toast'])):?>
    <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?=json_encode($_SESSION['toast']['message'])?>,<?=json_encode($_SESSION['toast']['type'])?>)});</script>
    <?php unset($_SESSION['toast']);endif;?>

    <main class="page-content">
      <!-- Dashboard Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Dashboard</h1>
          <p class="page-subtitle"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="page-actions">
          <button class="btn btn-secondary btn-sm" id="editModeBtn" onclick="toggleEditMode()">Customize</button>
        </div>
      </div>

      <!-- Card Grid -->
      <div class="dashboard-grid" id="dashboard-grid">
        <?php foreach ($layout as $card): ?>
        <?php if (empty($card['visible'])) continue; ?>
        <?php $data = $cardData[$card['card_key']] ?? []; ?>
        <div class="dashboard-card col-<?= (int)$card['cols'] ?>" data-key="<?= htmlspecialchars($card['card_key']) ?>"
             draggable="false">
          <div class="dashboard-card-handle">
            <div class="dashboard-card-title"><?= htmlspecialchars($cardNames[$card['card_key']] ?? $card['card_key']) ?></div>
            <div class="edit-controls" style="display:none;gap:4px">
              <button onclick="resizeCard('<?= $card['card_key'] ?>',-1)" class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:10px">◂</button>
              <button onclick="resizeCard('<?= $card['card_key'] ?>',1)" class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:10px">▸</button>
              <button onclick="hideCard('<?= $card['card_key'] ?>')" class="btn btn-ghost btn-sm" style="padding:2px 6px;color:var(--color-danger)">×</button>
            </div>
          </div>
          <div style="padding:12px 16px">
            <?php if (!empty($data['error'])): ?>
              <div style="color:var(--color-danger);font-size:12px"><?= htmlspecialchars($data['error']) ?></div>
            <?php else:
              $cardFile = __DIR__ . '/cards/' . $card['card_key'] . '.php';
              if (file_exists($cardFile)) { include $cardFile; }
              else { echo '<div style="color:var(--text-muted);font-size:12px">Card not configured</div>'; }
            endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
// Sidebar
function toggleSidebar(){
  var sb=document.getElementById('sidebar');
  sb.classList.toggle('collapsed');
  localStorage.setItem('sidebar_collapsed',sb.classList.contains('collapsed')?'1':'0');
}
if(localStorage.getItem('sidebar_collapsed')==='1'){document.getElementById('sidebar').classList.add('collapsed');}

// Dark mode
function toggleDarkMode(){
  document.documentElement.classList.toggle('dark');
  var isDark=document.documentElement.classList.contains('dark');
  document.cookie='theme='+(isDark?'dark':'light')+';path=/;max-age=31536000';
  fetch('/profile/set-theme',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'theme='+(isDark?'dark':'light')});
}

// Toast
function showToast(msg,type,dur){
  type=type||'success';dur=dur||4000;
  var c=document.getElementById('toast-container');if(!c)return;
  var t=document.createElement('div');t.className='toast toast-'+type;
  t.innerHTML='<span style="flex:1">'+msg+'</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;padding:0;margin-left:8px;font-size:16px;line-height:1">×</button>';
  c.appendChild(t);setTimeout(function(){t.remove();},dur);
}

// Search
var searchInput=document.getElementById('global-search-input');
var searchResults=document.getElementById('searchResults');
var searchTimer;
if(searchInput){
  searchInput.addEventListener('input',function(){
    clearTimeout(searchTimer);var q=this.value.trim();
    if(q.length<2){searchResults.style.display='none';searchResults.innerHTML='';return;}
    searchTimer=setTimeout(function(){
      fetch('/search?q='+encodeURIComponent(q)).then(function(r){return r.json();}).then(function(groups){
        searchResults.innerHTML='';
        groups.forEach(function(g){
          searchResults.innerHTML+='<div class="search-group-label">'+g.label+'</div>';
          g.items.forEach(function(item){
            searchResults.innerHTML+='<a href="'+item.url+'" class="search-result-item"><div><div class="search-result-code">'+item.code+'</div><div class="search-result-desc">'+(item.description||'')+'</div></div></a>';
          });
        });
        searchResults.style.display=groups.length?'block':'none';
      });
    },250);
  });
  document.addEventListener('click',function(e){if(!searchInput.contains(e.target)&&!searchResults.contains(e.target))searchResults.style.display='none';});
  document.addEventListener('keydown',function(e){
    if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)){e.preventDefault();searchInput.focus();}
    if(e.key==='Escape'){searchResults.style.display='none';searchInput.blur();}
  });
}

// User dropdown close
document.addEventListener('click',function(e){
  var dd=document.querySelector('.user-dropdown');
  if(dd&&!e.target.closest('.user-menu'))dd.style.display='none';
});

// Edit mode
var editMode=false;
var dashLayout=<?= json_encode(array_map(fn($c)=>['key'=>$c['card_key'],'cols'=>(int)$c['cols'],'visible'=>(bool)($c['visible']??true)],$layout)) ?>;
function toggleEditMode(){
  editMode=!editMode;
  document.getElementById('editModeBtn').textContent=editMode?'Done Editing':'Customize';
  document.querySelectorAll('.edit-controls').forEach(function(el){el.style.display=editMode?'flex':'none';});
  document.querySelectorAll('.dashboard-card').forEach(function(el){el.draggable=editMode;});
  document.getElementById('dashboard-grid').classList.toggle('dashboard-edit-mode',editMode);
}
var validCols=[3,4,6,8,12];
function resizeCard(key,dir){
  var card=dashLayout.find(function(c){return c.key===key;});if(!card)return;
  var idx=validCols.indexOf(card.cols);var ni=Math.max(0,Math.min(validCols.length-1,idx+dir));
  card.cols=validCols[ni];
  var el=document.querySelector('[data-key="'+key+'"]');
  if(el){el.className=el.className.replace(/col-\d+/,'col-'+card.cols);}
  saveLayout();
}
function hideCard(key){
  var card=dashLayout.find(function(c){return c.key===key;});if(card)card.visible=false;
  var el=document.querySelector('[data-key="'+key+'"]');if(el)el.remove();
  saveLayout();
}
function saveLayout(){
  fetch('/dashboard/layout',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'layout='+encodeURIComponent(JSON.stringify(dashLayout))+'&csrf_token=<?=$_SESSION['csrf_token']??''?>'});
}

// Drag and drop
var dragging=null;
document.getElementById('dashboard-grid').addEventListener('dragstart',function(e){
  if(!editMode)return;var card=e.target.closest('.dashboard-card');if(!card)return;
  dragging=card.dataset.key;e.dataTransfer.effectAllowed='move';
});
document.getElementById('dashboard-grid').addEventListener('dragover',function(e){if(editMode)e.preventDefault();});
document.getElementById('dashboard-grid').addEventListener('drop',function(e){
  if(!editMode||!dragging)return;e.preventDefault();
  var target=e.target.closest('.dashboard-card');if(!target||target.dataset.key===dragging)return;
  var grid=document.getElementById('dashboard-grid');
  var from=grid.querySelector('[data-key="'+dragging+'"]');
  var fi=Array.from(grid.children).indexOf(from);
  var ti=Array.from(grid.children).indexOf(target);
  if(fi<ti)target.after(from);else target.before(from);
  // Update layout order
  var newOrder=Array.from(grid.querySelectorAll('.dashboard-card')).map(function(el){return el.dataset.key;});
  dashLayout.sort(function(a,b){return newOrder.indexOf(a.key)-newOrder.indexOf(b.key);});
  saveLayout();dragging=null;
});
document.getElementById('dashboard-grid').addEventListener('dragend',function(){dragging=null;});
</script>
</body>
</html>
