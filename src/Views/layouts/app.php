<?php
/**
 * Main application layout — sidebar, header, toast, content area.
 * Views are rendered inside $__content via output buffering in BaseController::renderView().
 * Variables available: $__content, $__pageTitle, plus all extracted view data.
 */
$__user = $_SESSION['user'] ?? null;
$__isDark = $_COOKIE['theme'] ?? ($__user['theme'] ?? 'dark');
$__initials = '';
if (!empty($__user['full_name'])) {
    $__parts = explode(' ', $__user['full_name']);
    $__initials = strtoupper(substr($__parts[0],0,1) . (isset($__parts[1]) ? substr($__parts[1],0,1) : ''));
}
$__currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$__onDashboard = ($__currentPath === '/' || $__currentPath === '/dashboard');

// Facility data
$__facDb = \PrecisionInk\Controllers\BaseController::getSharedDb();
$__facSvc = $__facDb ? new \App\Services\FacilityService($__facDb) : null;
$__userFacilities = [];
$__activeFacility = null;
if ($__facSvc && $__user) {
    try {
        $__userFacilities = $__facSvc->getUserFacilities((int)$__user['id']);
        $__activeFacility = $__facSvc->getActiveFacility((int)$__user['id']);
    } catch (\Throwable $e) {}
}

// Nav helper
$__navActive = function(string $prefix) use ($__currentPath) {
    if ($prefix === '/items' && strpos($__currentPath, '/items') === 0) return true;
    return strpos($__currentPath, $prefix) === 0;
};

$__navGroups = [
    ['label'=>'','items'=>[
        ['label'=>'Items','url'=>'/items','prefix'=>'/items','icon'=>'inventory'],
    ]],
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
        ['label'=>'Requisitions','url'=>'/purchase-requisitions','prefix'=>'/purchase-requisitions','icon'=>'req'],
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
        ['label'=>'RMA','url'=>'/rma','prefix'=>'/rma','icon'=>'scar'],
    ]],
    ['label'=>'Finance','items'=>[
        ['label'=>'Price Lists','url'=>'/price-lists','prefix'=>'/price-lists','icon'=>'finance'],
        ['label'=>'QB Sync','url'=>'/qb-sync','prefix'=>'/qb-sync','icon'=>'finance'],
    ]],
    ['label'=>'Reports','items'=>[
        ['label'=>'All Reports','url'=>'/reports','prefix'=>'/reports','icon'=>'report'],
    ]],
    ['label'=>'Admin','items'=>[
        ['label'=>'Settings','url'=>'/settings/company','prefix'=>'/settings','icon'=>'settings'],
        ['label'=>'Users','url'=>'/users','prefix'=>'/users','icon'=>'customer'],
    ]],
];

