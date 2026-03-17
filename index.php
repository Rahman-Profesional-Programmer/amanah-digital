<?php
// Gate check: if already blocked by cookie, we still render the page
// (client-side JS will hide the form). No server redirect needed here.
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sampaikan aspirasi Anda secara anonim dan aman.">
  <title>AMANAH Digital</title>
  <link rel="icon" type="image/png" href="assets/logo-ia-ia-copy.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

  <style>
    /* ── CSS Variables ──────────────────────── */
    :root {
      --hue: 240; --sat: 5%;
      --bg-light:           hsl(var(--hue), var(--sat), 98%);
      --surface-light:      hsl(var(--hue), var(--sat), 100%);
      --text-primary-light: hsl(var(--hue), var(--sat), 10%);
      --text-secondary-light: hsl(var(--hue), var(--sat), 40%);
      --border-light:       hsl(var(--hue), var(--sat), 88%);
      --accent:             hsl(210, 100%, 50%);
      --accent-glow:        hsla(210, 100%, 50%, 0.12);
      --danger:             hsl(0, 84%, 60%);
      --success:            hsl(142, 71%, 45%);
      --bg-dark:            hsl(var(--hue), 10%, 10%);
      --surface-dark:       hsl(var(--hue), 10%, 14%);
      --text-primary-dark:  hsl(var(--hue), 5%,  90%);
      --text-secondary-dark:hsl(var(--hue), 5%,  60%);
      --border-dark:        hsl(var(--hue), 10%, 20%);
      /* Active theme defaults */
      --bg:           var(--bg-light);
      --surface:      var(--surface-light);
      --text-primary: var(--text-primary-light);
      --text-secondary: var(--text-secondary-light);
      --border:       var(--border-light);
      --radius-sm: 6px; --radius-md: 12px;
    }
    [data-theme="dark"] {
      --bg: var(--bg-dark); --surface: var(--surface-dark);
      --text-primary: var(--text-primary-dark);
      --text-secondary: var(--text-secondary-dark);
      --border: var(--border-dark);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg); color: var(--text-primary);
      transition: background-color .3s, color .3s;
      line-height: 1.6; -webkit-font-smoothing: antialiased;
    }
    .app-container {
      max-width: 640px; margin: 0 auto; min-height: 100vh;
      display: flex; flex-direction: column;
      background: var(--surface); box-shadow: 0 0 50px rgba(0,0,0,0.05);
    }

    /* ── Hero Header ────────────────────────── */
    .hero-header { position: relative; border-bottom: 1px solid var(--border); }
    .hero-image-wrap { width: 100%; aspect-ratio: 16/9; overflow: hidden; background: var(--border); }
    .hero-image-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
    .hero-header:hover .hero-image-wrap img { transform: scale(1.02); }
    .header-content {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1.25rem 2rem;
    }
    .brand { display: flex; align-items: center; gap: .75rem; }
    .brand-text { display: flex; flex-direction: column; }
    .logo-icon { font-size: 1.4rem; }
    .brand h1 { font-size: 1.2rem; font-weight: 600; letter-spacing: -.02em; line-height: 1.2; }
    .brand .subtitle { font-size: 0.72rem; color: var(--text-secondary); }
    .icon-btn {
      background: none; border: 1px solid var(--border); border-radius: var(--radius-sm);
      padding: .5rem; cursor: pointer; color: var(--text-primary);
      transition: all .2s; display: flex; align-items: center; justify-content: center;
    }
    .icon-btn:hover { background: var(--bg); border-color: var(--text-secondary); }
    [data-theme="light"] .sun-icon { display: none; }
    [data-theme="dark"]  .moon-icon { display: none; }

    /* ── Main ────────────────────────────────── */
    main { flex: 1; padding: 2rem; }
    .intro { margin-bottom: 2rem; }
    .intro h2 { font-size: 1.55rem; font-weight: 600; letter-spacing: -.03em; margin-bottom: .4rem; }
    .intro p  { color: var(--text-secondary); font-size: .93rem; }
    .status-badge {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .22rem .7rem; border-radius: 100px; font-size: .74rem; font-weight: 500;
      margin-top: .85rem; background: var(--bg); border: 1px solid var(--border);
    }
    .status-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--text-secondary); }
    .status-badge.allowed .dot { background: var(--success); box-shadow: 0 0 8px var(--success); }
    .status-badge.denied  .dot { background: var(--danger); }

    /* ── Form ────────────────────────────────── */
    .feedback-form { display: flex; flex-direction: column; gap: 1.25rem; }
    .form-row { display: flex; gap: 1rem; }
    .form-row .form-group { flex: 1; }
    .form-group label { display: block; font-size: .875rem; font-weight: 500; margin-bottom: .45rem; color: var(--text-primary); }
    textarea {
      width: 100%; padding: .75rem; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg); color: var(--text-primary);
      font-family: inherit; font-size: .95rem; transition: all .2s; outline: none;
      resize: vertical; min-height: 130px;
    }
    textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    textarea:disabled { opacity: .5; cursor: not-allowed; }

    /* Select2 theme override */
    .select2-container--default .select2-selection--single {
      border-color: var(--border); background: var(--bg); border-radius: var(--radius-sm);
      height: 42px; display: flex; align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--text-primary); line-height: 42px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
    .select2-container--default .select2-results__option--highlighted { background: var(--accent) !important; }
    .select2-dropdown { border-color: var(--border); background: var(--surface); }
    .select2-search__field { background: var(--bg) !important; color: var(--text-primary) !important; border-color: var(--border) !important; }
    .select2-results__option { color: var(--text-primary); }

    /* Submit button */
    .submit-btn {
      width: 100%; padding: .95rem; border-radius: var(--radius-sm); border: none;
      background: linear-gradient(135deg, var(--accent), hsl(250,80%,60%));
      color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer;
      transition: all .2s; position: relative; overflow: hidden;
    }
    .submit-btn:hover:not(:disabled) { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 4px 14px var(--accent-glow); }
    .submit-btn:disabled { opacity: .7; cursor: not-allowed; transform: none; }
    .spinner {
      border: 3px solid rgba(255,255,255,.3); border-radius: 50%;
      border-top-color: #fff; width: 20px; height: 20px;
      animation: spin 1s linear infinite; margin: 0 auto;
    }
    .hidden { display: none !important; }

    /* ── Success screen ──────────────────────── */
    .success-screen { text-align: center; padding: 2rem 0; animation: fadeIn .5s ease; }
    .checkmark-circle {
      width: 80px; height: 80px; border-radius: 50%;
      background: linear-gradient(135deg, #00c853, #69f0ae);
      display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;
      box-shadow: 0 4px 20px rgba(0,200,83,.3);
    }
    .checkmark-circle svg { width: 40px; height: 40px; stroke: #fff; fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
    .success-screen h3 { font-size: 1.5rem; font-weight: 600; margin-bottom: .5rem; }
    .success-screen p  { color: var(--text-secondary); font-size: .93rem; }
    .cooldown-notice   { margin-top: .75rem; font-size: .82rem; color: var(--text-secondary); padding: .45rem .9rem; background: var(--border); border-radius: var(--radius-sm); display: inline-block; }

    /* ── Footer ──────────────────────────────── */
    footer { padding: 1.75rem 2rem; text-align: center; border-top: 1px solid var(--border); margin-top: auto; }
    footer p { font-size: .78rem; color: var(--text-secondary); }

    /* ── Animations ─────────────────────────── */
    @keyframes spin    { to { transform: rotate(360deg); } }
    @keyframes fadeIn  { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 600px) {
      .app-container { box-shadow: none; }
      main { padding: 1.25rem; }
      .form-row { flex-direction: column; }
      .header-content { padding: 1rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="app-container">

  <!-- ── Hero Header ─────────────────────── -->
  <header class="hero-header">
    <div class="hero-image-wrap">
      <img src="assets/ia-clay.jpeg" alt="Ihsanul Amal">
    </div>
    <div class="header-content">
      <div class="brand">
        <span class="logo-icon">💬</span>
        <div class="brand-text">
          <h1>AMANAH Digital</h1>
          <p class="subtitle">(Aduan, Masukan, Aspirasi, Nasihat orang tua Anonim dan Humanis)</p>
        </div>
      </div>
      <button id="theme-toggle" class="icon-btn" aria-label="Toggle Dark Mode">
        <svg class="sun-icon"  xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </header>

  <main>
    <!-- ── Intro ──────────────────────────── -->
    <section class="intro" id="intro-section">
      <h2>Sistem Aduan &amp; Aspirasi Orang Tua</h2>
      <p>Identitas Anda terjaga. Masukan Anda sangat berharga untuk kemajuan lembaga kami.</p>
      <div class="status-badge" id="eligibility-badge">
        <span class="dot"></span>
        <span id="status-text">Memeriksa kelayakan...</span>
      </div>
    </section>

    <!-- ── Feedback Form ──────────────────── -->
    <form id="feedback-form" class="feedback-form">

      <div class="form-row">
        <div class="form-group">
          <label for="topic-select">Topik <span style="color:#dc2626">*</span></label>
          <select id="topic-select" required style="width:100%">
            <option value="">Pilih atau ketik topik...</option>
          </select>
        </div>
        <div class="form-group">
          <label for="subtopic-select">Sub-Topik <span style="color:#dc2626">*</span></label>
          <select id="subtopic-select" required style="width:100%">
            <option value="">Pilih topik dulu...</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="feedback-content">Pesan Anda <span style="color:#dc2626">*</span></label>
        <textarea id="feedback-content" rows="6"
          placeholder="Tuliskan aduan, saran, atau aspirasi Anda di sini..." required disabled></textarea>
      </div>

      <button type="submit" class="submit-btn" id="submit-btn">
        <span class="btn-text">Kirim Masukan</span>
        <div class="spinner hidden"></div>
      </button>
    </form>

    <!-- ── Success Screen ─────────────────── -->
    <div id="success-screen" class="success-screen hidden">
      <div class="checkmark-circle">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h3>Terima Kasih!</h3>
      <p>Masukan Anda telah kami terima secara anonim.</p>
      <p class="cooldown-notice">Anda dapat mengirimkan masukan lagi bulan depan.</p>
    </div>
  </main>

  <footer>
    <p>&copy; <?= date('Y') ?> Yayasan Ihsanul Amal Alabio.</p>
  </footer>

</div><!-- /.app-container -->

<!-- ── Scripts ──────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
  'use strict';

  /* ── Theme ─────────────────────────────── */
  const root        = document.documentElement;
  const themeToggle = document.getElementById('theme-toggle');

  function initTheme() {
    const saved     = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.setAttribute('data-theme', (saved === 'dark' || (!saved && prefersDark)) ? 'dark' : 'light');
  }
  themeToggle.addEventListener('click', () => {
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });
  initTheme();

  /* ── DOM refs ───────────────────────────── */
  const form            = document.getElementById('feedback-form');
  const submitBtn       = document.getElementById('submit-btn');
  const btnText         = submitBtn.querySelector('.btn-text');
  const spinner         = submitBtn.querySelector('.spinner');
  const statusBadge     = document.getElementById('eligibility-badge');
  const statusText      = document.getElementById('status-text');
  const feedbackContent = document.getElementById('feedback-content');
  const successScreen   = document.getElementById('success-screen');
  const introSection    = document.getElementById('intro-section');

  let tags = [];  // [{id, name, sub_tags:[{id,name},...]}]

  /* ── Eligibility (cookie + server) ─────── */
  function setBlocked(reason) {
    statusBadge.classList.add('denied');
    statusText.textContent = reason;
    submitBtn.disabled = true;
    btnText.textContent = 'Batas 1× / Bulan';
  }

  function setAllowed() {
    statusBadge.classList.add('allowed');
    statusText.textContent = 'Anda dapat mengirim masukan.';
  }

  async function checkEligibility() {
    // Fast cookie check
    if (document.cookie.split(';').some(c => c.trim().startsWith('anon_feedback_cooldown='))) {
      setBlocked('Anda sudah mengirim masukan bulan ini.');
      return false;
    }
    // localStorage fallback
    const last = localStorage.getItem('last_submission');
    if (last && Date.now() - parseInt(last, 10) < 2592000000) {
      setBlocked('Anda sudah mengirim masukan bulan ini.');
      return false;
    }
    // Server check
    try {
      const res  = await fetch('api/check-eligibility.php');
      const data = await res.json();
      if (data.blocked) {
        setBlocked(data.reason ?? 'Anda sudah mengirim masukan bulan ini.');
        return false;
      }
    } catch (_) { /* allow on network error */ }
    setAllowed();
    return true;
  }

  /* ── Load & build selects ───────────────── */
  async function loadTags() {
    try {
      const res = await fetch('api/get-tags.php');
      if (!res.ok) throw new Error();
      tags = await res.json();
      buildTopicSelect();
    } catch (_) {
      console.warn('Gagal memuat topik dari server.');
    }
  }

  function buildTopicSelect() {
    const $topic = $('#topic-select');
    $topic.empty().append('<option value="">Pilih atau ketik topik baru...</option>');
    tags.forEach(t => $topic.append(new Option(t.name, t.id)));

    $topic.select2({
      placeholder: 'Pilih atau ketik topik baru...',
      // tags: true,            // allow custom / new value
      // allowClear: true,
    });

    $topic.on('change', function () {
      buildSubtopicSelect($(this).val());
      updateFormState();
    });
  }

  function buildSubtopicSelect(tagVal) {
    const $sub = $('#subtopic-select');
    $sub.empty().append('<option value="">Pilih atau ketik sub-topik baru...</option>');

    if ($sub.data('select2')) $sub.select2('destroy');

    const tag = tags.find(t => String(t.id) === String(tagVal));
    if (tag && tag.sub_tags.length) {
      tag.sub_tags.forEach(s => $sub.append(new Option(s.name, s.id)));
    }

    $sub.select2({
      placeholder: 'Pilih atau ketik sub-topik baru...',
      tags: true,
      // allowClear: true,
      disabled: !tagVal,
    });

    if (!tagVal) {
      $sub.prop('disabled', true).trigger('change.select2');
    } else {
      $sub.prop('disabled', false);
    }

    $sub.on('change', updateFormState);
  }

  function updateFormState() {
    const hasTopic    = !!$('#topic-select').val();
    const hasSubtopic = !!$('#subtopic-select').val();
    feedbackContent.disabled = !(hasTopic && hasSubtopic);
  }

  /* ── Submit ─────────────────────────────── */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!(await checkEligibility())) return;

    const content  = feedbackContent.value.trim();
    const tagVal   = $('#topic-select').val()    ?? '';
    const subVal   = $('#subtopic-select').val() ?? '';

    if (!content) {
      Swal.fire({ icon: 'warning', title: 'Pesan kosong', text: 'Tuliskan pesan Anda terlebih dahulu.', confirmButtonColor: '#3085d6' });
      return;
    }

    // Loading state
    submitBtn.disabled = true;
    btnText.classList.add('hidden');
    spinner.classList.remove('hidden');

    try {
      const resp = await fetch('api/submit-feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content, tag_id: tagVal, sub_tag_id: subVal }),
      });

      const result = await resp.json();

      if (resp.ok) {
        localStorage.setItem('last_submission', String(Date.now()));
        form.classList.add('hidden');
        introSection.classList.add('hidden');
        successScreen.classList.remove('hidden');
        window.scrollTo(0, 0);
      } else {
        Swal.fire({ icon: 'error', title: 'Gagal Mengirim', text: result.message ?? 'Terjadi kesalahan.', confirmButtonColor: '#3085d6' });
        if (resp.status === 429) {
          localStorage.setItem('last_submission', String(Date.now()));
          setBlocked(result.message ?? 'Anda sudah mengirim masukan bulan ini.');
        }
        submitBtn.disabled = false;
        btnText.classList.remove('hidden');
        spinner.classList.add('hidden');
      }
    } catch (_) {
      Swal.fire({ icon: 'error', title: 'Kesalahan Jaringan', text: 'Gagal terhubung ke server.', confirmButtonColor: '#3085d6' });
      submitBtn.disabled = false;
      btnText.classList.remove('hidden');
      spinner.classList.add('hidden');
    }
  });

  /* ── Init ───────────────────────────────── */
  checkEligibility();
  loadTags();
  // Initialise vacant subtopic select with Select2
  $('#subtopic-select').select2({ placeholder: 'Pilih topik dulu...', disabled: true });

})();
</script>
</body>
</html>
