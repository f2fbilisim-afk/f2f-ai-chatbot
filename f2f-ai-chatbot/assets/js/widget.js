(function () {
  'use strict';

  var cfg = window.f2fAiChatbot || {};
  if (!cfg.restUrl && !cfg.enabled && cfg.enabled !== false) {
    /* allow demo without enabled flag */
  }
  if (!cfg.botName && !cfg.discoverHeadline) {
    return;
  }

  var state = {
    screen: 'discover', // discover | lead | chat
    pendingIntent: '',
    pendingService: '',
    lead: null,
    history: [],
    busy: false,
  };

  var ICONS = {
    layout:
      '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2" fill="currentColor"/><path d="M3 9h18" stroke="#fff" stroke-width="1.5"/><path d="M9 9v9" stroke="#fff" stroke-width="1.5"/></svg>',
    sparkle:
      '<svg viewBox="0 0 24 24"><path d="M12 3l1.2 4.2L17 8.5l-3.8 1.3L12 14l-1.2-4.2L7 8.5l3.8-1.3L12 3z"/><path d="M18 13l.7 2.3L21 16l-2.3.7L18 19l-.7-2.3L15 16l2.3-.7L18 13z"/></svg>',
    globe:
      '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg>',
    bag:
      '<svg viewBox="0 0 24 24"><path d="M6 8h12l1 13H5L6 8z"/><path d="M9 8V7a3 3 0 0 1 6 0v1"/></svg>',
    code:
      '<svg viewBox="0 0 24 24"><path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13 6l-2 12"/></svg>',
    server:
      '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/><path d="M7 7h.01M7 17h.01"/></svg>',
    chat:
      '<svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4V5z"/></svg>',
    star:
      '<svg viewBox="0 0 24 24"><path d="M12 3l2.5 6.5L21 11l-5 4.2L17.5 21 12 17.5 6.5 21 8 15.2 3 11l6.5-1.5L12 3z"/></svg>',
  };

  function iconSvg(name, filled) {
    var key = name && ICONS[name] ? name : 'star';
    var html = ICONS[key];
    if (filled && key === 'layout') return html;
    if (filled) {
      return html.replace('<svg', '<svg fill="currentColor"');
    }
    return html;
  }

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        if (k === 'text') node.textContent = attrs[k];
        else if (k === 'html') node.innerHTML = attrs[k];
        else node.setAttribute(k, attrs[k]);
      });
    }
    return node;
  }

  function restBase() {
    var base = cfg.restUrl || '/api/';
    if (base.slice(-1) !== '/') base += '/';
    return base;
  }

  function api(path, body) {
    return fetch(restBase() + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || '',
      },
      body: JSON.stringify(body || {}),
    }).then(function (res) {
      return res.json().then(function (data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    });
  }

  function whatsappUrl() {
    var wa = cfg.whatsapp || {};
    var phone = String(wa.phone || '').replace(/\D+/g, '');
    if (!phone) return '#';
    var msg = encodeURIComponent(wa.message || '');
    return 'https://wa.me/' + phone + (msg ? '?text=' + msg : '');
  }

  function mount() {
    var root = document.getElementById('f2f-ai-chatbot-root');
    if (!root) return;

    if (cfg.primaryColor) {
      root.style.setProperty('--f2f-green', cfg.primaryColor);
      root.style.setProperty('--f2f-green-soft', 'color-mix(in srgb, ' + cfg.primaryColor + ' 14%, white)');
      root.style.setProperty('--f2f-green-border', 'color-mix(in srgb, ' + cfg.primaryColor + ' 35%, white)');
    }
    root.setAttribute('data-position', cfg.position === 'left' ? 'left' : 'right');

    var bottomPct = typeof cfg.bottomMargin === 'number' ? cfg.bottomMargin : parseFloat(cfg.bottomMargin || '4');
    var sidePct = typeof cfg.sideMargin === 'number' ? cfg.sideMargin : parseFloat(cfg.sideMargin || '2');
    if (isNaN(bottomPct)) bottomPct = 4;
    if (isNaN(sidePct)) sidePct = 2;
    root.style.setProperty('--f2f-bottom', bottomPct + '%');
    root.style.setProperty('--f2f-side', sidePct + '%');

    var i18n = cfg.i18n || {};

    function openWidget() {
      root.classList.add('is-open');
    }

    var teaser = null;
    if (cfg.showTeaser !== false) {
      teaser = el('button', 'f2f-ai-teaser', { type: 'button', 'aria-label': i18n.open || 'Open' });
      teaser.appendChild(
        el('span', 'f2f-ai-teaser-title', { text: cfg.teaserTitle || 'AI PROJE AJANI' })
      );
      teaser.appendChild(
        el('span', 'f2f-ai-teaser-msg', {
          text: cfg.teaserMessage || 'Merhaba! Fikrinizi anlatın, birlikte netleştirelim.',
        })
      );
      teaser.addEventListener('click', openWidget);
    }

    var launcher = el('button', 'f2f-ai-launcher', {
      type: 'button',
      'aria-label': i18n.open || 'Open',
    });
    launcher.appendChild(
      el('span', 'f2f-ai-launcher-text', { text: cfg.launcherLabel || 'AI' })
    );
    launcher.appendChild(el('span', 'f2f-ai-launcher-dot', { 'aria-hidden': 'true' }));
    launcher.addEventListener('click', openWidget);

    var panel = el('div', 'f2f-ai-panel', { role: 'dialog', 'aria-label': cfg.botName || 'Chatbot' });

    // Header
    var header = el('div', 'f2f-ai-header');
    var avatar = el('img', 'f2f-ai-avatar', {
      src: cfg.avatarUrl || '',
      alt: cfg.botName || '',
      width: '36',
      height: '36',
    });
    header.appendChild(avatar);
    header.appendChild(el('div', 'f2f-ai-header-title', { text: cfg.botName || 'AI Proje Ajanı' }));
    var minBtn = el('button', 'f2f-ai-minimize', {
      type: 'button',
      'aria-label': i18n.close || 'Minimize',
      text: '˅',
    });
    header.appendChild(minBtn);
    panel.appendChild(header);

    // --- Discover screen ---
    var discover = el('div', 'f2f-ai-screen is-active', { 'data-screen': 'discover' });
    var dBody = el('div', 'f2f-ai-discover-body');
    dBody.appendChild(el('h2', null, { text: cfg.discoverHeadline || '' }));
    dBody.appendChild(el('p', 'f2f-sub', { text: cfg.discoverSubtext || '' }));

    var featured = cfg.featured || {};
    var featuredBtn = el('button', 'f2f-ai-featured', { type: 'button' });
    featuredBtn.appendChild(
      el('span', 'f2f-ai-featured-icon', { html: iconSvg(featured.icon || 'layout', true) })
    );
    var fText = el('span', 'f2f-ai-featured-text');
    fText.appendChild(el('strong', null, { text: featured.label || '' }));
    fText.appendChild(el('span', null, { text: featured.subtitle || '' }));
    featuredBtn.appendChild(fText);
    featuredBtn.appendChild(el('span', 'f2f-ai-featured-chevron', { text: '›' }));
    dBody.appendChild(featuredBtn);

    dBody.appendChild(el('div', 'f2f-ai-divider', { text: cfg.dividerText || 'VEYA KEŞFET' }));

    var grid = el('div', 'f2f-ai-services');
    (cfg.services || []).forEach(function (svc) {
      var btn = el('button', 'f2f-ai-service', { type: 'button', 'data-service': svc.label || '' });
      btn.appendChild(el('span', 'f2f-ai-service-icon', { html: iconSvg(svc.icon) }));
      btn.appendChild(el('strong', null, { text: svc.label || '' }));
      btn.addEventListener('click', function () {
        goLead(svc.label || '', svc.label || '');
      });
      grid.appendChild(btn);
    });
    dBody.appendChild(grid);
    discover.appendChild(dBody);

    var dFooter = el('div', 'f2f-ai-footer');
    var dForm = el('form', 'f2f-ai-composer');
    var dInput = el('input', null, {
      type: 'text',
      placeholder: cfg.inputPlaceholder || '',
      autocomplete: 'off',
      maxlength: '2000',
    });
    var dSend = el('button', 'f2f-ai-send', {
      type: 'submit',
      'aria-label': i18n.send || 'Send',
      html: '<svg viewBox="0 0 24 24"><path d="M12 4l8 8h-5v8h-6v-8H4l8-8z"/></svg>',
    });
    dForm.appendChild(dInput);
    dForm.appendChild(dSend);
    dFooter.appendChild(dForm);
    discover.appendChild(dFooter);
    panel.appendChild(discover);

    featuredBtn.addEventListener('click', function () {
      goLead(featured.label || '', featured.label || '');
    });
    dForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = (dInput.value || '').trim();
      if (!text) return;
      dInput.value = '';
      goLead(text, text);
    });

    // --- Lead screen ---
    var leadScreen = el('div', 'f2f-ai-screen', { 'data-screen': 'lead' });
    var lBody = el('div', 'f2f-ai-lead-body');
    var leadCfg = cfg.lead || {};
    lBody.appendChild(el('h2', null, { text: leadCfg.headline || 'Sizi tanıyalım' }));
    lBody.appendChild(el('p', 'f2f-sub', { text: leadCfg.subtext || '' }));

    var leadForm = el('form', 'f2f-ai-lead-form');
    var leadErr = el('p', 'f2f-ai-lead-error');
    leadForm.appendChild(leadErr);

    var row = el('div', 'f2f-ai-row');
    var firstField = fieldBlock(i18n.firstName || 'Ad', 'first_name', i18n.namePh || 'Adınız');
    var lastField = fieldBlock(i18n.lastName || 'Soyad', 'last_name', i18n.lastPh || 'Soyadınız');
    row.appendChild(firstField.wrap);
    row.appendChild(lastField.wrap);
    leadForm.appendChild(row);

    var phoneField = fieldBlock(i18n.phone || 'Telefon', 'phone', i18n.phonePh || '05xx xxx xx xx', 'tel');
    var emailField = fieldBlock(i18n.email || 'E-posta', 'email', i18n.emailPh || 'ornek@firma.com', 'email');
    leadForm.appendChild(phoneField.wrap);
    leadForm.appendChild(emailField.wrap);

    var leadSubmit = el('button', 'f2f-ai-lead-submit', {
      type: 'submit',
      text: leadCfg.btn || 'Devam et',
    });
    leadForm.appendChild(leadSubmit);
    var leadCancel = el('button', 'f2f-ai-lead-cancel', {
      type: 'button',
      text: leadCfg.cancel || 'Vazgeç',
    });
    leadForm.appendChild(leadCancel);
    lBody.appendChild(leadForm);
    leadScreen.appendChild(lBody);
    panel.appendChild(leadScreen);

    leadCancel.addEventListener('click', function () {
      state.pendingIntent = '';
      state.pendingService = '';
      showScreen('discover');
    });

    leadForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (state.busy) return;
      leadErr.classList.remove('is-visible');
      var payload = {
        first_name: firstField.input.value.trim(),
        last_name: lastField.input.value.trim(),
        phone: phoneField.input.value.trim(),
        email: emailField.input.value.trim(),
        service: state.pendingService,
        intent: state.pendingIntent,
      };
      if (!payload.first_name || !payload.last_name || !payload.phone || !payload.email) {
        leadErr.textContent = i18n.required || 'Lütfen tüm alanları doldurun.';
        leadErr.classList.add('is-visible');
        return;
      }
      state.busy = true;
      leadSubmit.disabled = true;
      api('lead', payload)
        .then(function (result) {
          if (!result.ok) {
            var msg =
              (result.data && result.data.message) ||
              i18n.error ||
              'Error';
            leadErr.textContent = msg;
            leadErr.classList.add('is-visible');
            return;
          }
          state.lead = payload;
          state.history = [];
          showScreen('chat');
          whatsappBtn.classList.add('is-visible');
          messages.innerHTML = '';
          var welcome =
            (result.data && result.data.welcome) ||
            cfg.chatWelcome ||
            '';
          welcome = String(welcome)
            .replace(/\{ad\}/g, payload.first_name)
            .replace(/\{soyad\}/g, payload.last_name)
            .replace(/\{hizmet\}/g, payload.service || 'proje');
          if (welcome) {
            appendBubble('bot', welcome);
            state.history.push({ role: 'assistant', content: welcome });
          }
          // If user typed a free-text intent (not just service label), send it to OpenAI
          if (payload.intent && payload.intent !== payload.service) {
            appendBubble('user', payload.intent);
            state.history.push({ role: 'user', content: payload.intent });
            sendChat(payload.intent, true);
          } else if (payload.service) {
            // Seed with service choice as first user message for context
            var seed = payload.service + ' hakkında bilgi almak istiyorum.';
            appendBubble('user', seed);
            state.history.push({ role: 'user', content: seed });
            sendChat(seed, true);
          }
        })
        .catch(function () {
          leadErr.textContent = i18n.offline || i18n.error || 'Offline';
          leadErr.classList.add('is-visible');
        })
        .finally(function () {
          state.busy = false;
          leadSubmit.disabled = false;
        });
    });

    // --- Chat screen ---
    var chatScreen = el('div', 'f2f-ai-screen', { 'data-screen': 'chat' });
    var messages = el('div', 'f2f-ai-messages');
    chatScreen.appendChild(messages);

    var cFooter = el('div', 'f2f-ai-footer');
    var cForm = el('form', 'f2f-ai-composer');
    var cInput = el('input', null, {
      type: 'text',
      placeholder: cfg.inputPlaceholder || '',
      autocomplete: 'off',
      maxlength: '2000',
    });
    var cSend = el('button', 'f2f-ai-send', {
      type: 'submit',
      'aria-label': i18n.send || 'Send',
      html: '<svg viewBox="0 0 24 24"><path d="M12 4l8 8h-5v8h-6v-8H4l8-8z"/></svg>',
    });
    cForm.appendChild(cInput);
    cForm.appendChild(cSend);
    cFooter.appendChild(cForm);

    var whatsappBtn = el('a', 'f2f-ai-whatsapp', {
      href: whatsappUrl(),
      target: '_blank',
      rel: 'noopener noreferrer',
      text: (cfg.whatsapp && cfg.whatsapp.label) || 'Canlı Görüşmeye Başla',
    });
    cFooter.appendChild(whatsappBtn);
    chatScreen.appendChild(cFooter);
    panel.appendChild(chatScreen);

    cForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (state.busy) return;
      var text = (cInput.value || '').trim();
      if (!text) return;
      cInput.value = '';
      appendBubble('user', text);
      state.history.push({ role: 'user', content: text });
      sendChat(text, false);
    });

    root.appendChild(panel);
    if (teaser) root.appendChild(teaser);
    root.appendChild(launcher);

    minBtn.addEventListener('click', function () {
      root.classList.remove('is-open');
    });

    function fieldBlock(label, name, placeholder, type) {
      var wrap = el('div', 'f2f-ai-field');
      wrap.appendChild(el('label', null, { text: label, for: 'f2f_' + name }));
      var input = el('input', null, {
        id: 'f2f_' + name,
        name: name,
        type: type || 'text',
        placeholder: placeholder || '',
        autocomplete: name === 'email' ? 'email' : name === 'phone' ? 'tel' : 'name',
        required: 'required',
      });
      wrap.appendChild(input);
      return { wrap: wrap, input: input };
    }

    function showScreen(name) {
      state.screen = name;
      [discover, leadScreen, chatScreen].forEach(function (scr) {
        scr.classList.toggle('is-active', scr.getAttribute('data-screen') === name);
      });
      if (name === 'lead') {
        firstField.input.focus();
      } else if (name === 'chat') {
        cInput.focus();
      } else {
        dInput.focus();
      }
    }

    function goLead(intent, serviceLabel) {
      state.pendingIntent = intent || '';
      state.pendingService = serviceLabel || intent || '';
      showScreen('lead');
    }

    function appendBubble(role, text, extra) {
      var bubble = el(
        'div',
        'f2f-ai-bubble is-' + role + (extra ? ' ' + extra : ''),
        { text: text }
      );
      messages.appendChild(bubble);
      messages.scrollTop = messages.scrollHeight;
      return bubble;
    }

    function appendTyping() {
      var node = el('div', 'f2f-ai-typing-label', { text: i18n.thinking || 'Ajan yazıyor...' });
      messages.appendChild(node);
      messages.scrollTop = messages.scrollHeight;
      return node;
    }

    function sendChat(text, historyAlreadyHasUser) {
      state.busy = true;
      cSend.disabled = true;
      var typing = appendTyping();
      var hist = historyAlreadyHasUser
        ? state.history.slice(0, -1).filter(function (t) {
            return t.role === 'user' || t.role === 'assistant';
          })
        : state.history.slice(0, -1);

      // Don't send the welcome assistant message duplicates awkwardly — keep last 12
      hist = hist.slice(-12);

      api('chat', {
        message: text,
        history: hist,
        lead: state.lead,
      })
        .then(function (result) {
          typing.remove();
          if (!result.ok) {
            var err =
              (result.data && result.data.message) ||
              i18n.error ||
              'Error';
            appendBubble('error', err, 'is-error');
            return;
          }
          var reply = (result.data && result.data.reply) || '';
          if (!reply) {
            appendBubble('error', i18n.error || 'Error', 'is-error');
            return;
          }
          appendBubble('bot', reply);
          state.history.push({ role: 'assistant', content: reply });
          if (state.history.length > 24) {
            state.history = state.history.slice(-24);
          }
        })
        .catch(function () {
          typing.remove();
          appendBubble('error', i18n.offline || i18n.error || 'Offline', 'is-error');
        })
        .finally(function () {
          state.busy = false;
          cSend.disabled = false;
          cInput.focus();
        });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
