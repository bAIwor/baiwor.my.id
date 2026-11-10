/* bAIwor floating chat bubble — self-contained, pasang sebelum </body> */
(function () {
  var BASE = "https://baiwor.my.id";

  var css = document.createElement("style");
  css.textContent = [
    ".baiwor-chat-bubble{position:fixed;right:25px;bottom:25px;z-index:9999;width:65px;height:65px;border-radius:50%;background:linear-gradient(135deg,#c9a84c,#f0c94a);border:2px solid rgba(255,255,255,0.25);box-shadow:0 8px 32px rgba(201,168,76,0.4),0 4px 16px rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;text-decoration:none!important;cursor:pointer;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);overflow:visible;}",
    ".baiwor-chat-bubble:hover{transform:scale(1.1) rotate(5deg);box-shadow:0 12px 40px rgba(201,168,76,0.65),0 6px 20px rgba(0,0,0,0.6);}",
    ".baiwor-chat-bubble img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;}",
    ".baiwor-chat-bubble::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(212,160,23,0.6);animation:bcbPulse 2.4s ease-out infinite;pointer-events:none;}",
    "@keyframes bcbPulse{0%{opacity:0.8;transform:scale(1);}70%{opacity:0;transform:scale(1.15);}100%{opacity:0;}}",
    ".baiwor-chat-bubble .bcb-status{position:absolute;bottom:2px;right:2px;width:14px;height:14px;background:#00ff88;border:2px solid #0e0c0a;border-radius:50%;box-shadow:0 0 8px #00ff88;z-index:2;}",
    ".baiwor-chat-bubble .bcb-tooltip{position:absolute;right:calc(100% + 14px);top:50%;transform:translateY(-50%) translateX(6px);background:rgba(14,12,10,0.92);backdrop-filter:blur(8px);border:1px solid rgba(201,168,76,0.35);color:#f5f0e8;padding:6px 14px;border-radius:8px;font-family:'Rajdhani',system-ui,sans-serif;font-size:0.88rem;font-weight:600;letter-spacing:0.02em;white-space:nowrap;pointer-events:none;opacity:0;visibility:hidden;transition:opacity 0.25s ease,transform 0.25s ease,visibility 0.25s;box-shadow:0 6px 20px rgba(0,0,0,0.5);}",
    ".baiwor-chat-bubble:hover .bcb-tooltip{opacity:1;visibility:visible;transform:translateY(-50%) translateX(0);}",
    "@media(max-width:480px){.baiwor-chat-bubble{width:56px;height:56px;right:16px;bottom:16px;}.baiwor-chat-bubble .bcb-status{width:12px;height:12px;bottom:1px;right:1px;}.baiwor-chat-bubble .bcb-tooltip{display:none;}}"
  ].join("\n");
  document.head.appendChild(css);

  var a = document.createElement("a");
  a.href = "https://chat.baiwor.my.id/";
  a.className = "baiwor-chat-bubble";
  a.setAttribute("aria-label", "Ngobrol dengan bAIwor");
  a.setAttribute("title", "Ngobrol dengan bAIwor");
  a.innerHTML =
    '<img src="' + BASE + '/baiwor_pp_128.png" alt="bAIwor">' +
    '<span class="bcb-status"></span>' +
    '<span class="bcb-tooltip">Ngobrol dengan bAIwor</span>';

  document.body.appendChild(a);
})();
