(function () {
'use strict';
var root = document.documentElement;
var body = document.body;
var ERP = (window.ERP = {});
var SPRITE = root.getAttribute('data-icons') || '/icons.svg';
var T = window.ERP_TEXT || {};
var $ = function (sel, scope) { return (scope || document).querySelector(sel); };
var $$ = function (sel, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(sel)); };
var mq = function (q) { return window.matchMedia(q).matches; };
var appMode = function () { return mq('(max-width: 991.98px)'); };
var phone = function () { return mq('(max-width: 767.98px)'); };
ERP.escape = function (s) {
return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
});
};
ERP.icon = function (id, cls) {
return '<i class="fi ' + (cls || '') + '"><svg aria-hidden="true"><use href="' + SPRITE + '#' + id + '"/></svg></i>';
};
function setIcon(el, id) {
var use = el && $('use', el);
if (use) use.setAttribute('href', SPRITE + '#' + id);
}
var settings = {};
try { settings = JSON.parse(localStorage.getItem('erp.ui') || '{}'); } catch (e) { settings = {}; }
function save() {
try { localStorage.setItem('erp.ui', JSON.stringify(settings)); } catch (e) { return; }
}
function theme() { return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light'; }
function syncTheme() {
$$('[data-theme-toggle]').forEach(function (b) { setIcon(b, theme() === 'dark' ? 's-sun' : 's-moon'); });
}
document.addEventListener('click', function (e) {
if (!e.target.closest('[data-theme-toggle]')) return;
settings.theme = theme() === 'dark' ? 'light' : 'dark';
root.setAttribute('data-bs-theme', settings.theme);
save();
syncTheme();
});
function toggleLayout() {
settings.layout = root.getAttribute('data-layout') === 'collapsed' ? 'vertical' : 'collapsed';
root.setAttribute('data-layout', settings.layout);
save();
}
function closeDrawer() { body.classList.remove('sidebar-open'); }
document.addEventListener('click', function (e) {
var t = e.target;
if (t.closest('#sidebarToggle')) {
if (appMode()) body.classList.toggle('sidebar-open'); else toggleLayout();
} else if (t.closest('#sidebarPin')) {
toggleLayout();
} else if (t.closest('#tabMenu')) {
body.classList.add('sidebar-open');
} else if (t.closest('#sidebarBackdrop')) {
closeDrawer();
} else if (t.closest('#appBack')) {
var same = false;
try { same = !!document.referrer && new URL(document.referrer).origin === location.origin; } catch (err) { same = false; }
if (history.length > 1 && same) history.back(); else location.href = root.getAttribute('data-home') || '/';
}
});
function setCollapse(link, open) {
var target = $(link.getAttribute('data-bs-target') || link.getAttribute('href'));
if (!target) return;
target.classList.toggle('show', open);
link.setAttribute('aria-expanded', open ? 'true' : 'false');
var item = link.closest('.menu-item');
if (item) item.classList.toggle('open', open);
}
document.addEventListener('click', function (e) {
var link = e.target.closest('[data-bs-toggle="collapse"]');
if (!link) return;
e.preventDefault();
var open = link.getAttribute('aria-expanded') !== 'true';
var menu = link.closest('#sidebarMenu');
if (menu && open) {
$$('[data-bs-toggle="collapse"][aria-expanded="true"]', menu).forEach(function (other) { setCollapse(other, false); });
}
setCollapse(link, open);
});
function closeDropdowns(except) {
$$('.dropdown-menu.show').forEach(function (menu) {
if (menu === except) return;
menu.classList.remove('show');
if (menu._home) { menu._home.appendChild(menu); menu._home = null; }
var toggle = $('[data-bs-toggle="dropdown"]', menu.parentNode);
if (toggle) { toggle.classList.remove('show'); toggle.setAttribute('aria-expanded', 'false'); }
});
if (!except) $$('.sheet-backdrop').forEach(function (b) { b.remove(); });
}
document.addEventListener('click', function (e) {
var toggle = e.target.closest('[data-bs-toggle="dropdown"]');
if (toggle) {
e.preventDefault();
var menu = $('.dropdown-menu', toggle.parentNode);
if (!menu) return;
var open = !menu.classList.contains('show');
closeDropdowns();
if (open) {
menu.setAttribute('data-bs-popper', 'static');
menu.classList.add('show');
toggle.classList.add('show');
toggle.setAttribute('aria-expanded', 'true');
if (appMode()) {
var back = document.createElement('div');
back.className = 'sheet-backdrop';
body.appendChild(back);
menu._home = menu.parentNode;
body.appendChild(menu);
}
}
return;
}
var inside = e.target.closest('.dropdown-menu.show');
if (inside && inside.getAttribute('data-bs-auto-close') === 'outside' && !e.target.closest('a, button[type="submit"]')) return;
closeDropdowns();
});
ERP.showModal = function (modal) {
if (typeof modal === 'string') modal = $(modal);
if (!modal) return;
closeDropdowns();
var back = document.createElement('div');
back.className = 'modal-backdrop fade';
body.appendChild(back);
modal._back = back;
modal.style.display = 'block';
modal.removeAttribute('aria-hidden');
body.classList.add('modal-open');
void modal.offsetWidth;
modal.classList.add('show');
back.classList.add('show');
var focus = $('[autofocus], input:not([type="hidden"]), .btn-primary, .btn', modal);
if (focus) focus.focus();
modal.dispatchEvent(new CustomEvent('erp:shown'));
};
ERP.hideModal = function (modal) {
if (!modal || !modal.classList.contains('show')) return;
modal.classList.remove('show');
if (modal._back) modal._back.classList.remove('show');
setTimeout(function () {
modal.style.display = '';
modal.setAttribute('aria-hidden', 'true');
if (modal._back) { modal._back.remove(); modal._back = null; }
if (!$('.modal.show')) body.classList.remove('modal-open');
modal.dispatchEvent(new CustomEvent('erp:hidden'));
}, 160);
};
document.addEventListener('click', function (e) {
var open = e.target.closest('[data-bs-toggle="modal"]');
if (open) { e.preventDefault(); closeDrawer(); ERP.showModal(open.getAttribute('data-bs-target')); return; }
var dismiss = e.target.closest('[data-bs-dismiss="modal"]');
if (dismiss) { ERP.hideModal(dismiss.closest('.modal')); return; }
if (e.target.classList && e.target.classList.contains('modal')) ERP.hideModal(e.target);
});
document.addEventListener('keydown', function (e) {
if (e.key !== 'Escape') return;
var modals = $$('.modal.show');
if (modals.length) ERP.hideModal(modals[modals.length - 1]);
else if ($('.dropdown-menu.show')) closeDropdowns();
else closeDrawer();
});
document.addEventListener('click', function (e) {
var tab = e.target.closest('[data-bs-toggle="tab"]');
if (!tab) return;
e.preventDefault();
var pane = $(tab.getAttribute('data-bs-target') || tab.getAttribute('href'));
if (!pane) return;
$$('[data-bs-toggle="tab"]', tab.closest('.nav')).forEach(function (t) { t.classList.toggle('active', t === tab); });
$$('.tab-pane', pane.parentNode).forEach(function (p) {
p.classList.toggle('active', p === pane);
p.classList.toggle('show', p === pane);
});
});
ERP.toast = function (message, type) {
var box = $('#toasts');
if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'erp-toasts'; body.appendChild(box); }
type = type || 'success';
var ico = { success: 's-circle-check', danger: 's-circle-xmark', warning: 's-triangle-exclamation', info: 's-circle-info' }[type] || 's-circle-info';
var el = document.createElement('div');
el.className = 'erp-toast is-' + type;
el.setAttribute('role', 'status');
el.innerHTML = ERP.icon(ico) + '<span>' + ERP.escape(message) + '</span>';
box.appendChild(el);
setTimeout(function () { el.classList.add('is-out'); setTimeout(function () { el.remove(); }, 250); }, type === 'danger' ? 6000 : 3200);
};
$$('[data-toast]').forEach(function (el) { ERP.toast(el.textContent.trim(), el.getAttribute('data-toast')); el.remove(); });
ERP.confirm = function (o, onOk) {
var style = o.style || 'danger';
var modal = document.createElement('div');
modal.className = 'modal fade erp-confirm';
modal.tabIndex = -1;
modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body text-center">'
+ '<div class="erp-confirm-icon text-' + style + '">' + ERP.icon(style === 'danger' ? 's-triangle-exclamation' : 's-circle-question') + '</div>'
+ '<h5 class="mb-1">' + ERP.escape(o.title || T.confirmTitle || 'Are you sure?') + '</h5>'
+ (o.text ? '<p class="text-muted mb-0">' + ERP.escape(o.text) + '</p>' : '')
+ '<div class="d-flex gap-2 mt-4"><button type="button" class="btn btn-light-secondary flex-fill" data-bs-dismiss="modal">' + ERP.escape(T.cancel || 'Cancel') + '</button>'
+ '<button type="button" class="btn btn-' + style + ' flex-fill" data-ok>' + ERP.escape(o.ok || T.confirmOk || 'Yes') + '</button></div>'
+ '</div></div></div>';
body.appendChild(modal);
modal.addEventListener('erp:hidden', function () { modal.remove(); });
$('[data-ok]', modal).addEventListener('click', function () { ERP.hideModal(modal); onOk(); });
ERP.showModal(modal);
$('[data-bs-dismiss]', modal).focus();
};
document.addEventListener('submit', function (e) {
var form = e.target;
if (form.hasAttribute('data-confirm') && !form._confirmed) {
e.preventDefault();
ERP.confirm({
title: form.getAttribute('data-confirm'), text: form.getAttribute('data-confirm-text'),
ok: form.getAttribute('data-confirm-ok'), style: form.getAttribute('data-confirm-style'),
}, function () { form._confirmed = true; if (form.requestSubmit) form.requestSubmit(); else form.submit(); });
return;
}
if (form._sent) { e.preventDefault(); return; }
form._sent = true;
$$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.classList.add('is-busy'); b.setAttribute('aria-disabled', 'true'); });
});
window.addEventListener('pageshow', function () {
$$('form').forEach(function (f) { f._sent = false; f._confirmed = false; });
$$('.is-busy').forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
});
document.addEventListener('click', function (e) {
var btn = e.target.closest('[data-toggle-password]');
if (!btn) return;
var input = $('input', btn.closest('.auth-field, .input-group, .input-icon'));
if (!input) return;
var show = input.type === 'password';
input.type = show ? 'text' : 'password';
setIcon(btn, show ? 'r-eye-slash' : 'r-eye');
});
var results = $('#searchResults');
var input = $('#searchInput');
if (results && input) {
var index = $$('#sidebarMenu a[href]').filter(function (a) { return a.getAttribute('href').charAt(0) !== '#'; }).map(function (a) {
var group = a.closest('.has-sub');
var text = $('.menu-text', a);
var use = $('.menu-icon use', group || a);
return {
label: (text ? text.textContent : a.textContent).trim(),
parent: group ? $('.menu-text', group).textContent.trim() : '',
href: a.getAttribute('href'),
icon: use ? use.getAttribute('href').split('#')[1] : 's-file',
};
});
var focus = 0;
var render = function (q) {
q = (q || '').trim().toLowerCase();
var hits = index.filter(function (i) { return !q || (i.label + ' ' + i.parent).toLowerCase().indexOf(q) > -1; }).slice(0, 30);
focus = 0;
results.innerHTML = hits.length ? hits.map(function (h, n) {
return '<a class="search-item ' + (n === 0 ? 'focus' : '') + '" href="' + ERP.escape(h.href) + '"><span class="si-icon">' + ERP.icon(h.icon) + '</span>'
+ '<span><strong>' + ERP.escape(h.label) + '</strong><small>' + ERP.escape(h.parent) + '</small></span>' + ERP.icon('s-arrow-right', 'si-go') + '</a>';
}).join('') : '<div class="search-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
};
$('#searchModal').addEventListener('erp:shown', function () { input.value = ''; input.focus(); render(''); });
input.addEventListener('input', function () { render(input.value); });
input.addEventListener('keydown', function (e) {
var items = $$('.search-item', results);
if (!items.length) return;
if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
e.preventDefault();
focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
items.forEach(function (a, n) { a.classList.toggle('focus', n === focus); });
items[focus].scrollIntoView({ block: 'nearest' });
} else if (e.key === 'Enter') {
location.href = items[focus].getAttribute('href');
}
});
document.addEventListener('keydown', function (e) {
if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ERP.showModal('#searchModal'); }
});
}
var SKIP = '.keep-table, .table-sm';
ERP.cardify = function (table) {
if (table.matches(SKIP)) return;
if (!phone()) { table.classList.remove('table-cards'); return; }
var heads = $$('thead tr:first-child th', table).map(function (th) { return th.textContent.trim(); });
$$('tbody > tr', table).forEach(function (tr) {
var cells = Array.prototype.slice.call(tr.children);
cells.forEach(function (td, i) {
if (td.tagName !== 'TD') return;
td.setAttribute('data-label', heads[i] || '');
td.classList.toggle('cell-primary', i === 0);
td.classList.toggle('cell-actions', i === cells.length - 1 && !!$('.btn, .btn-act, .dropdown', td));
});
});
table.classList.add('table-cards');
};
var cardifyAll = function () { $$('.app-content table.table').forEach(ERP.cardify); };
var resizeTimer;
window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(cardifyAll, 200); });
if (!$('.app-content h1')) body.classList.add('no-large-title');
var onScroll = function () { body.classList.toggle('is-scrolled', window.scrollY > 28); };
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();
syncTheme();
cardifyAll();
var active = $('#sidebarMenu .menu-sublink.active, #sidebarMenu .menu-item.active > .menu-link');
var scroller = $('#sidebarScroll');
if (active && scroller) scroller.scrollTop = Math.max(0, active.getBoundingClientRect().top - scroller.clientHeight / 2);
body.classList.add('is-ready', 'app-ready');
})();
