(function () {
  if (!window.F2FChatbot) return;

  var root = document.getElementById("f2f-chatbot-root");
  if (!root) return;

  var history = [];

  root.innerHTML =
    '<button type="button" class="f2f-chat-toggle" aria-label="Sohbet">💬</button>' +
    '<div class="f2f-chat-panel" role="dialog">' +
    '<div class="f2f-chat-head">' +
    (F2FChatbot.botName || "Asistan") +
    "</div>" +
    '<div class="f2f-chat-messages"></div>' +
    '<form class="f2f-chat-form"><input type="text" placeholder="Mesajınız..." autocomplete="off" required />' +
    '<button type="submit">Gönder</button></form></div>';

  var toggle = root.querySelector(".f2f-chat-toggle");
  var panel = root.querySelector(".f2f-chat-panel");
  var messagesEl = root.querySelector(".f2f-chat-messages");
  var form = root.querySelector(".f2f-chat-form");
  var input = form.querySelector("input");

  function append(role, text) {
    var div = document.createElement("div");
    div.className = "f2f-msg " + (role === "user" ? "user" : "bot");
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  append("bot", F2FChatbot.welcome || "Merhaba!");

  toggle.addEventListener("click", function () {
    panel.classList.toggle("open");
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    input.value = "";
    append("user", text);
    history.push({ role: "user", content: text });

    var body = new FormData();
    body.append("action", "f2f_chatbot_message");
    body.append("nonce", F2FChatbot.nonce);
    body.append("message", text);
    body.append("history", JSON.stringify(history.slice(0, -1)));

    fetch(F2FChatbot.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.success) {
          throw new Error((data.data && data.data.message) || "Hata");
        }
        var reply = data.data.reply || "";
        append("bot", reply);
        history.push({ role: "assistant", content: reply });
      })
      .catch(function (err) {
        append("bot", err.message || "Bağlantı hatası.");
      });
  });
})();