$__iconSvg = [
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
];
?>
<!DOCTYPE html>
<html lang="en" class="<?= $__isDark === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($__pageTitle ?? 'Precision Ink ERP') ?> — Precision Ink</title>
  <link rel="stylesheet" href="/css/app.css">
  <link rel="stylesheet" href="/assets/css/settings.css">
  <style>
    /* ── Layout integration for legacy views ─────────────────── */
    .page-content .app-header { display: none !important; }
    .page-content .toast[id="toast"] { display: none !important; }
    /* Override old max-width wrappers — content already has page-content padding */
    .page-content div[style*="max-width:1200px"],
    .page-content div[style*="max-width: 1200px"],
    .page-content div[style*="max-width:1400px"],
    .page-content div[style*="max-width:1000px"],
    .page-content div[style*="max-width:1500px"],
    .page-content div[style*="max-width:1600px"],
    .page-content div[style*="max-width:700px"] {
      max-width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; padding-left: 0 !important; padding-right: 0 !important;
    }
    /* Settings page: remove its sticky header offset since our header replaces it */
    .page-content .settings-nav { top: 0; height: calc(100vh - var(--header-height) - 48px); }
    .page-content .settings-wrapper { min-height: calc(100vh - var(--header-height) - 48px); }

    /* ── Dark mode for legacy views ──────────────────────────── */
    .dark .page-content { color: #f1f5f9; }
    .dark .page-content h1, .dark .page-content h2, .dark .page-content h3,
    .dark .page-content h4, .dark .page-content h5 { color: #f1f5f9; }
    .dark .page-content td, .dark .page-content th { color: #e2e8f0; }
    .dark .page-content label { color: #94a3b8; }
    .dark .page-content .data-table { background: #1e293b; border-color: #334155; }
    .dark .page-content .data-table th { background: #162032; color: #94a3b8; border-color: #334155; }
    .dark .page-content .data-table td { border-color: #1e293b; }
    .dark .page-content .data-table tbody tr:hover td { background: #162032; }
    .dark .page-content input, .dark .page-content select, .dark .page-content textarea {
      background: #374151 !important; color: #f1f5f9 !important; border-color: #4b5563 !important;
    }
    .dark .page-content a { color: #60a5fa; }
    .dark .page-content .settings-nav { background: #1e293b; border-color: #334155; }
    .dark .page-content .nav-link { color: #e2e8f0; }
    .dark .page-content a.nav-link:hover { background: #162032; color: #f1f5f9; }
    .dark .page-content .nav-link.active { background: rgba(59,130,246,0.15); color: #60a5fa; border-left-color: #60a5fa; }
    .dark .page-content .nav-heading { color: #94a3b8; border-color: #334155; }
    .dark .page-content .nav-group-title { color: #64748b; }
    .dark .page-content .settings-content { color: #f1f5f9; }
    .dark .page-content .form-section, .dark .page-content .dropdown-add-form {
      background: #1e293b; border-color: #334155;
    }
    .dark .page-content .form-section h2 { color: #f1f5f9; border-color: #334155; }
    .dark .page-content .settings-card { background: #1e293b; border-color: #334155; }
    .dark .page-content .settings-card h3 { color: #60a5fa; }
    .dark .page-content .settings-card p { color: #94a3b8; }
    .dark .page-content .btn-secondary { background: #1e293b; color: #e2e8f0; border-color: #334155; }
    .dark .page-content .btn-secondary:hover { background: #162032; }
    .dark .page-content .tab-btn, .dark .page-content .tab-bar button {
      color: #94a3b8; background: transparent; border-color: transparent;
    }
    .dark .page-content .tab-btn.active, .dark .page-content .tab-bar button.active {
      color: #60a5fa; background: transparent;
    }
    .dark .page-content .tab-bar { border-color: #334155; }
    .dark .page-content .inline-form { background: transparent; }
    .dark .page-content .help-text { color: #64748b; }
    .dark .page-content .checkbox-label { color: #94a3b8; }
    .dark .page-content .empty-state { color: #64748b; }
    .dark .page-content .form-group label { color: #94a3b8; }
    .dark .page-content .inline-field label { color: #94a3b8; }
    .dark .page-content .rich-toolbar { background: #162032; border-color: #334155; }
    .dark .page-content .rich-toolbar button { background: #1e293b; color: #e2e8f0; border-color: #334155; }
    .dark .page-content .rich-editor { color: #f1f5f9; }
    .dark .page-content .rich-editor-wrap { border-color: #334155; }
  </style>
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
      <a href="/dashboard" class="nav-item <?= $__onDashboard ? 'active' : '' ?>" data-label="Dashboard">
        <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span class="nav-item-label">Dashboard</span>
      </a>
      <?php foreach ($__navGroups as $__group): ?>
      <div class="nav-group">
        <?php if ($__group['label']): ?><div class="nav-group-label"><?= htmlspecialchars($__group['label']) ?></div><?php endif; ?>
        <?php foreach ($__group['items'] as $__item): ?>
        <a href="<?= $__item['url'] ?>" class="nav-item <?= $__navActive($__item['prefix']) ? 'active' : '' ?>" data-label="<?= htmlspecialchars($__item['label']) ?>">
          <?= $__iconSvg[$__item['icon']] ?? '' ?>
          <span class="nav-item-label"><?= htmlspecialchars($__item['label']) ?></span>
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
      <?php if (!$__onDashboard): ?>
      <a href="/dashboard" class="header-back-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Dashboard
      </a>
      <?php endif; ?>
      <div class="header-breadcrumb"><span class="current"><?= htmlspecialchars($__pageTitle ?? '') ?></span></div>
      <div class="header-right">
        <div class="global-search">
          <svg class="global-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="global-search-input" placeholder="Search... (/)" autocomplete="off">
          <div class="global-search-results" id="searchResults" style="display:none"></div>
        </div>
        <?php if (count($__userFacilities) > 1): ?>
        <div class="facility-badge">
          <form method="POST" action="/facility/switch" id="facility-form" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <select name="facility_id" onchange="document.getElementById('facility-form').submit()">
              <?php foreach ($__userFacilities as $__f): ?>
              <option value="<?= $__f['id'] ?>" <?= $__f['id'] == ($__activeFacility['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($__f['name']) ?></option>
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
            <div class="user-avatar"><?= htmlspecialchars($__initials) ?></div>
            <span class="user-name"><?= htmlspecialchars($__user['full_name'] ?? 'User') ?></span>
          </div>
          <div class="user-dropdown" style="display:none">
            <a href="/settings/company">Settings</a>
            <div class="user-dropdown-sep"></div>
            <a href="/auth/logout">Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <!-- Toast from session -->
    <?php if (isset($_SESSION['toast'])): ?>
    <script>document.addEventListener('DOMContentLoaded',function(){showToast(<?=json_encode($_SESSION['toast']['message'])?>,<?=json_encode($_SESSION['toast']['type'])?>)});</script>
    <?php unset($_SESSION['toast']); endif; ?>

    <main class="page-content">
      <?= $__content ?>
    </main>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
function toggleSidebar(){var s=document.getElementById('sidebar');s.classList.toggle('collapsed');localStorage.setItem('sidebar_collapsed',s.classList.contains('collapsed')?'1':'0');}
if(localStorage.getItem('sidebar_collapsed')==='1'){document.getElementById('sidebar').classList.add('collapsed');}
function toggleDarkMode(){document.documentElement.classList.toggle('dark');var d=document.documentElement.classList.contains('dark');document.cookie='theme='+(d?'dark':'light')+';path=/;max-age=31536000';fetch('/profile/set-theme',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'theme='+(d?'dark':'light')});}
function showToast(m,t,d){t=t||'success';d=d||4000;var c=document.getElementById('toast-container');if(!c)return;var e=document.createElement('div');e.className='toast toast-'+t;e.innerHTML='<span style="flex:1">'+m+'</span><button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;padding:0;margin-left:8px;font-size:16px">×</button>';c.appendChild(e);setTimeout(function(){e.remove();},d);}
document.addEventListener('click',function(e){var d=document.querySelector('.user-dropdown');if(d&&!e.target.closest('.user-menu'))d.style.display='none';});
// Global search
var _si=document.getElementById('global-search-input'),_sr=document.getElementById('searchResults'),_st;
if(_si){_si.addEventListener('input',function(){clearTimeout(_st);var q=this.value.trim();if(q.length<2){_sr.style.display='none';_sr.innerHTML='';return;}_st=setTimeout(function(){fetch('/search?q='+encodeURIComponent(q)).then(function(r){return r.json();}).then(function(g){_sr.innerHTML='';g.forEach(function(gr){_sr.innerHTML+='<div class="search-group-label">'+gr.label+'</div>';gr.items.forEach(function(i){_sr.innerHTML+='<a href="'+i.url+'" class="search-result-item"><div><div class="search-result-code">'+i.code+'</div><div class="search-result-desc">'+(i.description||'')+'</div></div></a>';});});_sr.style.display=g.length?'block':'none';});},250);});document.addEventListener('click',function(e){if(!_si.contains(e.target)&&!_sr.contains(e.target))_sr.style.display='none';});document.addEventListener('keydown',function(e){if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)){e.preventDefault();_si.focus();}if(e.key==='Escape'){_sr.style.display='none';_si.blur();}});}
</script>
</body>
</html>
