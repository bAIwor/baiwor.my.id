/* bAIwor floating chat bubble — self-contained, pasang sebelum </body> */
(function () {
  var BASE = "https://baiwor.my.id";

  var css = document.createElement("style");
  css.textContent = [
    ".baiwor-chat-bubble{position:fixed;right:20px;bottom:20px;z-index:9999;display:flex;align-items:center;gap:10px;padding:10px 18px 10px 10px;border-radius:999px;background:linear-gradient(135deg,#d4a017,#f0c94a);color:#1a1208;font-weight:700;font-family:inherit;font-size:0.95rem;text-decoration:none!important;box-shadow:0 8px 24px rgba(0,0,0,0.45);transition:transform .2s ease,box-shadow .2s ease;}",
    ".baiwor-chat-bubble:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(212,160,23,0.4);}",
    ".baiwor-chat-bubble img{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.85);}",
    ".baiwor-chat-bubble .bcb-label{line-height:1.2;}",
    ".baiwor-chat-bubble .bcb-sub{display:block;font-size:0.68rem;font-weight:600;opacity:0.8;}",
    ".baiwor-chat-bubble::after{content:'';position:absolute;inset:-4px;border-radius:inherit;border:2px solid rgba(212,160,23,0.5);animation:bcbPulse 2.4s ease-out infinite;}",
    ".baiwor-chat-bubble{overflow:visible;}",
    "@keyframes bcbPulse{0%{opacity:0.8;transform:scale(1);}70%{opacity:0;transform:scale(1.12);}100%{opacity:0;}}",
    "@media(max-width:480px){.baiwor-chat-bubble{right:14px;bottom:14px;padding:8px 14px 8px 8px;}.baiwor-chat-bubble img{width:34px;height:34px;}.baiwor-chat-bubble .bcb-sub{display:none;}}"
  ].join("\n");
  document.head.appendChild(css);

  var a = document.createElement("a");
  a.href = "https://chat.baiwor.my.id/";
  a.className = "baiwor-chat-bubble";
  a.setAttribute("aria-label", "Ngobrol dengan bAIwor");
  a.innerHTML =
    '<img src="' + BASE + '/bAIwor_pp.png" alt="bAIwor">' +
    '<span class="bcb-label">Ngobrol dengan bAIwor' +
    '<span class="bcb-sub">AI Agent Banyumas · 24/7</span></span>';

  document.body.appendChild(a);
})();
