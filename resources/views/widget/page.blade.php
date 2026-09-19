<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #111827; }
  #app { display: flex; flex-direction: column; height: 100vh; }
  header { padding: 12px 16px; color: #fff; background: {{ $color }}; }
  header h1 { margin: 0; font-size: 15px; }
  nav { display: flex; border-bottom: 1px solid #e5e7eb; }
  nav button { flex: 1; padding: 10px; border: none; background: #fff; cursor: pointer; font-size: 14px; }
  nav button.active { border-bottom: 2px solid {{ $color }}; font-weight: 600; }
  #panel { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
  .msg { max-width: 80%; padding: 8px 12px; border-radius: 12px; line-height: 1.4; }
  .msg.inbound { align-self: flex-start; background: #f3f4f6; }
  .msg.outbound { align-self: flex-end; background: {{ $color }}; color: #fff; }
  #composer { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e5e7eb; }
  #composer input { flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
  #composer button, .call-form button { padding: 8px 16px; border: none; border-radius: 8px; background: {{ $color }}; color: #fff; cursor: pointer; }
  .call-form { display: flex; flex-direction: column; gap: 10px; }
  .call-form input, .call-form textarea { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; width: 100%; }
  .note { font-size: 12px; color: #6b7280; }
  .error { color: #dc2626; font-size: 13px; }
  .ok { color: #059669; font-size: 13px; }
  #start-form { display: flex; flex-direction: column; gap: 10px; }
  #start-form input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; width: 100%; }
  #start-form button { padding: 10px; border: none; border-radius: 8px; background: {{ $color }}; color: #fff; cursor: pointer; }
</style>
</head>
<body>
<div id="app">
  <header><h1>{{ $title }}</h1></header>
  <nav>
    <button id="tab-chat" class="active" type="button">Chat</button>
    <button id="tab-call" type="button">Call</button>
  </nav>
  <div id="panel">
    <form id="start-form">
      <p class="note">Say hello — no account needed.</p>
      <input id="start-name" type="text" placeholder="Your name (optional)" maxlength="100" autocomplete="name">
      <input id="start-email" type="email" placeholder="Email (optional)" autocomplete="email">
      <button type="submit">Start chat</button>
      <p id="start-error" class="error"></p>
    </form>
    <div id="chat-view" style="display:none;flex-direction:column;gap:8px;">
      <div id="messages" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>
    <div id="call-view" style="display:none;">
      <div class="call-form">
        <p class="note" id="call-info">Request a callback and our AI receptionist will call you back.</p>
        <input id="call-phone" type="tel" placeholder="+15551234567" autocomplete="tel">
        <textarea id="call-topic" rows="2" maxlength="500" placeholder="What is this about? (optional)"></textarea>
        <button id="call-submit" type="button">Call me back</button>
        <p id="call-status"></p>
      </div>
    </div>
  </div>
  <form id="composer" style="display:none;">
    <input id="composer-input" type="text" placeholder="Type a message…" maxlength="2000" autocomplete="off">
    <button type="submit">Send</button>
  </form>
</div>
<script>
(function () {
  var inboxId = @json($inboxId);
  var token = @json($token);
  var base = window.location.origin;
  var visitorToken = null;
  var lastId = 0;
  var pollTimer = null;

  var $ = function (id) { return document.getElementById(id); };

  function api(path, options) {
    options = options || {};
    options.headers = Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, options.headers || {});
    return fetch(base + path, options).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) { var e = new Error('Request failed'); e.data = data; throw e; }
        return data;
      });
    });
  }

  $('tab-chat').addEventListener('click', function () {
    $('tab-chat').classList.add('active'); $('tab-call').classList.remove('active');
    $('call-view').style.display = 'none';
    $('chat-view').style.display = visitorToken ? 'flex' : 'none';
    $('start-form').style.display = visitorToken ? 'none' : 'flex';
    $('composer').style.display = visitorToken ? 'flex' : 'none';
  });
  $('tab-call').addEventListener('click', function () {
    $('tab-call').classList.add('active'); $('tab-chat').classList.remove('active');
    $('start-form').style.display = 'none'; $('chat-view').style.display = 'none'; $('composer').style.display = 'none';
    $('call-view').style.display = 'block';
    loadStatus();
  });

  $('start-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    $('start-error').textContent = '';
    api('/shared-inbox/widget/' + inboxId + '/start', {
      method: 'POST',
      body: JSON.stringify({
        token: token,
        name: $('start-name').value || undefined,
        email: $('start-email').value || undefined
      })
    }).then(function (data) {
      visitorToken = data.visitor_token;
      $('start-form').style.display = 'none';
      $('chat-view').style.display = 'flex';
      $('composer').style.display = 'flex';
      poll();
      pollTimer = setInterval(poll, 5000);
    }).catch(function () { $('start-error').textContent = 'Could not start chat. Try again.'; });
  });

  function bubble(m) {
    var div = document.createElement('div');
    div.className = 'msg ' + (m.direction === 'outbound' ? 'outbound' : 'inbound');
    div.textContent = m.body;
    return div;
  }

  function poll() {
    if (!visitorToken) return;
    api('/shared-inbox/widget/' + inboxId + '/messages?token=' + encodeURIComponent(token) +
      '&visitor_token=' + encodeURIComponent(visitorToken) + '&after_id=' + lastId)
      .then(function (data) {
        (data.messages || []).forEach(function (m) {
          $('messages').appendChild(bubble(m));
          if (m.id > lastId) lastId = m.id;
        });
        if (data.messages && data.messages.length) { $('panel').scrollTop = $('panel').scrollHeight; }
      }).catch(function () {});
  }

  $('composer').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var input = $('composer-input');
    if (!input.value.trim()) return;
    var body = input.value; input.value = '';
    api('/shared-inbox/widget/' + inboxId + '/messages', {
      method: 'POST',
      body: JSON.stringify({ token: token, visitor_token: visitorToken, body: body })
    }).then(function () { poll(); }).catch(function () { input.value = body; });
  });

  function loadStatus() {
    api('/shared-inbox/widget/' + inboxId + '/status?token=' + encodeURIComponent(token))
      .then(function (s) {
        var info = 'Request a callback and our AI receptionist will call you back.';
        if (s.display_number) info += ' Prefer to dial in? Call us at ' + s.display_number + '.';
        if (!s.callbacks_enabled) info = 'Callbacks are currently disabled.' + (s.display_number ? ' Call us at ' + s.display_number + '.' : '');
        $('call-info').textContent = info;
        $('call-submit').disabled = !s.callbacks_enabled;
      }).catch(function () {});
  }

  $('call-submit').addEventListener('click', function () {
    var st = $('call-status');
    st.className = ''; st.textContent = '';
    if (!visitorToken) { st.className = 'error'; st.textContent = 'Start a chat first so we can link your request.'; return; }
    api('/shared-inbox/widget/' + inboxId + '/call-request', {
      method: 'POST',
      body: JSON.stringify({
        token: token,
        visitor_token: visitorToken,
        phone: $('call-phone').value,
        topic: $('call-topic').value || undefined
      })
    }).then(function () {
      st.className = 'ok'; st.textContent = 'Request received — we will call you back shortly.';
    }).catch(function (e) {
      st.className = 'error';
      st.textContent = (e.data && e.data.message) ? e.data.message : 'Could not request a call. Check the number and try again.';
    });
  });
})();
</script>
</body>
</html>
