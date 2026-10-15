{{-- Turns a page's own tab bar (or its boxes) into the left-menu layout used by /admin/cloud. --}}
<style>
    .content-header { display: none !important; }
    .as-shell { position: relative; margin: -12px -15px -30px; padding: 24px; background: #D6D2C7; }
    .as-shell::before { content: ''; position: absolute; inset: 0; pointer-events: none; background-image: repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px); }
    .as-layout { position: relative; z-index: 1; display: grid; gap: 24px; grid-template-columns: 1fr; align-items: stretch; }
    @media (min-width: 992px) { .as-layout { grid-template-columns: 260px minmax(0, 1fr); } }
    .as-panel { background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; }
    .as-side { padding: 18px; align-self: stretch; }
    @media (min-width: 992px) { .as-side-inner { position: sticky; top: 104px; } }
    .as-side-title { display: none; }
    .as-nav { display: flex; flex-direction: column; gap: 4px; }
    .as-nav-item, .as-nav-item:hover, .as-nav-item:focus, .as-nav-item span { color: #742220 !important; }
    .as-nav-item.active, .as-nav-item.active span { color: #2D4A3E !important; }
    .as-nav-item { display: flex; align-items: center; gap: 10px; border: 2px solid transparent; border-radius: 999px; padding: 9px 14px; font-size: 12px; font-weight: 800; text-decoration: none !important; cursor: pointer; }
    .as-nav-item .material-icons-round { font-size: 20px; flex-shrink: 0; }
    .as-side-head { display: flex; align-items: center; justify-content: center; gap: 10px; border-radius: 999px; border: 2px solid #5a1a18; background: #742220; padding: 13px 18px; box-shadow: 3px 3px 0 0 #5a1a18; margin-bottom: 18px; }
    .as-side-head, .as-side-head span { color: #FEF9E1 !important; }
    .as-side-head .as-side-head-text { font-size: 11px; font-weight: 900; letter-spacing: .18em; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
    .as-side-head .material-icons-round { font-size: 18px; letter-spacing: normal; text-transform: none; flex-shrink: 0; }
    .as-nav-item:hover { background: rgba(45,74,62,.08); }
    .as-nav-item.active { background: #F5EFD5; border-color: #2D4A3E; }
    .as-main { min-width: 0; padding: 22px; }
    .as-main > .row:first-child { margin-top: 0; }
    .as-hidden-nav { display: none !important; }
    .as-section-hidden { display: none !important; }
    .as-main .nav-tabs-custom { background: transparent !important; box-shadow: none !important; border: 0 !important; margin-bottom: 0 !important; }
    .as-full-col { width: 100% !important; float: none !important; }
    .as-main .box { background: transparent !important; border: 0 !important; box-shadow: none !important; margin: 0 !important; }
    .as-main .box > .box-header { padding: 0 0 12px !important; margin-bottom: 16px; border-bottom: 2px solid #C8BCA0 !important; background: transparent !important; }
    .as-main .box > .box-header .box-title { font-size: 18px !important; font-weight: 900 !important; color: #742220 !important; }
    .as-main .box > .box-body { padding: 0 !important; }
    .as-main .box-footer { padding: 16px 0 0 !important; margin-top: 16px; border-top: 2px solid #C8BCA0 !important; background: transparent !important; }
    /* Box mode: no outer card, the selected section is the card itself. */
    .as-main.as-bare { background: transparent; border: 0; box-shadow: none; border-radius: 0; padding: 0 6px 6px 0; }
    .as-main.as-bare > .row { margin: 0; }
    .as-main.as-bare > .row > [class*="col-"] { padding: 0; }
    .as-main.as-bare .box:not(.as-section-hidden) { background: #FEF9E1 !important; border: 2px solid #2D4A3E !important; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E !important; padding: 22px; min-height: var(--as-h, 100%); }
    /* Save/Test buttons sit at the bottom of the card, and stay visible while the card scrolls. */
    .as-main.as-bare .box:not(.as-section-hidden) { display: flex; flex-direction: column; }
    .as-main.as-bare .box:not(.as-section-hidden) > form { flex: 1 1 auto; display: flex; flex-direction: column; }
    .as-main.as-bare .box:not(.as-section-hidden) > .box-body,
    .as-main.as-bare .box:not(.as-section-hidden) > form > .box-body { flex: 1 1 auto; }
    .as-main.as-bare .box-footer { position: sticky; bottom: 0; z-index: 2; padding: 14px 0 18px !important; background: #FEF9E1 !important; }
    .as-main.as-bare .box-footer::after { content: ''; display: table; clear: both; }
    .as-main.as-bare .box .box:not(.as-section-hidden) { border: 0 !important; box-shadow: none !important; padding: 0; min-height: 0; }
    /* Tab pages: strip each page's own decorative shells so only the cloud-style panel remains,
       and render boxes as the flat inner cards used on /admin/cloud. The id beats page-specific rules. */
    #asMain [class$="-shell"], #asMain [class*="-shell "],
    #asMain .nav-tabs-custom, #asMain .tab-content, #asMain .tab-pane, #asMain .tab-pane.active, #asMain .tab-pane > form {
        background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 0 !important; border-radius: 0 !important; overflow: visible !important;
    }
    #asMain [class$="-shell"]::before, #asMain [class*="-shell "]::before { display: none !important; }
    #asMain.as-panel:not(.as-tabs) .box { background: #F5EFD5 !important; border: 2px solid #C8BCA0 !important; border-radius: 12px !important; box-shadow: none !important; padding: 18px !important; margin: 0 0 18px !important; overflow: visible !important; }
    #asMain.as-panel:not(.as-tabs) .box > .box-header, #asMain.as-panel:not(.as-tabs) .box > .box-body, #asMain.as-panel:not(.as-tabs) .box > .box-footer { background: transparent !important; }
    #asMain.as-panel:not(.as-tabs) .box > .box-header { padding: 0 0 10px !important; margin-bottom: 14px !important; border-bottom: 2px solid #C8BCA0 !important; }
    #asMain.as-panel:not(.as-tabs) .box > .box-header .box-title { font-size: 15px !important; font-weight: 900 !important; color: #742220 !important; }
    #asMain.as-panel:not(.as-tabs) .box > .box-body { padding: 0 !important; }
    #asMain.as-panel:not(.as-tabs) .box > .box-footer { padding: 14px 0 0 !important; margin-top: 14px !important; border-top: 2px solid #C8BCA0 !important; }
    #asMain.as-panel:not(.as-tabs) .box .box { background: transparent !important; border: 0 !important; padding: 0 !important; }
    #asMain.as-tabs .box { background: transparent !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; padding: 0 !important; margin: 0 0 24px !important; overflow: visible !important; }
    #asMain.as-tabs .box > .box-header, #asMain.as-tabs .box > .box-body, #asMain.as-tabs .box > .box-footer { background: transparent !important; }
    #asMain.as-tabs .box > .box-header { padding: 0 0 12px !important; margin-bottom: 16px !important; border-bottom: 2px solid #C8BCA0 !important; }
    #asMain.as-tabs .box > .box-header .box-title { font-size: 18px !important; font-weight: 900 !important; color: #742220 !important; }
    #asMain.as-tabs .box > .box-body { padding: 0 !important; }
    #asMain.as-tabs .box > .box-footer { padding: 16px 0 0 !important; margin-top: 16px !important; border-top: 2px solid #C8BCA0 !important; }
    #asMain.as-tabs .box { min-height: 0 !important; }
    #asMain.as-tabs .box .box { background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
    #asMain.as-tabs > * > .row, #asMain.as-tabs .tab-pane > form > .row, #asMain.as-tabs .tab-pane > .row { margin-left: -12px; margin-right: -12px; }
    #asMain.as-tabs .row > [class*="col-md-"] { padding-left: 12px; padding-right: 12px; }
    /* Field grid: fields line up flush with the section title instead of being indented. */
    #asMain .box-body .row { margin-left: -10px !important; margin-right: -10px !important; }
    #asMain .box-body .row > [class*="col-"] { padding-left: 10px !important; padding-right: 10px !important; }
    #asMain .form-group { margin-bottom: 18px; }
    /* Thin scrollbars that only show while hovering the scroll area. */
    #asMain, #asMain * { scrollbar-width: thin; scrollbar-color: transparent transparent; }
    #asMain:hover, #asMain *:hover { scrollbar-color: rgba(116,34,32,.35) transparent; }
    #asMain::-webkit-scrollbar, #asMain *::-webkit-scrollbar { width: 6px; height: 6px; }
    #asMain::-webkit-scrollbar-track, #asMain *::-webkit-scrollbar-track { background: transparent; }
    #asMain::-webkit-scrollbar-thumb, #asMain *::-webkit-scrollbar-thumb { background: transparent; border-radius: 999px; }
    #asMain:hover::-webkit-scrollbar-thumb, #asMain *:hover::-webkit-scrollbar-thumb { background: rgba(116,34,32,.35); }
    #asMain .box > .box-header { display: flex !important; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    #asMain .box > .box-header .box-tools { position: static !important; margin: 0 !important; display: flex; align-items: center; gap: 8px; }
    #asMain .box > .box-header::before, #asMain .box > .box-header::after { display: none !important; }
    #asMain .box-footer .btn { min-width: 110px; }
    #asMain .box-footer .pull-right, #asMain .box-footer > .btn { float: right !important; }
    /* Tab pages: the side column (status / requirements / help) is a tinted info card. */
    #asMain.as-tabs .box.as-mini { background: #F5EFD5 !important; border: 2px solid #C8BCA0 !important; border-radius: 12px !important; padding: 18px !important; margin-bottom: 20px !important; }
    #asMain.as-tabs .box.as-mini:hover { border-color: #2D4A3E !important; }
    #asMain.as-tabs .box.as-mini > .box-header .box-title { font-size: 13px !important; letter-spacing: .12em; text-transform: uppercase; }
    /* Chain from the panel to the Save button stretches, so Save always sits at the bottom of the card. */
    #asMain .as-grow { display: flex !important; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #asMain .row.as-grow { flex-direction: row; flex-wrap: wrap; align-items: stretch; }
    #asMain .row.as-grow > [class*="col-"] { display: flex; flex-direction: column; }
    #asMain .row.as-grow > [class*="col-"] > .box { flex: 1 1 auto; }
    #asMain .as-grow > *, #asMain.as-tabs > * { flex-shrink: 0; }
    #asMain button.as-push, #asMain input.as-push { align-self: flex-end; height: auto !important; min-height: 0 !important; flex: 0 0 auto !important; }
    #asMain .as-push { margin-top: auto !important; }
    #asMain .as-footbar { display: flex; justify-content: flex-end; align-items: center; gap: 8px; border-top: 2px solid #C8BCA0; padding-top: 16px; margin-top: 18px; }
    #asMain .as-footbar .btn, #asMain .box-footer .btn { min-width: 120px; padding: 8px 22px !important; font-size: 12px !important; line-height: 1.4 !important; }
    #asMain.as-tabs .as-push { position: sticky; bottom: -22px; z-index: 2; background: #FEF9E1; padding-top: 14px; padding-bottom: 4px; }
    #asMain .box.as-grow > .box-body { flex: 1 1 auto; }
    #asMain.as-tabs .box-footer::after, #asMain .box-footer::after { content: ''; display: table; clear: both; }
    #asMain .box-body { padding-left: 0 !important; padding-right: 0 !important; padding-top: 0 !important; }
    #asMain .box-footer { padding-left: 0 !important; padding-right: 0 !important; }
    #asMain .callout { background: #F5EFD5 !important; border: 2px solid #C8BCA0 !important; border-left: 4px solid #2D4A3E !important; border-radius: 12px !important; box-shadow: none !important; color: #4B3A2F !important; padding: 14px 18px !important; margin: 0 0 18px !important; }
    #asMain .callout h4 { color: #742220 !important; font-size: 14px; font-weight: 900; margin: 0 0 6px; }
    #asMain .callout p { color: rgba(75,58,47,.85) !important; font-size: 12px; margin: 0 0 4px; }
    /* Box mode on desktop: the card is exactly the panel height; its body scrolls, Save stays pinned at the bottom. */
    @media (min-width: 992px) {
        .as-shell.as-fixed .as-main.as-bare .box.as-card { height: var(--as-card-h); min-height: 0; overflow: hidden !important; }
        .as-main.as-bare .box.as-card > form { min-height: 0; }
        .as-main.as-bare .box.as-card > .box-body, .as-main.as-bare .box.as-card > form > .box-body { min-height: 0; overflow-y: auto; overflow-x: hidden; padding-right: 6px !important; }
        .as-main.as-bare .box.as-card .box-footer { position: static; flex-shrink: 0; margin-top: auto !important; }
    }
    @media (min-width: 992px) {
        .as-shell.as-fixed .as-layout { height: 100%; grid-template-rows: minmax(0, 1fr); }
        .as-shell.as-fixed .as-main { overflow-y: auto; overflow-x: hidden; }
    }
</style>
<script>
    (function () {
        function text(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }
        function slug(s) { return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
        var ICONS = [[/audit/, 'policy'], [/new account/, 'person_add'], [/payment/, 'payments'], [/security log/, 'shield'], [/login/, 'login'], [/forget password|forgot password/, 'lock_reset'], [/change password/, 'password'], [/change email/, 'alternate_email'], [/ticket/, 'confirmation_number'], [/discord channel/, 'forum'], [/server icon|icon/, 'image'], [/server motd|motd/, 'campaign'], [/matching|match/, 'rule'], [/down detector|detector|monitor/, 'monitor_heart'], [/billing/, 'payments'], [/incident/, 'report'], [/rule/, 'gavel'], [/agent/, 'support_agent'], [/quarantine/, 'block'], [/live event|event/, 'bolt'], [/general|personality/, 'tune'], [/mail|email/, 'mail'], [/turnstile|security|captcha/, 'shield'], [/http|connection|network/, 'lan'], [/allocation|port/, 'settings_ethernet'], [/identity|community/, 'groups'], [/oauth|google|login/, 'key'], [/discord|bot/, 'smart_toy'], [/\bai\b|model|provider/, 'psychology'], [/skill/, 'auto_awesome'], [/memory/, 'memory'], [/log/, 'receipt_long'], [/node/, 'dns'], [/server/, 'storage'], [/domain/, 'language'], [/template|record/, 'description'], [/invoice/, 'receipt'], [/payment/, 'payments'], [/refund/, 'undo'], [/subscription/, 'autorenew'], [/reconcil/, 'fact_check'], [/tax/, 'percent'], [/coupon/, 'sell'], [/webhook|history/, 'history'], [/fail/, 'error'], [/motd|brand/, 'campaign'], [/icon|image|logo/, 'image'], [/sync|live/, 'sync'], [/match/, 'rule'], [/runtime|file/, 'folder'], [/overview|dashboard/, 'dashboard'], [/new|create/, 'add_circle'], [/usage|stat/, 'bar_chart'], [/setting|panel|general/, 'tune']];
        function iconFor(label) { var l = label.toLowerCase(); for (var i = 0; i < ICONS.length; i++) if (ICONS[i][0].test(l)) return ICONS[i][1]; return 'chevron_right'; }
        function outside(el) { return !el.closest('.modal') && !el.closest('.tab-pane'); }

        function build() {
            var content = document.querySelector('.admin-main-shell > section.content');
            if (!content || content.querySelector('.as-shell')) return;

            var items = [];
            var nav = Array.prototype.find.call(content.querySelectorAll('ul.nav-tabs'), outside);

            if (nav) {
                // Existing tab bar: links keep working (page links or Bootstrap tabs), we only move the menu.
                nav.classList.add('as-hidden-nav');
                Array.prototype.forEach.call(nav.children, function (li) {
                    var a = li.querySelector('a');
                    if (!a) return;
                    items.push({ label: text(a), active: li.classList.contains('active'), source: a, li: li });
                });
            } else {
                // No tab bar: every top-level box becomes a menu entry.
                var boxes = Array.prototype.filter.call(content.querySelectorAll('.box'), function (box) {
                    return outside(box) && !box.parentElement.closest('.box') && box.querySelector('.box-title');
                });
                if (boxes.length < 2) return;
                var sharedForm = boxes.some(function (box) {
                    var form = box.closest('form');
                    return form && boxes.some(function (other) { return other !== box && form.contains(other); });
                });
                var wanted = (location.hash || '').slice(1);
                boxes.forEach(function (box, i) {
                    var label = text(box.querySelector('.box-title'));
                    box.id = box.id || 'section-' + slug(label);
                    items.push({ label: label, box: box, active: wanted ? box.id === wanted : i === 0 });
                });
                if (!items.some(function (i) { return i.active; })) items[0].active = true;
                if (sharedForm) {
                    boxes.forEach(function (box) {
                        var form = box.closest('form');
                        if (!form || box.querySelector('[type="submit"]:not([data-as-action])')) return;
                        var submit = form.querySelector('[type="submit"]:not([data-as-action])');
                        if (!submit) return;
                        var footer = document.createElement('div');
                        footer.className = 'box-footer';
                        footer.appendChild(submit.cloneNode(true));
                        box.appendChild(footer);
                    });
                    // The form's own footer-only box (Save + CSRF token) is now redundant: hide it, it still submits.
                    boxes.forEach(function (box) {
                        var form = box.closest('form');
                        if (!form) return;
                        Array.prototype.forEach.call(form.querySelectorAll('.box'), function (extra) {
                            if (boxes.indexOf(extra) === -1 && !extra.parentElement.closest('.box')) extra.classList.add('as-section-hidden');
                        });
                    });
                }
                {
                    // One section at a time, like the cloud page. Columns go full width so a lone box fills the panel.
                    boxes.forEach(function (box) {
                        var col = box.parentElement;
                        while (col && col !== content) {
                            if (/\bcol-(xs|sm|md|lg)-\d+/.test(col.className)) col.classList.add('as-full-col');
                            col = col.parentElement;
                        }
                    });
                    items.forEach(function (i) { i.single = true; });
                }
            }
            if (!items.length) return;
            var bare = items.some(function (i) { return i.single; });

            var shell = document.createElement('div');
            shell.className = 'as-shell';
            var layout = document.createElement('div');
            layout.className = 'as-layout';
            var side = document.createElement('aside');
            side.className = 'as-side as-panel';
            var inner = document.createElement('div');
            inner.className = 'as-side-inner';
            var title = document.createElement('div');
            title.className = 'as-side-title';
            var h1 = document.querySelector('.content-header h1');
            title.textContent = h1 ? text(h1.childNodes[0] || h1) : document.title;
            var list = document.createElement('nav');
            list.className = 'as-nav';
            var main = document.createElement('div');
            main.className = bare ? 'as-main as-bare' : 'as-main as-panel as-tabs';
            main.id = 'asMain';

            // Bootstrap modals must live directly under <body>; inside the panel they end up under their own backdrop.
            Array.prototype.forEach.call(content.querySelectorAll('.modal'), function (modal) { document.body.appendChild(modal); });

            // Everything after the toast row moves into the main panel.
            Array.prototype.slice.call(content.children).forEach(function (child) {
                if (child.querySelector && child.querySelector('#adminToasts')) return;
                main.appendChild(child);
            });

            function select(item) {
                items.forEach(function (i) { i.link.classList.toggle('active', i === item); });
                if (item.single) {
                    items.forEach(function (i) { i.box.classList.toggle('as-section-hidden', i !== item); });
                    if (typeof fit === 'function' && shell.isConnected) fit();
                    try { history.replaceState(null, '', location.pathname + location.search + '#' + item.box.id); } catch (e) {}
                } else if (item.box) {
                    item.box.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            items.forEach(function (item) {
                var link = document.createElement('a');
                link.className = 'as-nav-item' + (item.active ? ' active' : '');
                var icon = document.createElement('span');
                icon.className = 'material-icons-round';
                icon.textContent = iconFor(item.label);
                link.appendChild(icon);
                var span = document.createElement('span');
                span.textContent = item.label;
                link.appendChild(span);
                if (item.source) {
                    var href = item.source.getAttribute('href') || '#';
                    link.href = href;
                    if (href.charAt(0) === '#' || item.source.hasAttribute('data-toggle')) {
                        link.addEventListener('click', function (e) {
                            e.preventDefault();
                            item.source.click();
                            items.forEach(function (i) { i.link.classList.toggle('active', i === item); });
                        });
                    }
                } else {
                    link.href = '#' + item.box.id;
                    link.addEventListener('click', function (e) { e.preventDefault(); select(item); });
                }
                item.link = link;
                list.appendChild(link);
            });

            var head = document.createElement('div');
            head.className = 'as-side-head';
            head.innerHTML = '<span class="material-icons-round">' + iconFor(title.textContent) + '</span>';
            var headText = document.createElement('span');
            headText.className = 'as-side-head-text';
            headText.textContent = title.textContent;
            head.appendChild(headText);
            inner.appendChild(head);
            inner.appendChild(list);
            side.appendChild(inner);
            layout.appendChild(side);
            layout.appendChild(main);
            shell.appendChild(layout);
            content.appendChild(shell);

            // Boxes laid out in a grid (half width or less) keep a card of their own, e.g. provider cards.
            Array.prototype.forEach.call(main.querySelectorAll('.box'), function (box) {
                var col = box.parentElement && box.parentElement.closest('[class*="col-"]');
                if (!col || !main.contains(col)) return;
                var m = col.className.match(/\bcol-(?:sm|md|lg)-(\d+)/);
                if (m && parseInt(m[1], 10) <= 6) box.classList.add('as-mini');
            });

            // Tab pages: stretch the path from the panel down to the visible Save button.
            if (!bare) {
                main.style.display = 'flex';
                main.style.flexDirection = 'column';
                var panes = main.querySelectorAll('.tab-pane.active');
                var scope = panes.length ? panes[0] : main;
                // Only a real Save button pulls to the bottom (not pagination or filters).
                var submit = Array.prototype.find.call(scope.querySelectorAll('form button[type="submit"], form input[type="submit"], form button:not([type])'), function (b) {
                    return b.offsetParent !== null && !b.closest('.modal') && !b.closest('table') && b.form && (b.form.getAttribute('method') || 'get').toLowerCase() !== 'get' && b.form.querySelector('input:not([type="hidden"]):not([type="submit"]), select, textarea');
                });
                var footer = submit ? (submit.closest('.box-footer') || submit.parentElement) : null;
                // If the button shares its parent with form fields, push the button itself.
                // Such a lone button gets its own footer bar (right-aligned, with a divider above it).
                if (footer && !footer.classList.contains('box-footer') && footer.querySelector('input:not([type="hidden"]):not([type="submit"]), select, textarea')) {
                    var bar = document.createElement('div');
                    bar.className = 'as-footbar';
                    submit.parentNode.insertBefore(bar, submit);
                    bar.appendChild(submit);
                    submit.classList.remove('pull-right', 'pull-left');
                    var clear = bar.nextElementSibling;
                    if (clear && clear.classList.contains('clearfix')) clear.remove();
                    footer = bar;
                }
                if (footer && footer.closest('.as-mini')) footer = null;
                if (footer) footer.classList.add('as-push');
                if (footer) {
                    var el = footer.parentElement;
                    while (el && el !== main) { el.classList.add('as-grow'); el = el.parentElement; }
                }
            }

            var initial = items.find(function (i) { return i.active; });
            if (initial && initial.single) select(initial);

            function fit() {
                var footer = document.querySelector('.admin-sticky-footer');
                var header = document.querySelector('.admin-topbar');
                var mainShell = document.querySelector('.admin-main-shell');
                if (window.innerWidth < 992) { main.style.removeProperty('--as-h'); main.style.overflowY = ''; Array.prototype.forEach.call(main.querySelectorAll('[data-as-fill]'), function (el) { el.style.height = ''; }); shell.style.height = ''; layout.style.height = ''; shell.classList.remove('as-fixed'); return; }
                var footH = footer ? footer.getBoundingClientRect().height : 0;
                if (mainShell) mainShell.style.paddingBottom = footH + 'px';
                var rect = shell.getBoundingClientRect();
                var gap = parseFloat(getComputedStyle(shell).paddingTop) || 0;
                if (header) gap = Math.max(gap, rect.top + gap - header.getBoundingClientRect().bottom);
                shell.style.paddingBottom = gap + 'px';
                shell.style.minHeight = '';
                var h = window.innerHeight - footH - (rect.top + window.scrollY);
                shell.classList.add('as-fixed');
                shell.style.height = Math.max(420, h) + 'px';
                var over = document.documentElement.scrollHeight - window.innerHeight;
                if (over > 0) shell.style.height = Math.max(420, h - over) + 'px';
                layout.style.height = (shell.clientHeight - gap - (parseFloat(getComputedStyle(shell).paddingTop) || 0)) + 'px';
                Array.prototype.forEach.call(main.querySelectorAll('[data-as-fill]'), function (el) {
                    var top = el.getBoundingClientRect().top - main.getBoundingClientRect().top + main.scrollTop;
                    el.style.height = Math.max(240, main.clientHeight - top - 24) + 'px';
                    main.style.overflowY = 'hidden';
                });
                // Every section card gets the same height: the full panel height.
                if (bare) {
                    main.style.setProperty('--as-h', (main.clientHeight - 6) + 'px');
                    items.forEach(function (i) {
                        if (!i.box || i.box.classList.contains('as-section-hidden')) return;
                        // Only when the card has its own Save footer (or none at all) - fill what is left below any notes above it.
                        var top = i.box.getBoundingClientRect().top - main.getBoundingClientRect().top + main.scrollTop;
                        i.box.classList.add('as-card');
                        main.style.setProperty('--as-card-h', Math.max(260, main.clientHeight - top - 6) + 'px');
                    });
                }
            }
            fit();
            window.addEventListener('resize', fit);
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
    })();
</script>
