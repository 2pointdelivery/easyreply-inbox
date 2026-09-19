/**
 * EasyReply website widget loader.
 *
 * Usage:
 *   <script src="https://your-app.test/shared-inbox/widget.js"
 *           data-inbox="123" data-token="..." data-title="Chat with us"
 *           data-color="#4f46e5" defer></script>
 *
 * Injects a floating button + iframe. The iframe hosts the chat/call UI;
 * this loader only handles positioning so host-page styles never clash
 * with the widget (all widget CSS lives inside the iframe document).
 */
(function () {
  var scripts = document.getElementsByTagName('script');
  var loader = null;
  for (var i = 0; i < scripts.length; i++) {
    if ((scripts[i].getAttribute('src') || '').indexOf('/shared-inbox/widget.js') !== -1) {
      loader = scripts[i];
      break;
    }
  }
  if (!loader) return;

  var src = loader.getAttribute('src');
  var base = src.slice(0, src.indexOf('/shared-inbox/widget.js'));
  var inbox = loader.getAttribute('data-inbox') || '';
  var token = loader.getAttribute('data-token') || '';
  var title = loader.getAttribute('data-title') || 'Chat with us';
  var color = loader.getAttribute('data-color') || '#4f46e5';
  if (!inbox || !token) return;

  var host = document.createElement('div');
  host.setAttribute('id', 'easyreply-widget-host');
  host.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:2147483000;font-family:system-ui,sans-serif;';

  var button = document.createElement('button');
  button.setAttribute('type', 'button');
  button.setAttribute('aria-label', 'Open chat');
  button.style.cssText = 'width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;color:#fff;font-size:24px;background:' + color + ';box-shadow:0 4px 14px rgba(0,0,0,.25);';
  button.textContent = '\u2709';

  var frame = document.createElement('iframe');
  frame.setAttribute('title', title);
  frame.style.cssText = 'display:none;width:360px;max-width:calc(100vw - 40px);height:520px;max-height:calc(100vh - 120px);border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.2);margin-bottom:12px;';
  frame.src = base + '/shared-inbox/widget?inbox=' + encodeURIComponent(inbox) +
    '&token=' + encodeURIComponent(token) +
    '&title=' + encodeURIComponent(title) +
    '&color=' + encodeURIComponent(color);

  var open = false;
  button.addEventListener('click', function () {
    open = !open;
    frame.style.display = open ? 'block' : 'none';
    button.textContent = open ? '\u00d7' : '\u2709';
  });

  var wrapper = document.createElement('div');
  wrapper.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;';
  wrapper.appendChild(frame);
  wrapper.appendChild(button);
  host.appendChild(wrapper);
  document.body.appendChild(host);
})();
