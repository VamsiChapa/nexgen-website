/* ================================================================
   NEx-gEN Chatbot Engine
   Depends on: chatbot-data.js (loaded first)
   ================================================================ */
(function () {
  'use strict';

  /* ── Build HTML ──────────────────────────────────────────────── */
  const html = `
  <div id="cbLabel" class="cb-label">💬 Chat with us!</div>
  <div class="cb-bubble" id="cbBubble" aria-label="Open chat assistant" role="button" tabindex="0">
    <svg class="cb-bubble__icon cb-bubble__icon--chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <svg class="cb-bubble__icon cb-bubble__icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
    <span class="cb-unread" id="cbUnread">1</span>
  </div>

  <div class="cb-window" id="cbWindow" role="dialog" aria-label="NEx-gEN Chat Assistant">
    <div class="cb-header">
      <div class="cb-header__avatar">${NEXGEN_BOT.avatar}</div>
      <div class="cb-header__info">
        <strong>${NEXGEN_BOT.name}</strong>
        <span class="cb-online"><span class="cb-dot"></span>Online</span>
      </div>
      <button class="cb-header__close" id="cbClose" aria-label="Close chat">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="cb-messages" id="cbMessages"></div>

    <div class="cb-chips" id="cbChips"></div>

    <div class="cb-input-row">
      <input type="text" id="cbInput" placeholder="Type your question…" autocomplete="off" />
      <button class="cb-send" id="cbSend" aria-label="Send">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
        </svg>
      </button>
    </div>
  </div>`;

  const wrapper = document.createElement('div');
  wrapper.className = 'cb-root';
  wrapper.innerHTML = html;
  document.body.appendChild(wrapper);

  /* ── Element refs ────────────────────────────────────────────── */
  const bubble   = document.getElementById('cbBubble');
  const label    = document.getElementById('cbLabel');
  const win      = document.getElementById('cbWindow');
  const msgs     = document.getElementById('cbMessages');
  const chipsEl  = document.getElementById('cbChips');
  const input    = document.getElementById('cbInput');
  const sendBtn  = document.getElementById('cbSend');
  const closeBtn = document.getElementById('cbClose');
  const unread   = document.getElementById('cbUnread');

  let isOpen   = false;
  let greeted  = false;

  /* ── Open / Close ────────────────────────────────────────────── */
  function openChat() {
    isOpen = true;
    win.classList.add('cb-window--open');
    bubble.classList.add('cb-bubble--open');
    unread.style.display = 'none';
    if (label) label.classList.add('hidden');
    if (!greeted) { greet(); greeted = true; }
    setTimeout(() => input.focus(), 320);
  }

  function closeChat() {
    isOpen = false;
    win.classList.remove('cb-window--open');
    bubble.classList.remove('cb-bubble--open');
  }

  bubble.addEventListener('click', () => isOpen ? closeChat() : openChat());
  bubble.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); isOpen ? closeChat() : openChat(); } });
  closeBtn.addEventListener('click', closeChat);

  /* ── Greeting ────────────────────────────────────────────────── */
  function greet() {
    appendBot(NEXGEN_BOT.greeting);
    renderChips(NEXGEN_BOT.openingChips);
  }

  /* ── Append messages ─────────────────────────────────────────── */
  function appendUser(text) {
    const div = document.createElement('div');
    div.className = 'cb-msg cb-msg--user';
    div.textContent = text;
    msgs.appendChild(div);
    scrollBottom();
  }

  function appendBot(text, delay = 0) {
    showTyping();
    setTimeout(() => {
      hideTyping();
      const div = document.createElement('div');
      div.className = 'cb-msg cb-msg--bot';
      /* Convert newlines to <br> and URLs to links */
      div.innerHTML = escapeAndFormat(text);
      msgs.appendChild(div);
      scrollBottom();
    }, delay || typingDelay(text));
  }

  /* ── Typing indicator ────────────────────────────────────────── */
  let typingEl = null;
  function showTyping() {
    if (typingEl) return;
    typingEl = document.createElement('div');
    typingEl.className = 'cb-msg cb-msg--bot cb-typing';
    typingEl.innerHTML = '<span></span><span></span><span></span>';
    msgs.appendChild(typingEl);
    scrollBottom();
  }
  function hideTyping() {
    if (typingEl) { typingEl.remove(); typingEl = null; }
  }
  function typingDelay(text) {
    /* Shorter text = faster reply (min 600ms, max 1400ms) */
    return Math.min(600 + text.length * 8, 1400);
  }

  /* ── Quick reply chips ───────────────────────────────────────── */
  function renderChips(chips) {
    chipsEl.innerHTML = '';
    if (!chips || !chips.length) return;
    chips.forEach(label => {
      const btn = document.createElement('button');
      btn.className = 'cb-chip';
      btn.textContent = label;
      btn.addEventListener('click', () => {
        chipsEl.innerHTML = '';
        handleInput(label);
      });
      chipsEl.appendChild(btn);
    });
  }

  /* ── Send ────────────────────────────────────────────────────── */
  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

  function sendMessage() {
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    chipsEl.innerHTML = '';
    handleInput(text);
  }

  /* ── Handle input ────────────────────────────────────────────── */
  function handleInput(text) {
    appendUser(text);
    const intent = findIntent(text);
    if (intent) {
      const response = pick(intent.responses);
      appendBot(response);
      if (intent.quickReplies) {
        setTimeout(() => renderChips(intent.quickReplies), typingDelay(response) + 100);
      }
    } else {
      appendBot(pick(NEXGEN_BOT.fallback));
    }
  }

  /* ── Intent matching ─────────────────────────────────────────── */
  function findIntent(text) {
    const q = normalise(text);
    let best = null;
    let bestScore = 0;

    for (const intent of NEXGEN_BOT.intents) {
      let score = 0;
      for (const kw of intent.keywords) {
        if (q.includes(normalise(kw))) {
          /* Longer keyword match = higher confidence */
          score = Math.max(score, kw.length);
        }
      }
      if (score > bestScore) { bestScore = score; best = intent; }
    }
    return bestScore > 0 ? best : null;
  }

  function normalise(s) {
    return s.toLowerCase().replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  /* ── Formatting helpers ──────────────────────────────────────── */
  function escapeAndFormat(text) {
    /* Escape HTML first */
    const escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');

    /* Convert URLs to clickable links */
    const linked = escaped.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener">$1</a>'
    );

    /* Convert newlines to <br> */
    return linked.replace(/\n/g, '<br>');
  }

  function scrollBottom() {
    msgs.scrollTop = msgs.scrollHeight;
  }

  /* ── Show unread badge after 4s if not opened ────────────────── */
  setTimeout(() => {
    if (!isOpen) { unread.style.display = 'flex'; }
  }, 4000);

  /* ── Hide "Chat with us" label after 8s ──────────────────────── */
  setTimeout(() => {
    if (label && !isOpen) label.classList.add('hidden');
  }, 8000);

})();
