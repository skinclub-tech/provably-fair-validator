// The analytics script is injected asynchronously after this file, so events
// fired during page load are queued until it is ready instead of being lost.
const ANALYTICS_WAIT_MS = 10000;
const ANALYTICS_POLL_MS = 200;
const ANALYTICS_QUEUE_LIMIT = 20;
const analyticsQueue = [];
let analyticsTimer = null;

function analyticsReady() {
  return typeof window.umami?.track === 'function';
}

function sendEvent(name, data) {
  try {
    const pending = window.umami.track(name, data);
    // Some trackers return a promise; rejected requests must not affect the UI.
    if (pending && typeof pending.catch === 'function') pending.catch(() => {});
  } catch {
    // Analytics must never break verification.
  }
}

function flushAnalytics() {
  while (analyticsQueue.length) {
    const [name, data] = analyticsQueue.shift();
    sendEvent(name, data);
  }
}

function trackEvent(name, data) {
  if (analyticsReady() && analyticsQueue.length === 0) {
    sendEvent(name, data);
    return;
  }
  if (analyticsQueue.length >= ANALYTICS_QUEUE_LIMIT) return;
  analyticsQueue.push([name, data]);
  if (analyticsTimer) return;

  const startedAt = Date.now();
  analyticsTimer = setInterval(() => {
    if (analyticsReady()) {
      flushAnalytics();
    } else if (Date.now() - startedAt < ANALYTICS_WAIT_MS) {
      return;
    } else {
      // Analytics is disabled or blocked (e.g. in development): drop the queue.
      analyticsQueue.length = 0;
    }
    clearInterval(analyticsTimer);
    analyticsTimer = null;
  }, ANALYTICS_POLL_MS);
}

// Forward only flat values from the server-rendered verification metadata.
function verificationEventData(raw) {
  const event = JSON.parse(raw);
  const data = {};
  for (const [key, value] of Object.entries(event)) {
    if (['string', 'number', 'boolean'].includes(typeof value)) data[key] = value;
  }
  return data;
}

// Mock roll data in the format the site's copy button produces. Never use
// real roll data here.
const SAMPLES = {
  regular: {
    client_seed: "my_seed",
    server_seed: "0d2ade1251ce04077d6020a5fb081c36",
    secret_salt: "b07bbd68cf0ba687",
    public_hash: "924ef42387a290388c5258d00660a7885cb27e3a7bd15bacee2f0242d9615b77",
    nonce: 7,
    roll: 10477,
    created_at: "2026-01-01 12:00:00"
  },
  battle: {
    type: "battle",
    beacon: "11TD1na4qZZonViCx9NbqbQ1cyiVtQ51",
    client_seed: "my_seed,player_2,player_3,player_4",
    nonce: 3,
    roll: 81575
  }
};
function autoSize(ta) {
  ta.style.height = 'auto';
  ta.style.height = ta.scrollHeight + 'px';
}
function loadSample(kind) {
  const ta = document.getElementById('roll_data');
  ta.value = JSON.stringify(SAMPLES[kind], null, 2);
  autoSize(ta);
  ta.focus();
  trackEvent('sample_selected', { roll_type: kind });
}
document.addEventListener('DOMContentLoaded', () => {
  const rawVerificationEvent = document.body.dataset.verificationEvent;
  if (rawVerificationEvent) {
    try {
      trackEvent('verification_completed', verificationEventData(rawVerificationEvent));
    } catch {
      // Ignore malformed analytics metadata.
    }
  }
  const ta = document.getElementById('roll_data');
  if (!ta) return;
  autoSize(ta);
  ta.addEventListener('input', () => autoSize(ta));
  ta.addEventListener('paste', () => {
    setTimeout(() => {
      try {
        ta.value = JSON.stringify(JSON.parse(ta.value), null, 2);
      } catch (e) {}
      autoSize(ta);
    }, 0);
  });

  const form = document.getElementById('roll-form');
  if (form) {
    const btn = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', () => {
      trackEvent('verification_submitted');
      if (!btn) return;
      btn.textContent = 'Checking…';
      btn.disabled = true;
      btn.classList.add('btn-loading');
    });
    window.addEventListener('pageshow', () => {
      if (!btn) return;
      btn.textContent = 'Check';
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    });
  }
});
