/**
 * KARAN OLI PORTFOLIO — main.js
 * Optimized for real-time PHP backend integration
 */

document.addEventListener('DOMContentLoaded', () => {

  /* ═══════════════════════════════════
     1. THEME TOGGLE (dark / light)
  ═══════════════════════════════════ */
  const themeBtn = document.getElementById('theme-btn');
  const htmlEl = document.documentElement;
  const themeIcon = document.getElementById('theme-icon');

  const savedTheme = localStorage.getItem('ko-theme') || 'dark';
  applyTheme(savedTheme);

  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem('ko-theme', next);
    });
  }

  function applyTheme(theme) {
    htmlEl.setAttribute('data-theme', theme);
    document.body.setAttribute('data-theme', theme);
    if (themeIcon) {
      themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }
    const mobileThemeIcon = document.getElementById('mobile-theme-icon');
    if (mobileThemeIcon) {
      mobileThemeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
    }
  }

  /* ═══════════════════════════════════
     2. VIEW MODE TOGGLE
  ═══════════════════════════════════ */
  const viewToggle = document.getElementById('view-toggle');
  const viewLabel = document.getElementById('view-label');
  const qsBanner = document.getElementById('quickscan-banner');
  let isQuick = false;

  if (viewToggle) {
    viewToggle.addEventListener('click', () => {
      toggleViewMode();
    });
  }

  function toggleViewMode() {
    isQuick = !isQuick;
    document.body.classList.toggle('quickscan-mode', isQuick);
    if (qsBanner) qsBanner.classList.toggle('active', isQuick);
    viewToggle?.classList.toggle('active', isQuick);
    if (viewLabel) viewLabel.textContent = isQuick ? 'Deep Dive' : 'Quick Scan';
    
    const mobileViewToggle = document.getElementById('mobile-view-toggle');
    const mobileViewLabel = document.getElementById('mobile-view-label');
    if (mobileViewToggle) mobileViewToggle.classList.toggle('active', isQuick);
    if (mobileViewLabel) mobileViewLabel.textContent = isQuick ? 'Deep Dive' : 'Quick Scan';
  }

  /* ═══════════════════════════════════
     3. MOBILE ACTIONS
  ═══════════════════════════════════ */
  const mobileThemeBtn = document.getElementById('mobile-theme-btn');
  if (mobileThemeBtn) {
    mobileThemeBtn.addEventListener('click', () => {
      const next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem('ko-theme', next);
    });
  }

  const mobileViewToggle = document.getElementById('mobile-view-toggle');
  if (mobileViewToggle) {
    mobileViewToggle.addEventListener('click', toggleViewMode);
  }

  /* ═══════════════════════════════════
     4. STICKY NAV
  ═══════════════════════════════════ */
  const navHeader = document.getElementById('nav-header');
  window.addEventListener('scroll', () => {
    if (navHeader) {
      navHeader.classList.toggle('scrolled', window.scrollY > 40);
    }
  }, { passive: true });

  /* ═══════════════════════════════════
     5. MOBILE HAMBURGER
  ═══════════════════════════════════ */
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('active');
    });

    mobileMenu.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        hamburger.classList.remove('active');
      });
    });
  }

  /* ═══════════════════════════════════
     6. TYPEWRITER EFFECT
  ═══════════════════════════════════ */
  const roles = [
    'Full-Stack Developer',
    'PHP & MySQL Engineer',
    'JavaScript Enthusiast',
    'BCA Graduate',
    'Open to Work',
  ];
  const typedEl = document.getElementById('typed-text');
  let roleIdx = 0;
  let charIdx = 0;
  let deleting = false;

  if (typedEl) {
    function type() {
      const current = roles[roleIdx];
      if (!deleting) {
        typedEl.textContent = current.substring(0, charIdx + 1);
        charIdx++;
        if (charIdx === current.length) {
          deleting = true;
          setTimeout(type, 1800);
          return;
        }
      } else {
        typedEl.textContent = current.substring(0, charIdx - 1);
        charIdx--;
        if (charIdx === 0) {
          deleting = false;
          roleIdx = (roleIdx + 1) % roles.length;
        }
      }
      setTimeout(type, deleting ? 60 : 100);
    }
    type();
  }

  /* ═══════════════════════════════════
     7. SKILL BARS
  ═══════════════════════════════════ */
  const fills = document.querySelectorAll('.skill-fill');
  if (fills.length) {
    const skillObs = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const target = entry.target;
          const level = target.dataset.level;
          if (level) {
            target.style.width = level + '%';
          }
          skillObs.unobserve(target);
        }
      });
    }, { threshold: 0.4 });

    fills.forEach(fill => skillObs.observe(fill));
  }

  /* ═══════════════════════════════════
     8. PROJECT FILTER
  ═══════════════════════════════════ */
  const filterBtns = document.querySelectorAll('.filter-btn');
  const projectCards = document.querySelectorAll('.project-card');

  if (filterBtns.length && projectCards.length) {
    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const tag = btn.dataset.tag;
        projectCards.forEach(card => {
          const matches = tag === 'all' || card.dataset.tags.includes(tag);
          card.style.display = matches ? '' : 'none';
        });
      });
    });

    document.querySelectorAll('.proj-discuss').forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const card = link.closest('.project-card');
        const title = card?.querySelector('.project-title')?.textContent || 'Project';
        const subjectField = document.getElementById('contact-subject');
        if (subjectField) subjectField.value = `Discussing your project: ${title}`;
        document.getElementById('contact')?.scrollIntoView({ behavior: 'smooth' });
      });
    });
  }

  /* ═══════════════════════════════════
     9. TERMINAL WIDGET
  ═══════════════════════════════════ */
  const termInput = document.getElementById('terminal-input');
  const termOutput = document.getElementById('terminal-output');

  const COMMANDS = {
    help: () => [
      { text: 'Available commands:', type: 'response' },
      { text: '  whoami      → who is Karan?', type: 'response' },
      { text: '  skills      → tech stack details', type: 'response' },
      { text: '  projects    → list of projects', type: 'response' },
      { text: '  contact     → get contact info', type: 'response' },
      { text: '  hire        → why hire Karan?', type: 'response' },
      { text: '  education   → academic background', type: 'response' },
      { text: '  github      → GitHub profile link', type: 'response' },
      { text: '  clear       → clear terminal', type: 'response' },
    ],
    whoami: () => [
      { text: 'Karan Oli', type: 'response success' },
      { text: 'BCA graduate from Nepal. Full-stack developer.', type: 'response' },
      { text: 'Builds web apps with HTML, CSS, JS, PHP & MySQL.', type: 'response' },
    ],
    skills: () => [
      { text: '── Tech Stack ────────────────────────', type: 'response muted' },
      { text: '  PHP, MySQL, JavaScript, HTML5, CSS3', type: 'response' },
    ],
    projects: () => [
      { text: 'E-Commerce, Result System, Weather Dashboard, etc.', type: 'response' },
    ],
    contact: () => [
      { text: 'Email: chhetrikaran.147@gmail.com', type: 'response' },
    ],
    hire: () => [
      { text: '✓ Secure PHP/PDO, Clean Code, Fast Learner.', type: 'response success' },
    ],
    education: () => [
      { text: 'Bachelor of Computer Applications - TU, Nepal', type: 'response' },
    ],
    github: () => [
      { text: 'https://github.com/NotMeFound', type: 'response' },
    ],
    clear: () => '__clear__',
  };

  function addLine(content, classes = '') {
    const p = document.createElement('p');
    p.className = 't-response ' + classes;
    p.textContent = content;
    if (termOutput) {
      termOutput.appendChild(p);
      termOutput.scrollTop = termOutput.scrollHeight;
    }
  }

  function addCommandLine(cmd) {
    const p = document.createElement('p');
    p.className = 't-line';
    p.innerHTML = `<span class="t-prompt">$</span> <span class="t-cmd">${escHtml(cmd)}</span>`;
    if (termOutput) termOutput.appendChild(p);
  }

  function escHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  if (termInput && termOutput) {
    termInput.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      const raw = termInput.value.trim().toLowerCase();
      termInput.value = '';
      if (!raw) return;

      addCommandLine(raw);
      if (COMMANDS[raw]) {
        const result = COMMANDS[raw]();
        if (result === '__clear__') {
          termOutput.innerHTML = '';
          return;
        }
        result.forEach(item => addLine(item.text, item.type));
        if (raw === 'github') window.open('https://github.com/NotMeFound', '_blank');
      } else {
        addLine(`Command not found: ${raw}`, 'error');
      }
    });
  }

  /* ═══════════════════════════════════
     10. CONTACT FORM - REAL-TIME PHP VERSION
  ═══════════════════════════════════ */
  const form = document.getElementById('contact-form');
  const statusEl = document.getElementById('form-status');
  const submitBtn = document.getElementById('submit-btn');
  const btnText = submitBtn?.querySelector('.btn-text');
  const btnLoading = submitBtn?.querySelector('.btn-loading');

  if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      // UI Reset
      hideStatus();
      const inputs = form.querySelectorAll('input, textarea');
      inputs.forEach(i => i.classList.remove('error'));

      // Basic Validation
      const formData = new FormData(this);
      let hasError = false;

      if (formData.get('name').length < 2) { showFieldError('name'); hasError = true; }
      if (!formData.get('email').includes('@')) { showFieldError('email'); hasError = true; }
      if (formData.get('message').length < 10) { showFieldError('message'); hasError = true; }

      if (hasError) return;

      // Loading State
      if (submitBtn) submitBtn.disabled = true;
      if (btnText) btnText.style.display = 'none';
      if (btnLoading) btnLoading.style.display = 'inline-block';

      try {
        const response = await fetch('php/send-message.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showStatus(result.message, 'success');
          form.reset();
        } else {
          showStatus(result.error || 'Something went wrong!', 'error');
        }
      } catch (error) {
        showStatus('Network error. Please try again later!', 'error');
      } finally {
        // Restore State
        if (submitBtn) submitBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoading) btnLoading.style.display = 'none';
      }
    });
  }

  function showFieldError(name) {
    const field = document.getElementsByName(name)[0];
    if (field) field.classList.add('error');
  }

  function showStatus(msg, type) {
    if (statusEl) {
      statusEl.textContent = msg;
      statusEl.className = 'form-status ' + type;
      statusEl.style.display = 'block';
    }
  }

  function hideStatus() {
    if (statusEl) statusEl.style.display = 'none';
  }

  /* ═══════════════════════════════════
     11. VISITOR COUNTER
  ═══════════════════════════════════ */
  const counterEl = document.getElementById('visitor-count');

  async function loadVisitorCount() {
    try {
      const res = await fetch('php/visitor.php');
      const json = await res.json();
      if (json.visitors) animateCounter(counterEl, 0, json.visitors, 1500);
    } catch {
      if (counterEl) counterEl.textContent = '1,247';
    }
  }

  function animateCounter(el, from, to, duration) {
    if (!el) return;
    const start = performance.now();
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const val = Math.round(from + (to - from) * (1 - Math.pow(1 - progress, 3)));
      el.textContent = val.toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  loadVisitorCount();

  /* ═══════════════════════════════════
     12. SMOOTH SCROLL
  ═══════════════════════════════════ */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});