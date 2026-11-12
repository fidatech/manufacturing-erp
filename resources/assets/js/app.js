// ERP core script. Plain JavaScript: no jQuery, no Bootstrap JS, no plugins.
// Source file. public/js/app.js is generated from it by: php tools/build-assets.php
// Write comments on their own lines, starting with two slashes.
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

    // ---------------------------------------------------------------- settings
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

    // ----------------------------------------------------------------- sidebar
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

    // ---------------------------------------------------------------- collapse
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
        // the sidebar is an accordion: one open group at a time
        if (menu && open) {
            $$('[data-bs-toggle="collapse"][aria-expanded="true"]', menu).forEach(function (other) { setCollapse(other, false); });
        }
        setCollapse(link, open);
    });

    // --------------------------------------------------------------- dropdowns
    function closeDropdowns(except) {
        $$('.dropdown-menu.show').forEach(function (menu) {
            if (menu === except) return;
            menu.classList.remove('show');
            // a bottom sheet was moved to <body>: put it back next to its button
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
                // on phones and tablets the menu is a bottom sheet. It moves to <body>
                // because the blurred top bar would otherwise trap its fixed position.
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

    // ------------------------------------------------------------------ modals
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
        // force a reflow so the fade transition runs
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

    // -------------------------------------------------------------------- tabs
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

    // ------------------------------------------------------------------ toasts
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

    // ----------------------------------------------------------------- confirm
    // <form data-confirm="Sign out?" data-confirm-text="..." data-confirm-ok="Sign out" data-confirm-style="primary">
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
        // a form can be sent only once: stops double posting on slow connections
        if (form._sent) { e.preventDefault(); return; }
        form._sent = true;
        $$('button[type="submit"], button:not([type])', form).forEach(function (b) { b.classList.add('is-busy'); b.setAttribute('aria-disabled', 'true'); });
    });
    // coming back with the browser back button re-enables the form
    window.addEventListener('pageshow', function () {
        $$('form').forEach(function (f) { f._sent = false; f._confirmed = false; });
        $$('.is-busy').forEach(function (b) { b.classList.remove('is-busy'); b.removeAttribute('aria-disabled'); });
    });

    // ------------------------------------------------------- password show/hide
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = $('input', btn.closest('.auth-field, .input-group, .input-icon'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        setIcon(btn, show ? 'r-eye-slash' : 'r-eye');
    });

    // --------------------------------------------------------- command search
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

    // ------------------------------------------------------- amount boxes
    // Every amount box (inputmode="decimal") accepts digits only and shows thousands
    // with commas while typing: 12500.5 becomes 12,500.5. The server removes the commas.
    ERP.formatNumber = function (value) {
        var text = String(value == null ? '' : value).replace(/[^0-9.]/g, '');
        var dot = text.indexOf('.');
        var whole = dot < 0 ? text : text.slice(0, dot);
        // only the first dot counts
        var fraction = dot < 0 ? null : text.slice(dot + 1).replace(/\./g, '');
        whole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (dot === 0) whole = '0';
        return fraction === null ? whole : whole + '.' + fraction;
    };
    ERP.numberInput = function (input) {
        var before = input.value;
        var caret = input.selectionStart == null ? before.length : input.selectionStart;
        // how many digits and dots are left of the caret: the caret stays after the same one
        var keep = before.slice(0, caret).replace(/[^0-9.]/g, '').length;
        var after = ERP.formatNumber(before);
        if (after === before) return;
        input.value = after;
        var position = 0;
        for (var seen = 0; position < after.length && seen < keep; position++) {
            if (/[0-9.]/.test(after.charAt(position))) seen++;
        }
        try { input.setSelectionRange(position, position); } catch (err) { return; }
    };
    var AMOUNT = 'input[inputmode="decimal"]';
    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches(AMOUNT)) ERP.numberInput(e.target);
    });

    // ------------------------------------------------ select with search box
    // <select data-search> becomes a box you can type in. No library needed.
    ERP.searchSelect = function (select) {
        if (select._ss || select.disabled) return;
        select._ss = true;
        var wrap = document.createElement('div');
        wrap.className = 'ss';
        var box = document.createElement('input');
        box.type = 'text';
        box.className = 'form-control ' + (select.classList.contains('is-invalid') ? 'is-invalid' : '');
        box.autocomplete = 'off';
        box.placeholder = select.options.length && !select.options[0].value ? select.options[0].text : '';
        if (select.required) { box.required = true; select.required = false; }
        if (select.id) { box.id = select.id; select.removeAttribute('id'); }
        var list = document.createElement('div');
        list.className = 'ss-list';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(box);
        wrap.appendChild(list);
        wrap.appendChild(select);
        select.hidden = true;
        var focus = 0;
        var current = function () { var o = select.options[select.selectedIndex]; return o && o.value ? o.text : ''; };
        var close = function () { wrap.classList.remove('open'); };
        var show = function (all) {
            var q = all ? '' : box.value.trim().toLowerCase();
            var html = '';
            var n = 0;
            for (var i = 0; i < select.options.length && n < 60; i++) {
                var o = select.options[i];
                if (!o.value || (q && o.text.toLowerCase().indexOf(q) < 0)) continue;
                html += '<div class="ss-item' + (n === 0 ? ' focus' : '') + '" data-value="' + ERP.escape(o.value) + '">' + ERP.escape(o.text) + '</div>';
                n++;
            }
            list.innerHTML = html || '<div class="ss-empty">' + ERP.escape(T.noResults || 'No results') + '</div>';
            focus = 0;
            wrap.classList.add('open');
        };
        var pick = function (value) {
            select.value = value;
            box.value = current();
            close();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };
        box.value = current();
        box.addEventListener('focus', function () { box.select(); show(true); });
        // clicking a box that already has the cursor opens the list again, ready to type over
        box.addEventListener('click', function () { if (!wrap.classList.contains('open')) { box.select(); show(true); } });
        box.addEventListener('input', function () { show(false); });
        box.addEventListener('keydown', function (e) {
            var items = $$('.ss-item', list);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                focus = (focus + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (it, n) { it.classList.toggle('focus', n === focus); });
                items[focus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && wrap.classList.contains('open')) {
                e.preventDefault();
                if (items[focus]) pick(items[focus].getAttribute('data-value'));
            } else if (e.key === 'Escape') {
                close();
            }
        });
        // mousedown comes before blur, so the choice is not lost
        list.addEventListener('mousedown', function (e) {
            var item = e.target.closest('.ss-item');
            if (item) { e.preventDefault(); pick(item.getAttribute('data-value')); }
        });
        box.addEventListener('blur', function () {
            if (box.value.trim() === '' && select.value !== '') pick('');
            else box.value = current();
            close();
        });
    };
    var enhance = function (scope) { $$('select[data-search]', scope).forEach(ERP.searchSelect); };

    // ------------------------------------------------------- rows of a paper
    // <table data-rows> with <tbody data-rows-body>, <template data-row-template> (uses __i__ as
    // the row number), a [data-add-row] button and a [data-remove-row] button in every row.
    var rowCounter = 1000;
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-add-row]');
        if (add) {
            // a form can have several groups of rows: data-add-row="costs" works on data-rows-body="costs"
            var form = add.closest('form');
            var group = add.getAttribute('data-add-row');
            var pick = group ? '="' + group + '"' : '';
            var body = $('[data-rows-body' + pick + ']', form);
            var template = $('[data-row-template' + pick + ']', form);
            if (!body || !template) return;
            var holder = document.createElement('tbody');
            holder.innerHTML = template.innerHTML.replace(/__i__/g, String(rowCounter++));
            var row = holder.firstElementChild;
            body.appendChild(row);
            enhance(row);
            var first = $('input:not([type="hidden"]), select', row);
            if (first) first.focus();
            return;
        }
        var remove = e.target.closest('[data-remove-row]');
        if (remove) {
            var tr = remove.closest('[data-row]');
            var tbody = tr.parentNode;
            if ($$('[data-row]', tbody).length > 1) tr.remove();
            else $$('input:not([type="hidden"])', tr).forEach(function (i) { i.value = ''; });
            recalc(tbody.closest('form'));
        }
    });

    // Totals of a sale or purchase. Only for showing: the server calculates again when saving.
    var num = function (v) { var n = parseFloat(String(v == null ? '' : v).replace(/,/g, '')); return isFinite(n) ? n : 0; };
    var show2 = function (n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function recalc(form) {
        if (!form || !form.hasAttribute('data-totals')) return;
        var subtotal = 0;
        $$('[data-row]', form).forEach(function (row) {
            var qty = $('input[data-qty]', row);
            var price = $('input[data-price]', row);
            var out = $('[data-line-total]', row);
            if (!qty || !price) return;
            var total = Math.round(num(qty.value) * num(price.value) * 100) / 100;
            subtotal += total;
            if (out) out.textContent = show2(total);
        });
        var discount = num(($('input[data-discount]', form) || {}).value);
        var set = function (sel, value) { var el = $(sel, form); if (el) el.textContent = show2(value); };
        set('[data-subtotal]', subtotal);
        set('[data-grand]', subtotal - discount);
        var paid = num(($('input[data-paid]', form) || {}).value);
        set('[data-remaining]', subtotal - discount - paid);
    }
    document.addEventListener('input', function (e) { recalc(e.target.closest('form')); });
    // choosing an item fills its usual price and unit
    document.addEventListener('change', function (e) {
        var select = e.target;
        if (!select.matches || !select.matches('select[data-item]')) return;
        var option = select.options[select.selectedIndex];
        var row = select.closest('[data-row]');
        if (!option || !row) return;
        var price = $('input[data-price]', row);
        var form = select.closest('form');
        var party = $('select[data-party]', form);
        var partyOption = party ? party.options[party.selectedIndex] : null;
        var inBase = !party || (partyOption && partyOption.getAttribute('data-cur') === form.getAttribute('data-base'));
        // a customer's own price (Customer Price Lists) comes first, then the usual price
        var own = null;
        try { own = ((JSON.parse(form.getAttribute('data-prices') || '{}')[party ? party.value : ''] || {})[select.value]) || null; } catch (err) { own = null; }
        if (price && !price.value && own) price.value = own;
        if (price && !price.value && inBase && option.getAttribute('data-price')) price.value = option.getAttribute('data-price');
        var unit = $('[data-unit]', row);
        if (unit) unit.textContent = option.getAttribute('data-unit') || '';
        recalc(select.closest('form'));
    });
    // the currency of the chosen customer or supplier is shown next to the totals
    var showCurrency = function (form) {
        var party = $('select[data-party]', form);
        var option = party ? party.options[party.selectedIndex] : null;
        var code = option && option.value ? option.getAttribute('data-cur') : '';
        $$('[data-party-cur]', form).forEach(function (el) { el.textContent = code; });
    };
    document.addEventListener('change', function (e) {
        if (e.target.matches && e.target.matches('select[data-party]')) showCurrency(e.target.closest('form'));
    });
    $$('form[data-totals]').forEach(function (form) { recalc(form); showCurrency(form); });

    // Currency exchange form: the currencies follow the chosen account, the rate comes from the
    // rates table (or is typed by hand), and the buying amount is worked out while typing.
    $$('form[data-exchange]').forEach(function (form) {
        var rates = {};
        try { rates = JSON.parse(form.getAttribute('data-rates') || '{}') || {}; } catch (err) { rates = {}; }
        var holder = $('[name="holder"]', form);
        var sellCur = $('[data-exchange-sell]', form);
        var buyCur = $('[data-exchange-buy]', form);
        var sellAmount = $('[name="sell_amount"]', form);
        var buyAmount = $('[name="buy_amount"]', form);
        var rate = $('[name="rate"]', form);
        var operation = $('[name="operation"]', form);
        var date = $('[name="date"]', form);
        var hint = $('[data-rate-hint]', form);
        var sum = $('[data-exchange-sum]', form);
        if (!holder || !sellCur || !buyCur) return;

        var number = function (input) { var v = parseFloat(String(input.value).replace(/,/g, '')); return isFinite(v) && v > 0 ? v : 0; };
        var show = function (value, decimals) {
            if (!(value > 0) || !isFinite(value)) return '';
            var text = value.toFixed(decimals);
            if (decimals > 2) text = text.replace(/0+$/, '').replace(/\.$/, '');
            return ERP.formatNumber(text);
        };
        var code = function (select) { var o = select.options[select.selectedIndex]; return o ? o.getAttribute('data-code') : ''; };
        var say = function (key) { if (hint) hint.textContent = key ? form.getAttribute('data-text-' + key) || '' : ''; };

        var summary = function () {
            $$('[data-sell-code]', form).forEach(function (el) { el.textContent = '(' + code(sellCur) + ')'; });
            $$('[data-buy-code]', form).forEach(function (el) { el.textContent = '(' + code(buyCur) + ')'; });
            if (!sum) return;
            var sold = number(sellAmount), bought = number(buyAmount), r = number(rate);
            sum.hidden = !(sold && bought && r);
            if (!sum.hidden) {
                sum.textContent = show(sold, 2) + ' ' + code(sellCur) + ' ' + (operation.value === 'divide' ? '÷' : '×') + ' ' + show(r, 6) + ' = ' + show(bought, 2) + ' ' + code(buyCur);
            }
        };
        var calcBuy = function () {
            var sold = number(sellAmount), r = number(rate);
            buyAmount.value = sold && r ? show(operation.value === 'divide' ? sold / r : sold * r, 2) : '';
            summary();
        };
        var calcRate = function () {
            var sold = number(sellAmount), bought = number(buyAmount);
            if (sold && bought) { rate.value = show(operation.value === 'divide' ? sold / bought : bought / sold, 6); say('manual'); }
            summary();
        };
        var tableRate = function () {
            if (!holder.value) { say(''); summary(); return; }
            var s = parseFloat(rates[sellCur.value]), b = parseFloat(rates[buyCur.value]);
            if (!(s > 0) || !(b > 0)) { rate.value = ''; say('none'); calcBuy(); return; }
            operation.value = b >= s ? 'multiply' : 'divide';
            rate.value = show(b >= s ? b / s : s / b, 6);
            say('table');
            calcBuy();
        };
        // only the currencies the chosen account has can be picked, and never the same one twice
        var limit = function () {
            var option = holder.options[holder.selectedIndex];
            var allowed = option && option.value ? String(option.getAttribute('data-currencies') || '').split(',') : null;
            [sellCur, buyCur].forEach(function (select) {
                Array.prototype.forEach.call(select.options, function (o) {
                    var ok = !allowed || allowed.indexOf(o.value) >= 0;
                    o.hidden = !ok;
                    o.disabled = !ok;
                });
                if (select.options[select.selectedIndex] && select.options[select.selectedIndex].disabled) {
                    var first = Array.prototype.filter.call(select.options, function (o) { return !o.disabled; })[0];
                    if (first) select.value = first.value;
                }
            });
        };
        var differ = function (changed) {
            if (sellCur.value !== buyCur.value) return;
            var other = changed === buyCur ? sellCur : buyCur;
            var next = Array.prototype.filter.call(other.options, function (o) { return !o.disabled && o.value !== changed.value; })[0];
            if (next) other.value = next.value;
        };

        holder.addEventListener('change', function () { limit(); differ(sellCur); tableRate(); });
        sellCur.addEventListener('change', function () { differ(sellCur); tableRate(); });
        buyCur.addEventListener('change', function () { differ(buyCur); tableRate(); });
        sellAmount.addEventListener('input', calcBuy);
        rate.addEventListener('input', function () { say('manual'); calcBuy(); });
        operation.addEventListener('change', calcBuy);
        buyAmount.addEventListener('input', calcRate);
        var again = $('[data-table-rate]', form);
        if (again) again.addEventListener('click', tableRate);
        if (date) date.addEventListener('change', function () {
            var url = form.getAttribute('data-rates-url');
            if (!url || !date.value || !window.fetch) return;
            var query = '?date=' + encodeURIComponent(date.value) + ($('input[name="_shamsi[]"]', form) ? '&_shamsi[]=date' : '');
            fetch(url + query, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { rates = data; tableRate(); } })
                .catch(function () { return null; });
        });

        limit();
        differ(sellCur);
        if (rate.value) summary(); else tableRate();
    });

    // Money form: show the second amount only when the two accounts use different currencies.
    $$('form[data-money-form]').forEach(function (form) {
        var sync = function () {
            var cur = {};
            $$('select[data-currency-of]', form).forEach(function (s) {
                var o = s.options[s.selectedIndex];
                cur[s.getAttribute('data-currency-of')] = o && o.value ? o.getAttribute('data-cur') : '';
            });
            $$('[data-cur-label]', form).forEach(function (el) { var c = cur[el.getAttribute('data-cur-label')]; el.textContent = c ? '(' + c + ')' : ''; });
            var box = $('[data-other-amount]', form);
            if (box) {
                var differ = !!cur.money && !!cur.other && cur.money !== cur.other;
                box.hidden = !differ;
                if (!differ) $('input', box).value = '';
            }
        };
        // Money in / money out: choosing the person picks the cash box of the same currency.
        // A money account the user already chose in that currency is left alone.
        var pickMoney = function () {
            var other = $('select[data-currency-of="other"]', form);
            var money = $('select[data-currency-of="money"]', form);
            if (!other || !money) return;
            var chosen = other.options[other.selectedIndex];
            var cur = chosen && chosen.value ? chosen.getAttribute('data-cur') : '';
            var now = money.options[money.selectedIndex];
            if (!cur || (now && now.value && now.getAttribute('data-cur') === cur)) return;
            var match = null;
            for (var i = 0; i < money.options.length; i++) {
                var o = money.options[i];
                if (!o.value || o.getAttribute('data-cur') !== cur) continue;
                // a cash box comes before a bank or sarafi
                if (o.hasAttribute('data-cash')) { match = o; break; }
                match = match || o;
            }
            if (match) money.value = match.value;
        };
        form.addEventListener('change', function (e) {
            if (form.hasAttribute('data-auto-money') && e.target.matches && e.target.matches('select[data-currency-of="other"]')) pickMoney();
            sync();
        });
        if (form.hasAttribute('data-auto-money')) pickMoney();
        sync();
    });

    // ------------------------------------------- tables become cards on phones
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

    // -------------------------------------------------------------------- boot
    if (!$('.app-content h1')) body.classList.add('no-large-title');
    var onScroll = function () { body.classList.toggle('is-scrolled', window.scrollY > 28); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    syncTheme();
    $$(AMOUNT).forEach(function (input) { if (input.value) input.value = ERP.formatNumber(input.value); });
    enhance(document);
    cardifyAll();
    var active = $('#sidebarMenu .menu-sublink.active, #sidebarMenu .menu-item.active > .menu-link');
    var scroller = $('#sidebarScroll');
    if (active && scroller) scroller.scrollTop = Math.max(0, active.getBoundingClientRect().top - scroller.clientHeight / 2);
    body.classList.add('is-ready', 'app-ready');
})();
