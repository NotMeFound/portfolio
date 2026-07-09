/**
 * KARAN OLI PORTFOLIO — main.js
 * Complete production-ready JavaScript
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

  themeBtn.addEventListener('click', () => {
    const next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem('ko-theme', next);
  });

  function applyTheme(theme) {
    htmlEl.setAttribute('data-theme', theme);
    document.body.setAttribute('data-theme', theme);
    if (themeIcon) {
      themeIcon.textContent = theme === 'dark' ? '🌙' : '☀️';
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
      isQuick = !isQuick;
      document.body.classList.toggle('quickscan-mode', isQuick);
      if (qsBanner) qsBanner.classList.toggle('active', isQuick);
      viewToggle.classList.toggle('active', isQuick);
      if (viewLabel) viewLabel.textContent = isQuick ? 'Deep Dive' : 'Quick Scan';
    });
  }

  /* ═══════════════════════════════════
     3. STICKY NAV
  ═══════════════════════════════════ */
  const navHeader = document.getElementById('nav-header');
  window.addEventListener('scroll', () => {
    if (navHeader) {
      navHeader.classList.toggle('scrolled', window.scrollY > 40);
    }
  }, { passive: true });

  /* ═══════════════════════════════════
     4. MOBILE HAMBURGER
  ═══════════════════════════════════ */
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
    });

    mobileMenu.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => mobileMenu.classList.remove('open'));
    });
  }

  /* ═══════════════════════════════════
     5. TYPEWRITER EFFECT
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
     6. SKILL BARS
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
     7. PROJECT FILTER
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
        const subject = document.getElementById('contact-subject');
        if (subject) subject.value = `Discussing your project: ${title}`;
        document.getElementById('contact')?.scrollIntoView({ behavior: 'smooth' });
      });
    });
  }

  /* ═══════════════════════════════════
     8. TERMINAL WIDGET
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
      { text: 'Status: actively looking for junior dev roles.', type: 'response' },
    ],
    skills: () => [
      { text: '── Frontend ──────────────────────────', type: 'response muted' },
      { text: '  HTML5          ████████████████████ 90%', type: 'response' },
      { text: '  CSS3 / Grid    ████████████████████ 85%', type: 'response' },
      { text: '  JavaScript     █████████████████    78%', type: 'response' },
      { text: '── Backend ───────────────────────────', type: 'response muted' },
      { text: '  PHP 8          ████████████████     80%', type: 'response' },
      { text: '  MySQL / SQL    ████████████████     82%', type: 'response' },
      { text: '  PDO / Security ███████████████      75%', type: 'response' },
      { text: '── Tools ─────────────────────────────', type: 'response muted' },
      { text: '  Git, XAMPP, cPanel, VS Code', type: 'response' },
    ],
    projects: () => [
      { text: '[1] E-Commerce Web App      (PHP, SQL, JS)', type: 'response' },
      { text: '[2] Student Result System   (PHP, SQL)', type: 'response' },
      { text: '[3] Weather Dashboard       (JS, CSS)', type: 'response' },
      { text: '[4] Blog CMS                (PHP, SQL)', type: 'response' },
      { text: '[5] Task Manager            (JS, CSS)', type: 'response' },
      { text: '[6] Guestbook               (PHP, SQL, JS)', type: 'response' },
      { text: 'Scroll to #projects to see full details.', type: 'response muted' },
    ],
    contact: () => [
      { text: 'Email:    chhetrikaran.147@gmail.com', type: 'response' },
      { text: 'GitHub:   https://github.com/NotMeFound', type: 'response' },
      { text: 'https://www.linkedin.com/in/karan-chhetri-919b803b7', type: 'response' },
      { text: 'Location: Nepal — open to remote', type: 'response' },
    ],
    hire: () => [
      { text: '┌─ Why hire Karan? ───────────────────┐', type: 'response muted' },
      { text: '│ ✓ Understands full stack end-to-end  │', type: 'response success' },
      { text: '│ ✓ Writes secure PHP with PDO         │', type: 'response success' },
      { text: '│ ✓ Clean, readable code — no bloat    │', type: 'response success' },
      { text: '│ ✓ Fast learner, self-taught many      │', type: 'response success' },
      { text: '│ ✓ Open to feedback and code review   │', type: 'response success' },
      { text: '└─────────────────────────────────────┘', type: 'response muted' },
    ],
    education: () => [
      { text: 'Degree:  Bachelor of Computer Applications', type: 'response' },
      { text: 'School:  Tribhuvan University, Nepal', type: 'response' },
      { text: 'Year:    2020 – 2024', type: 'response' },
      { text: 'Focus:   Web development, databases, algorithms', type: 'response' },
    ],
    github: () => [
      { text: 'Opening GitHub profile...', type: 'response success' },
      { text: 'https://github.com/karanoli', type: 'response' },
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
    if (termOutput) {
      termOutput.appendChild(p);
    }
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
          addLine('Terminal cleared. Type help for commands.', 'muted');
          return;
        }
        result.forEach(item => {
          const type = item.type || 'response';
          addLine(item.text, type.replace('response', '').trim());
        });
        if (raw === 'github') {
          window.open('https://github.com/NotMeFound', '_blank', 'noopener,noreferrer');
        }
      } else {
        addLine(`Command not found: ${raw}`, 'error');
        addLine('Type help to see available commands.', 'muted');
      }
    });
  }

  /* ═══════════════════════════════════
     9. CONTACT FORM - Production Ready
  ═══════════════════════════════════ */
  const form = document.getElementById('contact-form');
  const statusEl = document.getElementById('form-status');
  const submitBtn = document.getElementById('submit-btn');
  const btnText = submitBtn?.querySelector('.btn-text');
  const btnLoading = submitBtn?.querySelector('.btn-loading');

  if (form) {
    // Real-time validation on input
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
      input.addEventListener('input', function() {
        if (this.value.trim().length > 0) {
          this.classList.remove('error');
          const errorEl = this.parentElement.querySelector('.field-error');
          if (errorEl) errorEl.textContent = '';
        }
      });
    });

    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      // Get form data
      const formData = new FormData(this);
      
      // Simple validation
      const name = formData.get('name')?.trim() || '';
      const email = formData.get('email')?.trim() || '';
      const subject = formData.get('subject')?.trim() || '';
      const message = formData.get('message')?.trim() || '';

      // Reset errors
      inputs.forEach(input => input.classList.remove('error'));
      
      let hasError = false;

      if (!name || name.length < 2) {
        showFieldError('name', 'Please enter your name (minimum 2 characters).');
        hasError = true;
      }

      if (!email || !email.includes('@') || !email.includes('.')) {
        showFieldError('email', 'Please enter a valid email address.');
        hasError = true;
      }

      if (!subject || subject.length < 2) {
        showFieldError('subject', 'Please enter a subject (minimum 2 characters).');
        hasError = true;
      }

      if (!message || message.length < 10) {
        showFieldError('message', 'Please enter a message (minimum 10 characters).');
        hasError = true;
      }

      if (hasError) {
        showStatus('Please fix all errors before submitting.', 'error');
        return;
      }

      // Show loading state
      if (submitBtn) submitBtn.disabled = true;
      if (btnText) btnText.hidden = true;
      if (btnLoading) btnLoading.hidden = false;
      
      hideStatus();

      try {
        // Use fetch with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        const response = await fetch('php/send-message.php', {
          method: 'POST',
          body: formData,
          signal: controller.signal
        });

        clearTimeout(timeoutId);

        const result = await response.json();

        if (response.ok && result.success) {
          showStatus(result.message || 'Message sent successfully! 🎉', 'success');
          this.reset();
          const subjectField = document.getElementById('contact-subject');
          if (subjectField) subjectField.value = '';
        } else {
          if (response.status === 429) {
            showStatus('Too many messages. Please wait an hour before sending again.', 'error');
          } else if (response.status === 422) {
            showStatus(result.error || 'Please check your input and try again.', 'error');
          } else {
            showStatus(result.error || 'Failed to send message. Please try again.', 'error');
          }
        }
      } catch (error) {
        console.error('Contact form error:', error);
        if (error.name === 'AbortError') {
          showStatus('Request timed out. Please check your connection and try again.', 'error');
        } else {
          showStatus('Network error. Please check your connection and try again.', 'error');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (btnText) btnText.hidden = false;
        if (btnLoading) btnLoading.hidden = true;
      }
    });
  }

  function showFieldError(fieldName, message) {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (field) {
      field.classList.add('error');
      const errorEl = field.parentElement.querySelector('.field-error');
      if (errorEl) errorEl.textContent = message;
    }
  }

  function showStatus(msg, type) {
    if (statusEl) {
      statusEl.textContent = msg;
      statusEl.className = 'form-status ' + type;
      statusEl.hidden = false;
      if (type === 'success') {
        setTimeout(() => {
          if (statusEl) statusEl.hidden = true;
        }, 5000);
      }
    }
  }

  function hideStatus() {
    if (statusEl) {
      statusEl.hidden = true;
      statusEl.className = 'form-status';
    }
  }

  /* ═══════════════════════════════════
     10. VISITOR COUNTER
  ═══════════════════════════════════ */
  const counterEl = document.getElementById('visitor-count');

  async function loadVisitorCount() {
    try {
      const res = await fetch('php/visitor.php');
      const json = await res.json();
      if (json.visitors !== undefined) {
        animateCounter(counterEl, 0, json.visitors, 1000);
      }
    } catch {
      animateCounter(counterEl, 0, 247, 1000);
    }
  }

  function animateCounter(el, from, to, duration) {
    if (!el) return;
    const start = performance.now();
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const value = Math.round(from + (to - from) * easeOut(progress));
      el.textContent = value.toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

  loadVisitorCount();

  /* ═══════════════════════════════════
     11. SMOOTH SCROLL
  ═══════════════════════════════════ */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});