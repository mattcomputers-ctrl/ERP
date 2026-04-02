<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($pkl['pkl_number'])?> — Precision Ink ERP</title><link rel="stylesheet" href="/assets/css/settings.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    .pick-line{padding:10px 12px;background:#f9fafb;border-radius:6px;margin-bottom:6px;border-left:4px solid #d1d5db;}
    .pick-line.picked{border-left-color:#16a34a;background:#f0fdf4;}
    .pick-line.partial{border-left-color:#f59e0b;background:#fefce8;}
    .pick-line.unable{border-left-color:#dc2626;background:#fef2f2;opacity:0.7;}
    .loc-badge{font-size:16px;font-weight:700;color:#1d4ed8;background:#dbeafe;padding:4px 10px;border-radius:4px;margin-right:8px;}
</style>
</head>
<body>
    <header class="app-header"><div class="header-left"><a href="/" class="app-logo">Precision Ink ERP</a></div><div class="header-right" style="display:flex;align-items:center;gap:16px;"><?php $user=$_SESSION['user']??null;if($user):?><span class="user-name"><?=htmlspecialchars($user['full_name']??'')?></span><?php endif;?></div></header>
    <div style="max-width:1000px;margin:24px auto;padding:0 16px;" x-data="pickApp()">
        <?php if(isset($_SESSION['toast'])):?><div class="toast toast-<?=htmlspecialchars($_SESSION['toast']['type'])?>" id="toast"><?=htmlspecialchars($_SESSION['toast']['message'])?><button class="toast-close" onclick="this.parentElement.remove()">&times;</button></div><?php unset($_SESSION['toast']);endif;?>

        <?php $sb=match($pkl['status']){'OPEN'=>'badge-info','IN_PROGRESS'=>'badge-warning','COMPLETE'=>'badge-active',default=>''};?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h1 style="margin:0;"><?=htmlspecialchars($pkl['pkl_number'])?> <span class="badge <?=$sb?>" style="font-size:14px;vertical-align:middle;"><?=$pkl['status']?></span></h1>
            <div style="display:flex;gap:8px;">
                <a href="/pick-lists" class="btn btn-secondary">&larr; Back</a>
                <a href="/pick-lists/<?=$pkl['id']?>/print" class="btn btn-secondary" target="_blank">Print PDF</a>
                <?php if($pkl['status']!=='COMPLETE'):?>
                <form method="POST" action="/pick-lists/<?=$pkl['id']?>/complete" style="display:inline;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Mark pick list complete?')">Complete</button>
                </form>
                <?php endif;?>
            </div>
        </div>

        <p style="font-size:13px;color:#6b7280;margin-bottom:8px;">Facility: <?=htmlspecialchars($pkl['facility_name'])?> | Created: <?=date('M j, Y',strtotime($pkl['created_at']))?> by <?=htmlspecialchars($pkl['created_by_name']??'')?></p>

        <!-- Sort toggle -->
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:16px;cursor:pointer;">
            <input type="checkbox" x-model="sortByLocation" class="form-checkbox"> Sort by storage location
        </label>

        <!-- Lines -->
        <template x-for="line in sortedLines" :key="line.id">
            <div class="pick-line" :class="line.status.toLowerCase()">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;margin-bottom:4px;">
                            <span class="loc-badge" x-text="line.location || '—'"></span>
                            <strong x-text="line.item_code" style="font-size:14px;"></strong>
                            <span style="color:#6b7280;margin-left:8px;" x-text="line.item_description"></span>
                            <span x-show="line.pack_name" style="margin-left:8px;" class="badge badge-inactive" x-text="line.pack_name"></span>
                        </div>
                        <div style="font-size:12px;color:#6b7280;">
                            SO: <span x-text="line.so_number"></span> — <span x-text="line.customer_name"></span>
                            | Qty to pick: <strong x-text="parseFloat(line.quantity_to_pick).toFixed(4)"></strong>
                            | Lot: <span x-text="line.lot_number || '—'"></span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge" :class="{'badge-info': line.status==='PENDING', 'badge-active': line.status==='PICKED', 'badge-warning': line.status==='PARTIAL', 'badge-danger': line.status==='UNABLE'}" x-text="line.status"></span>
                    </div>
                </div>

                <?php if($pkl['status']!=='COMPLETE'):?>
                <div x-show="line.status === 'PENDING'" style="display:flex;gap:8px;align-items:end;margin-top:8px;flex-wrap:wrap;">
                    <div><label style="font-size:11px;display:block;">Qty Picked</label><input type="number" step="0.0001" :value="line.quantity_to_pick" style="width:100px;font-size:13px;padding:4px 8px;" class="form-input" x-ref="qty" :id="'qty-'+line.id"></div>
                    <div><label style="font-size:11px;display:block;">Lot #</label><input type="text" :value="line.lot_number" style="width:140px;font-size:13px;padding:4px 8px;" class="form-input" :id="'lot-'+line.id"></div>
                    <button class="btn btn-sm btn-primary" @click="confirmLine(line.id, 'PICKED')">Picked</button>
                    <button class="btn btn-sm btn-warning" @click="confirmLine(line.id, 'PARTIAL')">Partial</button>
                    <button class="btn btn-sm btn-danger" @click="unableLine(line.id)">Unable</button>
                </div>
                <div x-show="line.status === 'UNABLE'" style="margin-top:4px;font-size:12px;color:#dc2626;" x-text="'Reason: ' + (line.unable_reason || '')"></div>
                <?php endif;?>
            </div>
        </template>
    </div>

    <script src="/assets/js/settings.js"></script>
    <script>
    var t=document.getElementById('toast');if(t)setTimeout(function(){t.style.display='none';},4000);

    function pickApp() {
        return {
            sortByLocation: false,
            lines: <?= json_encode(array_map(function($l) {
                return [
                    'id' => (int)$l['id'],
                    'so_number' => $l['so_number'],
                    'customer_name' => $l['customer_name'],
                    'item_code' => $l['item_code'],
                    'item_description' => $l['item_description'],
                    'pack_name' => $l['pack_name'] ?? '',
                    'location' => $l['location'] ?? '',
                    'quantity_to_pick' => $l['quantity_to_pick'],
                    'quantity_picked' => $l['quantity_picked'],
                    'lot_number' => $l['lot_number'] ?? '',
                    'status' => $l['status'],
                    'unable_reason' => $l['unable_reason'] ?? '',
                ];
            }, $lines)) ?>,

            get sortedLines() {
                if (!this.sortByLocation) return this.lines;
                return [...this.lines].sort(function(a, b) {
                    return (a.location || 'zzz').localeCompare(b.location || 'zzz');
                });
            },

            confirmLine(lineId, status) {
                var qtyEl = document.getElementById('qty-' + lineId);
                var lotEl = document.getElementById('lot-' + lineId);
                var body = new FormData();
                body.append('line_id', lineId);
                body.append('quantity_picked', qtyEl ? qtyEl.value : 0);
                body.append('lot_number', lotEl ? lotEl.value : '');
                body.append('status', status);

                var self = this;
                fetch('/pick-lists/<?=$pkl['id']?>/confirm-line', { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            var line = self.lines.find(function(l) { return l.id === lineId; });
                            if (line) {
                                line.status = status;
                                line.quantity_picked = qtyEl ? qtyEl.value : 0;
                                line.lot_number = lotEl ? lotEl.value : '';
                            }
                        }
                    });
            },

            unableLine(lineId) {
                var reason = prompt('Reason for unable to pick:');
                if (!reason) return;
                var body = new FormData();
                body.append('line_id', lineId);
                body.append('quantity_picked', 0);
                body.append('status', 'UNABLE');
                body.append('unable_reason', reason);

                var self = this;
                fetch('/pick-lists/<?=$pkl['id']?>/confirm-line', { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success) {
                            var line = self.lines.find(function(l) { return l.id === lineId; });
                            if (line) { line.status = 'UNABLE'; line.unable_reason = reason; }
                        }
                    });
            }
        };
    }
    </script>
</body>
</html>
