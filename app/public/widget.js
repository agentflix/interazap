/*!
 * InteraZap Web Widget Loader
 * Floating chat bubble that embeds the public webchat without redirect.
 *
 * Usage (paste before </body>):
 *   <script
 *     src="https://app.interazap.com/widget.js"
 *     data-tenant="YOUR_TENANT_ID"
 *     data-color="#2563eb"
 *     data-title="Suporte ao Cliente"
 *     data-icon-url=""
 *     data-greeting="Precisa de ajuda?"
 *     data-greeting-delay="5000"
 *     async
 *   ></script>
 */
(function () {
  'use strict';

  // ─── Idempotency guard ────────────────────────────────────────────────────
  if (window.__InteraZapWidgetLoaded) return;
  window.__InteraZapWidgetLoaded = true;

  // ─── Resolve script tag + options ─────────────────────────────────────────
  var script =
    document.currentScript ||
    (function () {
      var scripts = document.getElementsByTagName('script');
      for (var i = scripts.length - 1; i >= 0; i--) {
        if (scripts[i].src && scripts[i].src.indexOf('widget.js') !== -1) {
          return scripts[i];
        }
      }
      return null;
    })();

  if (!script) {
    console.warn('[InteraZap Widget] Could not locate script tag.');
    return;
  }

  var ds = script.dataset || {};
  var tenant = ds.tenant || ds.tenantId || '';
  if (!tenant) {
    console.warn('[InteraZap Widget] Missing data-tenant attribute.');
    return;
  }

  // Origin of the InteraZap app (derived from the script src)
  var origin = (function () {
    try {
      return new URL(script.src).origin;
    } catch (e) {
      return '';
    }
  })();

  var options = {
    tenant: tenant,
    color: ds.color || '#2563eb',
    title: ds.title || 'Atendimento',
    iconUrl: ds.iconUrl || '',
    greeting: ds.greeting || '',
    greetingDelay: parseInt(ds.greetingDelay || '0', 10),
    embedUrl: origin + '/embed/' + encodeURIComponent(tenant),
  };

  // ─── Build host + Shadow DOM (CSS isolation from host site) ───────────────
  var host = document.createElement('div');
  host.id = 'interazap-widget-root';
  host.style.cssText = 'position:fixed;bottom:0;right:0;z-index:2147483647;width:0;height:0;';
  document.body.appendChild(host);

  var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

  var style = document.createElement('style');
  style.textContent = [
    ':host,*{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;}',
    '.iz-fab{position:fixed;bottom:20px;right:20px;width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;background:' +
      options.color +
      ';box-shadow:0 6px 20px rgba(0,0,0,0.18);display:flex;align-items:center;justify-content:center;color:#fff;transition:transform .2s ease,box-shadow .2s ease;padding:0;}',
    '.iz-fab:hover{transform:scale(1.06);box-shadow:0 10px 28px rgba(0,0,0,0.24);}',
    '.iz-fab:focus{outline:3px solid rgba(255,255,255,0.6);outline-offset:2px;}',
    '.iz-fab svg{width:28px;height:28px;}',
    '.iz-fab img{width:32px;height:32px;border-radius:50%;object-fit:cover;}',
    '.iz-badge{position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:#ef4444;color:#fff;font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;border:2px solid #fff;}',
    '.iz-greeting{position:fixed;bottom:90px;right:20px;max-width:260px;background:#fff;color:#111827;padding:12px 36px 12px 14px;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,0.16);font-size:14px;line-height:1.4;cursor:pointer;animation:izFade .3s ease;}',
    '.iz-greeting-close{position:absolute;top:6px;right:8px;background:transparent;border:none;color:#6b7280;cursor:pointer;font-size:16px;line-height:1;padding:2px;}',
    '.iz-panel{position:fixed;bottom:90px;right:20px;width:380px;max-width:calc(100vw - 24px);height:600px;max-height:calc(100vh - 110px);border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 16px 48px rgba(0,0,0,0.22);display:none;flex-direction:column;animation:izSlide .25s ease;}',
    '.iz-panel.open{display:flex;}',
    '.iz-panel iframe{flex:1;width:100%;border:none;display:block;}',
    '@keyframes izSlide{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}',
    '@keyframes izFade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}',
    '@media (max-width:480px){.iz-panel{right:0;bottom:0;width:100vw;max-width:100vw;height:100vh;max-height:100vh;border-radius:0;}.iz-fab{bottom:16px;right:16px;}.iz-greeting{right:16px;bottom:86px;}}',
  ].join('');
  root.appendChild(style);

  // ─── FAB (floating action button) ─────────────────────────────────────────
  var fab = document.createElement('button');
  fab.className = 'iz-fab';
  fab.type = 'button';
  fab.setAttribute('aria-label', 'Abrir chat');

  var defaultIcon =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';

  if (options.iconUrl) {
    var img = document.createElement('img');
    img.src = options.iconUrl;
    img.alt = '';
    fab.appendChild(img);
  } else {
    fab.innerHTML = defaultIcon;
  }

  var badge = document.createElement('span');
  badge.className = 'iz-badge';
  badge.style.display = 'none';
  badge.textContent = '0';
  fab.appendChild(badge);
  root.appendChild(fab);

  // ─── Panel + iframe (lazy-loaded on first open) ───────────────────────────
  var panel = document.createElement('div');
  panel.className = 'iz-panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', options.title);
  root.appendChild(panel);

  var iframe = null;
  var unread = 0;
  var isOpen = false;

  function ensureIframe() {
    if (iframe) return;
    iframe = document.createElement('iframe');
    iframe.title = options.title;
    iframe.setAttribute('allow', 'camera; microphone; clipboard-write');
    iframe.src = options.embedUrl;
    panel.appendChild(iframe);
  }

  function setUnread(n) {
    unread = Math.max(0, n | 0);
    if (unread > 0 && !isOpen) {
      badge.textContent = unread > 9 ? '9+' : String(unread);
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }

  function openPanel() {
    ensureIframe();
    panel.classList.add('open');
    isOpen = true;
    fab.setAttribute('aria-label', 'Fechar chat');
    setUnread(0);
    hideGreeting();
  }

  function closePanel() {
    panel.classList.remove('open');
    isOpen = false;
    fab.setAttribute('aria-label', 'Abrir chat');
  }

  function togglePanel() {
    if (isOpen) closePanel();
    else openPanel();
  }

  fab.addEventListener('click', togglePanel);

  // ─── Proactive greeting bubble ────────────────────────────────────────────
  var greetingEl = null;
  function showGreeting() {
    if (!options.greeting || isOpen || greetingEl) return;
    greetingEl = document.createElement('div');
    greetingEl.className = 'iz-greeting';
    greetingEl.setAttribute('role', 'button');
    greetingEl.tabIndex = 0;
    greetingEl.textContent = options.greeting;

    var closeBtn = document.createElement('button');
    closeBtn.className = 'iz-greeting-close';
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Fechar mensagem');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      hideGreeting();
    });
    greetingEl.appendChild(closeBtn);

    greetingEl.addEventListener('click', openPanel);
    greetingEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openPanel();
      }
    });
    root.appendChild(greetingEl);
  }
  function hideGreeting() {
    if (greetingEl && greetingEl.parentNode) {
      greetingEl.parentNode.removeChild(greetingEl);
      greetingEl = null;
    }
  }
  if (options.greeting && options.greetingDelay >= 0) {
    setTimeout(showGreeting, options.greetingDelay || 5000);
  }

  // ─── Cross-frame messaging (iframe → loader) ──────────────────────────────
  // The embed page can postMessage({ source: 'interazap', type: 'unread', count: N })
  // or { type: 'open' } / { type: 'close' } to control the widget.
  window.addEventListener('message', function (ev) {
    if (origin && ev.origin !== origin) return;
    var data = ev.data;
    if (!data || data.source !== 'interazap') return;
    switch (data.type) {
      case 'unread':
        setUnread(data.count);
        break;
      case 'open':
        openPanel();
        break;
      case 'close':
        closePanel();
        break;
    }
  });

  // ─── Public API on window.InteraZap ───────────────────────────────────────
  window.InteraZap = {
    open: openPanel,
    close: closePanel,
    toggle: togglePanel,
    setUnread: setUnread,
    options: options,
  };
})();
