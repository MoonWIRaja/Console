{{-- Gives the plain admin list pages (nodes, servers, users, ...) the same full-height card layout as the settings pages. --}}
<style>
    .content-header { display: none !important; }
    .ls-shell { position: relative; margin: -12px -15px -30px; padding: 24px; background: #D6D2C7; }
    .ls-shell::before { content: ''; position: absolute; inset: 0; pointer-events: none; background-image: repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px); }
    .ls-panel { position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 0; background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 22px; }
    .ls-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding-bottom: 12px; margin-bottom: 16px; border-bottom: 2px solid #C8BCA0; flex-shrink: 0; }
    .ls-title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 18px; font-weight: 900; color: #742220; }
    .ls-title .material-icons-round { font-size: 22px; }
    .ls-info { position: relative; display: inline-flex; }
    .ls-info-btn { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #742220; color: #742220; background: #FEF9E1; font-size: 12px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: help; padding: 0; }
    .ls-info-tip { position: absolute; left: -10px; top: calc(100% + 10px); width: 300px; max-width: 80vw; z-index: 30; background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 10px 12px; font-size: 11px; line-height: 1.6; color: #4B3A2F; opacity: 0; visibility: hidden; transition: opacity .15s; pointer-events: none; }
    .ls-info:hover .ls-info-tip, .ls-info:focus-within .ls-info-tip { opacity: 1; visibility: visible; }
    .ls-count { font-size: 11px; color: rgba(116,34,32,.6); }
    .ls-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ls-actions form { margin: 0; }
    .ls-actions .input-group { display: flex; align-items: center; gap: 6px; width: auto !important; }
    .ls-actions .input-group .form-control { width: 240px !important; float: none; border-radius: 999px !important; }
    .ls-actions .input-group-btn { width: auto; display: flex; gap: 6px; }
    .ls-body { flex: 1 1 auto; min-height: 0; overflow: auto; padding: 2px 6px 6px 2px; }
    body.skin-blue .ls-shell .ls-body > .row { margin-left: -9px; margin-right: -9px; }
    body.skin-blue .ls-shell .ls-body > .row > [class*="col-"] { padding-left: 9px; padding-right: 9px; }
    /* Every box inside is a flat tinted card. */
    body.skin-blue .ls-shell .ls-body .box, body.skin-blue .ls-shell .ls-body .box[class*="box-"] { background: #F5EFD5 !important; background-image: none !important; border: 2px solid #C8BCA0 !important; border-top: 2px solid #C8BCA0 !important; border-radius: 12px !important; box-shadow: none !important; margin: 0 0 18px !important; overflow: hidden; }
    body.skin-blue .ls-shell .ls-body .box > .box-header { background: transparent !important; border-bottom: 1px solid #C8BCA0 !important; padding: 12px 16px !important; display: flex !important; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    body.skin-blue .ls-shell .ls-body .box > .box-header::before, .ls-body .box > .box-header::after { display: none !important; }
    body.skin-blue .ls-shell .ls-body .box > .box-header .box-title { font-size: 14px !important; font-weight: 900 !important; color: #742220 !important; }
    body.skin-blue .ls-shell .ls-body .box > .box-header .box-tools { position: static !important; }
    body.skin-blue .ls-shell .ls-body .box > .box-body { background: transparent !important; }
    body.skin-blue .ls-shell .ls-body .box > .box-footer { background: transparent !important; border-top: 1px solid #C8BCA0 !important; }
    body.skin-blue .ls-shell .ls-body .box.ls-headless > .box-header { display: none !important; }
    body.skin-blue .ls-shell .ls-body .table { margin: 0; background: transparent !important; }
    body.skin-blue .ls-shell .ls-body .table th { position: sticky; top: 0; z-index: 1; background: #EFE7C9 !important; font-size: 10px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.75) !important; border-bottom: 2px solid #2D4A3E !important; white-space: nowrap; }
    body.skin-blue .ls-shell .ls-body .table td { background: transparent !important; font-size: 12px; vertical-align: middle !important; border-top: 1px solid #E2D8BD !important; }
    body.skin-blue .ls-shell .ls-body .table > tbody > tr:hover > td { background: rgba(254,249,225,.8) !important; }
    body.skin-blue .ls-shell .ls-body .box-body.table-responsive { overflow: visible; }
    .ls-body, .ls-body * { scrollbar-width: thin; scrollbar-color: transparent transparent; }
    .ls-body:hover, .ls-body *:hover { scrollbar-color: rgba(116,34,32,.35) transparent; }
</style>
<script>
    (function () {
        var ICONS = { 'admin.databases': 'storage', 'admin.locations': 'place', 'admin.nodes': 'dns', 'admin.servers': 'dns', 'admin.users': 'group', 'admin.tickets': 'confirmation_number', 'admin.mounts': 'folder_special', 'admin.nests': 'egg', 'admin.api.index': 'vpn_key' };
        var route = @json(request()->route()?->getName());

        function build() {
            var content = document.querySelector('.admin-main-shell > section.content');
            if (!content || content.querySelector('.ls-shell')) return;
            var h1 = document.querySelector('.content-header h1');
            var title = h1 ? (h1.childNodes[0].textContent || '').trim() : document.title;
            var sub = h1 && h1.querySelector('small') ? h1.querySelector('small').textContent.trim() : '';

            Array.prototype.forEach.call(content.querySelectorAll('.modal'), function (m) { document.body.appendChild(m); });

            var shell = document.createElement('div'); shell.className = 'ls-shell';
            var panel = document.createElement('div'); panel.className = 'ls-panel';
            var head = document.createElement('div'); head.className = 'ls-head';
            var h = document.createElement('h3'); h.className = 'ls-title';
            h.innerHTML = '<span class="material-icons-round">' + (ICONS[route] || 'list') + '</span>';
            h.appendChild(document.createTextNode(title));
            head.appendChild(h);
            if (sub) {
                var info = document.createElement('span'); info.className = 'ls-info'; info.tabIndex = 0;
                info.innerHTML = '<button type="button" class="ls-info-btn" aria-label="About this page">!</button><span class="ls-info-tip"></span>';
                info.querySelector('.ls-info-tip').textContent = sub;
                head.appendChild(info);
            }
            var actions = document.createElement('div'); actions.className = 'ls-actions';
            head.appendChild(actions);
            var body = document.createElement('div'); body.className = 'ls-body';

            Array.prototype.slice.call(content.children).forEach(function (child) {
                if (child.querySelector && child.querySelector('#adminToasts')) return;
                body.appendChild(child);
            });

            // The first box's tools (search, Create New, Settings) move up into the page head.
            var first = body.querySelector('.box');
            if (first) {
                var header = first.querySelector(':scope > .box-header');
                var tools = header && header.querySelector('.box-tools');
                if (tools) {
                    while (tools.firstChild) actions.appendChild(tools.firstChild);
                    tools.remove();
                    // A header that only held "X List" + tools is redundant now.
                    if (header.children.length <= 1) first.classList.add('ls-headless');
                }
            }

            panel.appendChild(head);
            panel.appendChild(body);
            shell.appendChild(panel);
            content.appendChild(shell);

            function fit() {
                var footer = document.querySelector('.admin-sticky-footer');
                var mainShell = document.querySelector('.admin-main-shell');
                if (window.innerWidth < 992) { shell.style.height = ''; panel.style.height = ''; return; }
                var footH = footer ? footer.getBoundingClientRect().height : 0;
                if (mainShell) mainShell.style.paddingBottom = footH + 'px';
                var top = shell.getBoundingClientRect().top + window.scrollY;
                var h = Math.max(420, window.innerHeight - footH - top);
                shell.style.height = h + 'px';
                panel.style.height = (shell.clientHeight - 48) + 'px';
            }
            fit();
            window.addEventListener('resize', fit);
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
    })();
</script>
