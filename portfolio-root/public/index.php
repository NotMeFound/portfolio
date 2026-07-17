<?php
declare(strict_types=1);
require_once __DIR__ . '/../core/security.php';

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portfolio | BCA Graduate & Full-Stack Developer</title>
<style>
  :root {
    --bg: #0f1220;
    --bg-alt: #161a2e;
    --card: #1c2138;
    --text: #eef0f6;
    --muted: #9aa1b9;
    --accent: #5b8cff;
    --accent-soft: rgba(91,140,255,0.12);
    --border: #2a2f4a;
    --radius: 14px;
    --max-width: 1100px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.6;
  }
  a { color: inherit; text-decoration: none; }
  section { padding: 90px 24px; }
  .wrap { max-width: var(--max-width); margin: 0 auto; }

  /* Nav */
  header {
    position: sticky; top: 0; z-index: 100;
    background: rgba(15,18,32,0.85);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--border);
  }
  nav.wrap {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 24px;
  }
  .logo { font-weight: 700; font-size: 1.2rem; letter-spacing: 0.5px; }
  .nav-links { display: flex; gap: 28px; }
  .nav-links a { color: var(--muted); font-weight: 500; transition: color .2s; }
  .nav-links a:hover { color: var(--accent); }

  /* Hero */
  .hero {
    min-height: 80vh; display: flex; flex-direction: column;
    justify-content: center; align-items: flex-start;
  }
  .hero .eyebrow {
    color: var(--accent); font-weight: 600; letter-spacing: 1.5px;
    text-transform: uppercase; font-size: 0.85rem; margin-bottom: 16px;
  }
  .hero h1 { font-size: clamp(2.2rem, 5vw, 3.6rem); font-weight: 800; margin-bottom: 18px; }
  .hero p { color: var(--muted); font-size: 1.1rem; max-width: 560px; margin-bottom: 28px; }
  .btn {
    display: inline-block; background: var(--accent); color: #fff;
    padding: 13px 28px; border-radius: 999px; font-weight: 600;
    border: none; cursor: pointer; transition: transform .15s, opacity .2s;
  }
  .btn:hover { transform: translateY(-2px); }
  .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

  h2.section-title {
    font-size: 2rem; font-weight: 800; margin-bottom: 12px;
  }
  .section-sub { color: var(--muted); margin-bottom: 48px; max-width: 600px; }

  /* About */
  .about-grid {
    display: grid; grid-template-columns: 1fr 1.3fr; gap: 48px; align-items: center;
  }
  .about-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 32px; color: var(--muted);
  }

  /* Skills */
  .skills-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 18px;
  }
  .skill-block {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 24px 18px; text-align: center;
    font-weight: 600; transition: border-color .2s, transform .2s;
  }
  .skill-block:hover { border-color: var(--accent); transform: translateY(-4px); }

  /* Services */
  .services-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
  }
  .service-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 32px;
  }
  .service-card .icon {
    width: 46px; height: 46px; border-radius: 12px;
    background: var(--accent-soft); color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; margin-bottom: 18px; font-size: 1.2rem;
  }
  .service-card h3 { margin-bottom: 10px; font-size: 1.15rem; }
  .service-card p { color: var(--muted); font-size: 0.95rem; }

  /* Contact */
  .contact-grid {
    display: grid; grid-template-columns: 1fr 1.4fr; gap: 48px;
  }
  .contact-info p { color: var(--muted); margin-bottom: 10px; }
  form#contact-form {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 32px; display: flex; flex-direction: column; gap: 16px;
  }
  .field label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 6px; }
  .field input, .field textarea {
    width: 100%; padding: 12px 14px; border-radius: 8px;
    border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);
    font-family: inherit; font-size: 0.95rem;
  }
  .field input:focus, .field textarea:focus { outline: none; border-color: var(--accent); }
  #form-status { font-size: 0.9rem; min-height: 20px; }
  .status-success { color: #4ade80; }
  .status-error { color: #f87171; }

  footer {
    text-align: center; padding: 32px 24px; color: var(--muted);
    border-top: 1px solid var(--border); font-size: 0.85rem;
  }

  @media (max-width: 760px) {
    .nav-links { display: none; }
    .about-grid, .contact-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<header>
  <nav class="wrap">
    <div class="logo">Portfolio</div>
    <div class="nav-links">
      <a href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#skills">Skills</a>
      <a href="#services">Services</a>
      <a href="#contact">Contact</a>
    </div>
  </nav>
</header>

<section id="home" class="hero wrap">
  <div class="eyebrow">BCA Graduate &middot; Full-Stack Web Developer</div>
  <h1>Hi, I build clean, secure, and fast web applications.</h1>
  <p>I design and develop full-stack websites with a strong focus on backend security,
     clean architecture, and thoughtful user experience.</p>
  <a href="#contact" class="btn">Let's talk</a>
</section>

<section id="about">
  <div class="wrap">
    <h2 class="section-title">About Me</h2>
    <p class="section-sub">A quick summary of how I approach engineering work.</p>
    <div class="about-grid">
      <div class="about-card">
        <strong>Education</strong>
        <p style="margin-top:8px;">Bachelor of Computer Applications (BCA)</p>
      </div>
      <div class="about-card">
        I approach every project as an engineering problem first: understand the
        requirements, design a clean data model, and build the smallest secure
        piece that solves it. I care about writing code that is easy to reason
        about, defensively validated on every input boundary, and maintainable
        by the next developer — even if that developer is future me. I enjoy
        working across the stack, from schema design in MySQL to the small
        interaction details in the browser.
      </div>
    </div>
  </div>
</section>

<section id="skills">
  <div class="wrap">
    <h2 class="section-title">Skills</h2>
    <p class="section-sub">Core technologies I work with day to day.</p>
    <div class="skills-grid">
      <div class="skill-block">PHP</div>
      <div class="skill-block">MySQL</div>
      <div class="skill-block">JavaScript</div>
      <div class="skill-block">HTML5 &amp; CSS3</div>
      <div class="skill-block">PDO / SQL Security</div>
      <div class="skill-block">REST APIs</div>
    </div>
  </div>
</section>

<section id="services">
  <div class="wrap">
    <h2 class="section-title">Services</h2>
    <p class="section-sub">What I can help you build.</p>
    <div class="services-grid">
      <div class="service-card">
        <div class="icon">UI</div>
        <h3>Web Design</h3>
        <p>Clean, responsive interfaces that load fast and feel intentional on every device.</p>
      </div>
      <div class="service-card">
        <div class="icon">FS</div>
        <h3>Full-Stack Development</h3>
        <p>End-to-end builds covering database design, backend logic, and the front-end that ties it together.</p>
      </div>
      <div class="service-card">
        <div class="icon">API</div>
        <h3>Secure API Integrations</h3>
        <p>Authenticated, validated integrations that follow least-privilege and defense-in-depth principles.</p>
      </div>
    </div>
  </div>
</section>

<section id="contact">
  <div class="wrap">
    <h2 class="section-title">Contact</h2>
    <p class="section-sub">Have a project in mind? Send a message below.</p>
    <div class="contact-grid">
      <div class="contact-info">
        <p>I usually reply within a day or two.</p>
        <p>Prefer email? Use the form and I'll get back to you at the address you provide.</p>
      </div>

      <form id="contact-form" novalidate>
        <!-- Hidden CSRF token generated server-side; sent with every submission. -->
        <input type="hidden" name="csrf_token" value="<?= escapeHTML($csrfToken) ?>">

        <!-- Honeypot: hidden from real visitors via CSS, but most bots fill it in.
             tabindex=-1 and autocomplete=off keep it out of keyboard/autofill flow. -->
        <div style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
          <label for="website">Website</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" maxlength="100" required>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" maxlength="150" required>
        </div>
        <div class="field">
          <label for="subject">Subject</label>
          <input type="text" id="subject" name="subject" maxlength="255" required>
        </div>
        <div class="field">
          <label for="message">Message</label>
          <textarea id="message" name="message" rows="5" required></textarea>
        </div>

        <button type="submit" class="btn" id="submit-btn">Send Message</button>
        <div id="form-status" role="status" aria-live="polite"></div>
      </form>
    </div>
  </div>
</section>

<footer>
  &copy; <?= date('Y') ?> Portfolio. Built with PHP, JavaScript, and MySQL.
</footer>

<script>
(function () {
  const form = document.getElementById('contact-form');
  const statusEl = document.getElementById('form-status');
  const submitBtn = document.getElementById('submit-btn');

  form.addEventListener('submit', async function (event) {
    event.preventDefault(); // Prevent the default full-page reload/navigation.

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';
    statusEl.textContent = '';
    statusEl.className = '';

    const formData = new FormData(form);

    try {
      const response = await fetch('process-contact.php', {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
      });

      const data = await response.json();

      if (data.success) {
        statusEl.textContent = data.message;
        statusEl.className = 'status-success';
        form.reset();
      } else {
        // If field-level errors were returned, show the first one; else the general message.
        let message = data.message || 'Something went wrong. Please try again.';
        if (data.errors) {
          const firstField = Object.keys(data.errors)[0];
          message = data.errors[firstField];
        }
        statusEl.textContent = message;
        statusEl.className = 'status-error';
      }
    } catch (networkError) {
      statusEl.textContent = 'Network error. Please check your connection and try again.';
      statusEl.className = 'status-error';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Message';
    }
  });
})();
</script>

</body>
</html>
