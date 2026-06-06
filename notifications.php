<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
requireLogin();

$pageTitle = 'Notifications';
$extraCss  = [];
require_once __DIR__ . '/includes/header.php';
?>
<style>
.notif-center{max-width:700px;margin:0 auto;padding:24px 20px 40px;}
.notif-center h1{font-size:20px;font-weight:700;margin:0 0 4px;color:#000000;}
.notif-center .subtitle{color:#444444;font-size:14px;margin:0 0 20px;}
.notif-toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.notif-toolbar select{flex:1;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--surface-1);color:#000000;font-size:13px;}
.notif-list{display:flex;flex-direction:column;gap:6px;}
.notif-card{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:var(--surface-1);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:border-color .18s,box-shadow .18s;text-decoration:none;color:#000000 !important;}
.notif-card:hover{border-color:var(--primary);box-shadow:0 2px 8px rgba(0,0,0,.07);}
.notif-card.unread{background:var(--surface-2);border-color:var(--primary-soft,var(--border));}
.notif-card.unread .notif-dot{width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:6px;}
.notif-card:not(.unread) .notif-dot{width:8px;flex-shrink:0;}
.notif-icon{font-size:20px;flex-shrink:0;width:32px;text-align:center;}
.notif-body{flex:1;min-width:0;}
.notif-msg{font-size:14px;font-weight:500;color:#000000 !important;line-height:1.4;margin:0 0 4px;}
.notif-meta{font-size:12px;color:#444444 !important;}
.notif-meta span{display:inline-block;margin-right:8px;color:#444444 !important;}
.notif-empty{text-align:center;padding:60px 20px;color:#555555;}
.notif-empty .icon{font-size:40px;margin-bottom:12px;}
/* Badge colours kept vivid for type-at-a-glance readability */
.badge-type{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px;}
.badge-adoption{background:#fef3c7;color:#000000;}
.badge-system{background:#f3f4f6;color:#000000;}
.badge-otp{background:#fce7f3;color:#000000;}
.notif-loading{text-align:center;padding:40px;color:#555555;}
</style>

<div class="notif-center">
    <h1>🔔 Notification Center</h1>
    <p class="subtitle">Your recent activity and system alerts</p>

    <div class="notif-toolbar">
        <select id="filterType" onchange="loadNotifications()">
            <option value="">All types</option>
            <option value="adoption">🐾 Adoption</option>
            <option value="system">🔔 System</option>
            <option value="otp">🔐 Security</option>
        </select>
        <select id="filterRead" onchange="loadNotifications()">
            <option value="">All</option>
            <option value="unread">Unread only</option>
            <option value="read">Read only</option>
        </select>
        <button class="btn btn-outline" id="markAllBtn" onclick="markAllRead()" style="white-space:nowrap;padding:8px 14px;font-size:13px;">
            Mark all read
        </button>
    </div>

    <div id="notifList" class="notif-list">
        <div class="notif-loading">Loading notifications…</div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const API = '<?= url('api/notifications.php') ?>';
let allNotifications = [];

async function loadNotifications() {
    const res  = await fetch(API + '?action=list&limit=50');
    const data = await res.json();
    if (!data.success) return;

    allNotifications = data.notifications;
    renderNotifications();
}

function renderNotifications() {
    const typeFilter = document.getElementById('filterType').value;
    const readFilter = document.getElementById('filterRead').value;

    let items = allNotifications;
    if (typeFilter) items = items.filter(n => n.type === typeFilter);
    if (readFilter === 'unread') items = items.filter(n => !n.is_read);
    if (readFilter === 'read')   items = items.filter(n => n.is_read);

    const list = document.getElementById('notifList');
    if (!items.length) {
        list.innerHTML = '<div class="notif-empty"><div class="icon">🔕</div>No notifications here.</div>';
        return;
    }

    const badgeClass = { adoption:'badge-adoption', system:'badge-system', otp:'badge-otp' };

    list.innerHTML = items.map(n => {
        const href = n.link_url ? '<?= url('') ?>' + n.link_url : '#';
        const cls  = n.is_read ? '' : 'unread';
        const badge = `<span class="badge-type ${badgeClass[n.type] || 'badge-system'}">${n.type}</span>`;
        return `
          <a href="${href}" class="notif-card ${cls}"
             onclick="markRead(${n.id}, this)"
             data-id="${n.id}">
            <div class="notif-dot"></div>
            <div class="notif-icon">${n.icon}</div>
            <div class="notif-body">
              <p class="notif-msg">${escHtml(n.message)}</p>
              <div class="notif-meta">
                ${badge}
                <span>⏱ ${n.time_ago}</span>
              </div>
            </div>
          </a>`;
    }).join('');
}

async function markRead(id, el) {
    if (el.classList.contains('unread')) {
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-dot');
        if (dot) dot.style.background = 'transparent';
        await fetch(API + '?action=mark_read', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=mark_read&id=' + id
        });
        const n = allNotifications.find(x => x.id === id);
        if (n) n.is_read = true;
        updateBadge();
    }
}

async function markAllRead() {
    await fetch(API + '?action=mark_all_read', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=mark_all_read'
    });
    allNotifications.forEach(n => n.is_read = true);
    renderNotifications();
    updateBadge(0);
}

function updateBadge(count) {
    const badge = document.querySelector('.notif-badge');
    if (!badge) return;
    if (count === undefined) count = allNotifications.filter(n => !n.is_read).length;
    badge.textContent = count > 0 ? count : '';
    badge.style.display = count > 0 ? '' : 'none';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadNotifications();
</script>
