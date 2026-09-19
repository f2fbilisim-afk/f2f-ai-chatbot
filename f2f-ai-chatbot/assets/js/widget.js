(function () {
  'use strict';

  var cfg = window.f2fAiChatbot || {};
  if (!cfg.restUrl) {
    return;
  }

  var history = [];
  var busy = false;

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        if (key === 'text') node.textContent = attrs[key];
        else if (key === 'html') node.innerHTML = attrs[key];
        else node.setAttribute(key, attrs[key]);
      });
    }
    return node;
  }

  function initials(name) {
    return String(name || 'AI')
      .split(/\s+/)
      .slice(0, 2)
      .map(function (p) {
        return p.charAt(0).toUpperCase();
      })
      .join('');
  }

  function mount() {
    var root = document.getElementById('f2f-ai-chatbot-root');
    if (!root) return;

    if (cfg.primaryColor) {
      root.style.setProperty('--f2f-primary', cfg.primaryColor);
    }
    root.setAttribute('data-position', cfg.position === 'left' ? 'left' : 'right');

    var i18n = cfg.i18n || {};

    var launcher = el('button', 'f2f-ai-launcher', {
      type: 'button',
      'aria-label': i18n.open || 'Open chat',
      html:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 5v-5H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm3 5v2h10V9H7zm0 4v2h7v-2H7z"/></svg>',
    });

    var panel = el('div', 'f2f-ai-panel', {
      role: 'dialog',
      'aria-label': cfg.botName || 'Chatbot',
    });

    var header = el('div', 'f2f-ai-header');
    header.appendChild(el('div', 'f2f-ai-avatar', { text: initials(cfg.botName) }));
    var headerText = el('div', 'f2f-ai-header-text');
    headerText.appendChild(el('strong', null, { text: cfg.botName || 'Asistan' }));
    headerText.appendChild(el('span', null, { text: 'Online' }));
    header.appendChild(headerText);
    var closeBtn = el('button', 'f2f-ai-close', {
      type: 'button',
      'aria-label': i18n.close || 'Close',
      text: '×',
    });
    header.appendChild(closeBtn);

    var messages = el('div', 'f2f-ai-messages');
    var composer = el('form', 'f2f-ai-composer');
    var input = el('input', null, {
      type: 'text',
      placeholder: i18n.placeholder || 'Mesajınızı yazın…',
      autocomplete: 'off',
      maxlength: '2000',
    });
    var sendBtn = el('button', null, { type: 'submit', text: i18n.send || 'Gönder' });
    composer.appendChild(input);
    composer.appendChild(sendBtn);

    panel.appendChild(header);
    panel.appendChild(messages);
    panel.appendChild(composer);
    root.appendChild(panel);
    root.appendChild(launcher);

    function setOpen(open) {
      root.classList.toggle('is-open', open);
      if (open) {
        input.focus();
      }
    }

    function appendBubble(role, text, extraClass) {
      var bubble = el(
        'div',
        'f2f-ai-bubble is-' + role + (extraClass ? ' ' + extraClass : ''),
        { text: text }
      );
      messages.appendChild(bubble);
      messages.scrollTop = messages.scrollHeight;
      return bubble;
    }

    function appendTyping() {
      var bubble = el('div', 'f2f-ai-bubble is-bot');
      var dots = el('span', 'f2f-ai-typing', {
        'aria-label': i18n.thinking || 'Yazıyor…',
        html: '<i></i><i></i><i></i>',
      });
      bubble.appendChild(dots);
      messages.appendChild(bubble);
      messages.scrollTop = messages.scrollHeight;
      return bubble;
    }

    if (cfg.welcomeMessage) {
      appendBubble('bot', cfg.welcomeMessage);
    }

    launcher.addEventListener('click', function () {
      setOpen(true);
    });
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });

    composer.addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy) return;
      var text = (input.value || '').trim();
      if (!text) return;

      input.value = '';
      appendBubble('user', text);
      history.push({ role: 'user', content: text });
      send(text);
    });

    function send(text) {
      busy = true;
      sendBtn.disabled = true;
      var typing = appendTyping();

      var payload = {
        message: text,
        history: history.slice(0, -1),
      };

      fetch(cfg.restUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce || '',
        },
        body: JSON.stringify(payload),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, status: res.status, data: data };
          });
        })
        .then(function (result) {
          typing.remove();
          if (!result.ok) {
            var err =
              (result.data && (result.data.message || (result.data.data && result.data.data.message))) ||
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
          history.push({ role: 'assistant', content: reply });
          if (history.length > 24) {
            history = history.slice(-24);
          }
        })
        .catch(function () {
          typing.remove();
          appendBubble('error', i18n.offline || i18n.error || 'Offline', 'is-error');
        })
        .finally(function () {
          busy = false;
          sendBtn.disabled = false;
          input.focus();
        });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
