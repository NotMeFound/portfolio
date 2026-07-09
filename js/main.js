/**
 * KARAN OLI PORTFOLIO — main.js
 * Features:
 *  - Dark/light theme toggle (persisted to localStorage)
 *  - Recruiter "Quick Scan" / Engineer "Deep Dive" mode toggle
 *  - Typewriter hero text effect
 *  - Skill bars animated via IntersectionObserver
 *  - Project tag filter
 *  - Interactive terminal widget
 *  - AJAX contact form submission
 *  - Live visitor counter fetch
 *  - Sticky nav with scroll class
 *  - Mobile hamburger menu
 *  - Smooth scroll for anchor links
 */

document.addEventListener('DOMContentLoaded', () => {

  /* ═══════════════════════════════════
     1. THEME TOGGLE (dark / light)
  ═══════════════════════════════════ */
  const themeBtn   = document.getElementById('theme-btn');
  const htmlEl     = document.documentElement;

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
  }


  /* ═══════════════════════════════════
     2. VIEW MODE TOGGLE (recruiter / engineer)
  ═══════════════════════════════════ */
  const viewToggle = document.getElementById('view-toggle');
  const viewLabel  = document.getElementById('view-label');
  const qsBanner   = document.getElementById('quickscan-banner');
  let   isQuick    = false;

  viewToggle.addEventListener('click', () => {
    isQuick = !isQuick;
    document.body.classList.toggle('quickscan-mode', isQuick);
    qsBanner.classList.toggle('active', isQuick);
    viewToggle.classList.toggle('active', isQuick);
    viewLabel.textContent = isQuick ? 'Deep Dive' : 'Quick Scan';
  });


  /* ═══════════════════════════════════
     3. STICKY NAV ON SCROLL
  ═══════════════════════════════════ */
  const navHeader = document.getElementById('nav-header');
  window.addEventListener('scroll', () => {
    navHeader.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });


  /* ═══════════════════════════════════
     4. MOBILE HAMBURGER
  ═══════════════════════════════════ */
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');

  hamburger.addEventListener('click', () => {
    mobileMenu.classList.toggle('open');
  });

  // Close on any mobile link tap
  mobileMenu.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => mobileMenu.classList.remove('open'));
  });


  /* ═══════════════════════════════════
     5. TYPEWRITER EFFECT (hero)
  ═══════════════════════════════════ */
  const roles = [
    'Full-Stack Developer',
    'PHP & MySQL Engineer',
    'JavaScript Enthusiast',
    'BCA Graduate',
    'Open to Work',
  ];
  const typedEl  = document.getElementById('typed-text');
  let roleIdx    = 0;
  let charIdx    = 0;
  let deleting   = false;

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
        deleting  = false;
        roleIdx   = (roleIdx + 1) % roles.length;
      }
    }
    setTimeout(type, deleting ? 60 : 100);
  }
  type();


  /* ═══════════════════════════════════
     6. SKILL BARS (IntersectionObserver)
  ═══════════════════════════════════ */
  const fills = document.querySelectorAll('.skill-fill');
  const skillObs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.width = entry.target.dataset.level + '%';
        skillObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.4 });

  fills.forEach(fill => skillObs.observe(fill));


  /* ═══════════════════════════════════
     7. PROJECT TAG FILTER
  ═══════════════════════════════════ */
  const filterBtns = document.querySelectorAll('.filter-btn');
  const projectCards = document.querySelectorAll('.project-card');

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

  // Pre-fill contact subject when "Discuss this project →" is clicked
  document.querySelectorAll('.proj-discuss').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const title   = link.closest('.project-card').querySelector('.project-title').textContent;
      const subject = document.getElementById('subject');
      if (subject) subject.value = `Discussing your project: ${title}`;
      document.getElementById('contact').scrollIntoView({ behavior: 'smooth' });
    });
  });


  /* ═══════════════════════════════════
     8. TERMINAL WIDGET
  ═══════════════════════════════════ */
  const termInput  = document.getElementById('terminal-input');
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
      { text: 'LinkedIn: https://www.linkedin.com/in/karan-chhetri-919b803b7', type: 'response' },
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
    termOutput.appendChild(p);
    termOutput.scrollTop = termOutput.scrollHeight;
  }

  function addCommandLine(cmd) {
    const p = document.createElement('p');
    p.className = 't-line';
    p.innerHTML = `<span class="t-prompt">$</span> <span class="t-cmd">${escHtml(cmd)}</span>`;
    termOutput.appendChild(p);
  }

  function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

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
      result.forEach(item => addLine(item.text, item.type.replace('response', '').trim()));
      if (raw === 'github') {
        window.open('https://github.com/karanoli', '_blank', 'noopener,noreferrer');
      }
    } else {
      addLine(`Command not found: ${raw}`, 'error');
      addLine('Type help to see available commands.', 'muted');
    }
  });


  /* ═══════════════════════════════════
  /* ═══════════════════════════════════
     9. CONTACT FORM - Production Ready
  ═══════════════════════════════════ */
  const form = document.getElementById('contact-form');
  const statusEl = document.getElementById('form-status');
  const submitBtn = document.getElementById('submit-btn');
  const btnText = submitBtn.querySelector('.btn-text');
  const btnLoading = submitBtn.querySelector('.btn-loading');

  // ── Field validation helpers ──────────────────────────────────
  function validateField(field) {
    const value = field.value.trim();
    const errorEl = document.querySelector(`[data-field="${field.name}"]`);
    let isValid = true;
    let errorMsg = '';

    // Reset error state
    field.classList.remove('error');
    if (errorEl) errorEl.textContent = '';

    switch (field.name) {
      case 'name':
        if (!value) {
          errorMsg = 'Name is required.';
          isValid = false;
        } else if (value.length < 2) {
          errorMsg = 'Name must be at least 2 characters.';
          isValid = false;
        } else if (value.length > 100) {
          errorMsg = 'Name cannot exceed 100 characters.';
          isValid = false;
        }
        break;

      case 'email':
        if (!value) {
          errorMsg = 'Email is required.';
          isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          errorMsg = 'Please enter a valid email address.';
          isValid = false;
        } else if (value.length > 100) {
          errorMsg = 'Email cannot exceed 100 characters.';
          isValid = false;
        }
        break;

      case 'subject':
        if (!value) {
          errorMsg = 'Subject is required.';
          isValid = false;
        } else if (value.length < 2) {
          errorMsg = 'Subject must be at least 2 characters.';
          isValid = false;
        } else if (value.length > 200) {
          errorMsg = 'Subject cannot exceed 200 characters.';
          isValid = false;
        }
        break;

      case 'message':
        if (!value) {
          errorMsg = 'Message is required.';
          isValid = false;
        } else if (value.length < 10) {
          errorMsg = 'Message must be at least 10 characters.';
          isValid = false;
        } else if (value.length > 5000) {
          errorMsg = 'Message cannot exceed 5000 characters.';
          isValid = false;
        }
        break;
    }

    if (!isValid && errorEl) {
      field.classList.add('error');
      errorEl.textContent = errorMsg;
    }

    return isValid;
  }

  // ── Real-time validation on blur ─────────────────────────────
  form.querySelectorAll('input, textarea').forEach(field => {
    field.addEventListener('blur', () => validateField(field));
    field.addEventListener('input', () => {
      // Clear error on input if valid
      if (field.classList.contains('error') && validateField(field)) {
        field.classList.remove('error');
        const errorEl = document.querySelector(`[data-field="${field.name}"]`);
        if (errorEl) errorEl.textContent = '';
      }
    });
  });

  // ── Form submission ──────────────────────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // ── Validate all fields ────────────────────────────────────
    let allValid = true;
    const fields = form.querySelectorAll('input, textarea');
    fields.forEach(field => {
      if (!validateField(field)) {
        allValid = false;
        field.focus();
      }
    });

    if (!allValid) {
      showStatus('Please fix all errors before submitting.', 'error');
      return;
    }

    // ── Get form data ──────────────────────────────────────────
    const formData = new FormData(form);
    const data = {
      name: formData.get('name').trim(),
      email: formData.get('email').trim(),
      subject: formData.get('subject').trim(),
      message: formData.get('message').trim()
    };

    // ── Show loading state ─────────────────────────────────────
    submitBtn.disabled = true;
    btnText.hidden = true;
    btnLoading.hidden = false;
    hideStatus();

    try {
      // Determine which backend to use
      const useWeb3Forms = false; // Set to true for Web3Forms, false for PHP
      let endpoint;
      let body;

      if (useWeb3Forms) {
        // ── Option 1: Web3Forms (No backend required) ──────────
        endpoint = 'https://api.web3forms.com/submit';
        body = new FormData();
        body.append('access_key', 'YOUR_WEB3FORMS_ACCESS_KEY'); // Get from web3forms.com
        body.append('name', data.name);
        body.append('email', data.email);
        body.append('subject', data.subject);
        body.append('message', data.message);
      } else {
        // ── Option 2: PHP Backend ──────────────────────────────
        endpoint = 'php/send-message.php';
        body = formData; // Use FormData for file uploads if needed
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        body: body
      });

      const result = await response.json();

      if (response.ok && result.success) {
        showStatus(result.message || 'Message sent successfully! 🎉', 'success');
        form.reset();
        // Reset project pre-filled subject
        const subjectField = document.getElementById('contact-subject');
        if (subjectField) subjectField.value = '';
      } else {
        // Handle specific error responses
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
      showStatus('Network error. Please check your connection and try again.', 'error');
    } finally {
      submitBtn.disabled = false;
      btnText.hidden = false;
      btnLoading.hidden = true;
    }
  });

  function showStatus(msg, type) {
    statusEl.textContent = msg;
    statusEl.className = 'form-status ' + type;
    statusEl.hidden = false;
  }

  function hideStatus() {
    statusEl.hidden = true;
    statusEl.className = 'form-status';
  }


  /* ═══════════════════════════════════
     10. VISITOR COUNTER
  ═══════════════════════════════════ */
  const counterEl = document.getElementById('visitor-count');

  async function loadVisitorCount() {
    try {
      const res  = await fetch('php/visitor.php');
      const json = await res.json();
      if (json.visitors !== undefined) {
        animateCounter(counterEl, 0, json.visitors, 1000);
      }
    } catch {
      // Static fallback if PHP not available
      animateCounter(counterEl, 0, 247, 1000);
    }
  }

  function animateCounter(el, from, to, duration) {
    const start = performance.now();
    function step(now) {
      const progress = Math.min((now - start) / duration, 1);
      const value    = Math.round(from + (to - from) * easeOut(progress));
      el.textContent = value.toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

  loadVisitorCount();


  /* ═══════════════════════════════════
     11. SMOOTH SCROLL FOR ALL ANCHOR LINKS
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
