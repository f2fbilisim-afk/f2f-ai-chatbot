(function () {
  'use strict';

  var pill = document.getElementById('modePill');
  var openBtn = document.getElementById('openChatBtn');

  fetch('/api/config')
    .then(function (r) {
      return r.json();
    })
    .then(function (cfg) {
      if (!pill) return;
      pill.textContent = cfg.hasApiKey
        ? 'OpenAI canlı mod'
        : 'Demo mod (API anahtarı yok)';
    })
    .catch(function () {
      if (pill) pill.textContent = 'Bağlantı yok';
    });

  if (openBtn) {
    openBtn.addEventListener('click', function () {
      var launcher = document.querySelector('.f2f-ai-launcher');
      if (launcher) launcher.click();
    });
  }
})();
