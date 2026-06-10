
with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

inline_html = '''
  <!-- INLINE CHAT SECTION -->
  <section class="terminal-section reveal" id="chat-baiwor" style="padding: 40px 5% 80px;">
    <div style="max-width:740px; width:100%; margin:0 auto;">
      <div style="text-align:center; margin-bottom:32px;">
        <div class="section-tag" style="justify-content:center;">Coba Langsung</div>
        <h2 class="section-title">Ngobrol dengan <span class="gold">bAIwor</span></h2>
        <p style="color:var(--white-dim); font-size:0.9rem; margin-top:8px;">Tanya apa saja — Bahasa Jawa, Ngapak, atau Indonesia</p>
      </div>
      <div style="background:rgba(0,0,0,0.7);border:1px solid rgba(201,168,76,0.3);border-radius:8px;overflow:hidden;box-shadow:0 20px 80px rgba(0,0,0,0.6);">
        <div style="background:rgba(201,168,76,0.08);padding:12px 20px;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(201,168,76,0.15);">
          <div class="t-dot red"></div><div class="t-dot yellow"></div><div class="t-dot green"></div>
          <img src="bAIwor_pp.png" alt="bAIwor" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--gold);margin-left:8px;">
          <span style="font-family:'Share Tech Mono',monospace;font-size:0.72rem;color:rgba(201,168,76,0.7);letter-spacing:0.1em;">bAIwor_chat.sh</span>
          <span style="margin-left:auto;font-family:'Share Tech Mono',monospace;font-size:0.65rem;"><span style="color:#00ff88;">&#9679;</span> <span style="color:rgba(245,240,232,0.4);">Online</span></span>
        </div>
        <div id="inlineChatMessages" style="min-height:260px;max-height:380px;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:14px;font-family:'Rajdhani',sans-serif;scrollbar-width:thin;scrollbar-color:#8b6914 transparent;">
          <div class="inline-msg-bot">
            <span class="inline-prompt">bAIwor@purwokerto:~$</span>
            Sugeng rawuh! &#x1F64F; Kula bAIwor — siap mbantu panjenengan. Wonten ingkang saged kula bantu?
          </div>
        </div>
        <div style="padding:16px 20px;border-top:1px solid rgba(201,168,76,0.12);display:flex;gap:10px;align-items:center;background:rgba(0,0,0,0.3);">
          <span style="font-family:'Share Tech Mono',monospace;font-size:1rem;color:#c9a84c;flex-shrink:0;">&#9656;</span>
          <input type="text" id="inlineChatInput" placeholder="Ketik pertanyaan..." onkeypress="handleInlineKeyPress(event)" class="inline-input">
          <button onclick="sendInlineMessage()" class="inline-send-btn">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#080808;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </button>
        </div>
      </div>
    </div>
  </section>

'''

inline_css = '''
    /* ===== INLINE CHAT ===== */
    .inline-msg-bot {
      background: rgba(201,168,76,0.06);
      border: 1px solid rgba(201,168,76,0.15);
      border-radius: 0 10px 10px 10px;
      padding: 12px 16px;
      color: var(--white);
      font-size: 0.92rem;
      line-height: 1.6;
      max-width: 85%;
      align-self: flex-start;
    }
    .inline-msg-user {
      background: linear-gradient(135deg, var(--gold-dark), var(--gold));
      border-radius: 10px 10px 0 10px;
      padding: 12px 16px;
      color: #080808;
      font-size: 0.92rem;
      font-weight: 600;
      max-width: 85%;
      align-self: flex-end;
    }
    .inline-prompt {
      font-family: 'Share Tech Mono', monospace;
      font-size: 0.65rem;
      color: var(--gold);
      display: block;
      margin-bottom: 6px;
    }
    .inline-input {
      flex: 1;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(201,168,76,0.2);
      border-radius: 6px;
      padding: 12px 16px;
      color: var(--white);
      font-family: 'Rajdhani', sans-serif;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.3s;
    }
    .inline-input:focus { border-color: var(--gold); }
    .inline-send-btn {
      background: linear-gradient(135deg, var(--gold-dark), var(--gold));
      border: none;
      width: 44px;
      height: 44px;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: opacity 0.2s;
    }
    .inline-send-btn:hover { opacity: 0.8; }

'''

inline_js = '''
    // === INLINE CHAT ===
    const inlineMsgs = document.getElementById('inlineChatMessages');
    const inlineInput = document.getElementById('inlineChatInput');

    function appendInlineMsg(role, text) {
      const d = document.createElement('div');
      if (role === 'bot') {
        d.className = 'inline-msg-bot';
        d.innerHTML = '<span class="inline-prompt">bAIwor@purwokerto:~$</span>' + text.replace(/\\n/g, '<br>');
      } else {
        d.className = 'inline-msg-user';
        d.textContent = text;
      }
      inlineMsgs.appendChild(d);
      inlineMsgs.scrollTop = inlineMsgs.scrollHeight;
      return d;
    }

    function handleInlineKeyPress(e) {
      if (e.key === 'Enter') sendInlineMessage();
    }

    async function sendInlineMessage() {
      const text = inlineInput.value.trim();
      if (!text) return;
      appendInlineMsg('user', text);
      inlineInput.value = '';
      const loading = appendInlineMsg('bot', '<i>Saweg mikir...</i>');
      const reply = await getChatResponse(text);
      loading.innerHTML = '<span class="inline-prompt">bAIwor@purwokerto:~$</span>' + reply.replace(/\\n/g, '<br>');
      inlineMsgs.scrollTop = inlineMsgs.scrollHeight;
    }

'''

# Inject CSS before closing </style>
content = content.replace('  </style>\n</head>', inline_css + '  </style>\n</head>', 1)

# Inject HTML before <!-- FEATURES SECTION -->
content = content.replace('  <!-- FEATURES SECTION -->', inline_html + '  <!-- FEATURES SECTION -->', 1)

# Inject JS before Scroll reveal
content = content.replace('    // Scroll reveal', inline_js + '    // Scroll reveal', 1)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)

print('Done! Inline chat injected successfully.')
