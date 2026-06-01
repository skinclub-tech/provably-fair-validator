const SAMPLES = {
  regular: {
    client_seed: "my_seed",
    server_seed: "c4ca4238a0b92382",
    secret_salt: "0dcc509a6f75849b",
    public_hash: "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
    nonce: "4",
    roll: "21752",
    created_at: "2024-01-01 00:00:00"
  },
  battle: {
    type: "battle",
    beacon: "Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q",
    client_seed: "12354,abgd",
    nonce: "9",
    roll: "5415"
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
}
document.addEventListener('DOMContentLoaded', () => {
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
