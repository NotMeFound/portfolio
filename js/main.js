document.addEventListener('DOMContentLoaded', () => {
    // 1. THEME TOGGLE
    const themeBtn = document.getElementById('theme-btn');
    const applyTheme = (t) => {
        document.documentElement.setAttribute('data-theme', t);
        localStorage.setItem('ko-theme', t);
        if(document.getElementById('theme-icon')) document.getElementById('theme-icon').textContent = t === 'dark' ? '🌙' : '☀️';
    };
    themeBtn?.addEventListener('click', () => applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));
    applyTheme(localStorage.getItem('ko-theme') || 'dark');

    // 2. VISITOR COUNTER
    fetch('php/visitor.php')
        .then(res => res.json())
        .then(data => { if(data.visitors) document.getElementById('visitor-count').textContent = data.visitors; });

    // 3. TYPEWRITER
    const typedEl = document.getElementById('typed-text');
    const roles = ['Full-Stack Developer', 'PHP Engineer', 'BCA Graduate'];
    let rIdx = 0, cIdx = 0, isDeleting = false;
    function type() {
        const full = roles[rIdx];
        typedEl.textContent = isDeleting ? full.substring(0, cIdx--) : full.substring(0, cIdx++);
        if (!isDeleting && cIdx > full.length) { isDeleting = true; setTimeout(type, 2000); }
        else if (isDeleting && cIdx < 0) { isDeleting = false; rIdx = (rIdx + 1) % roles.length; setTimeout(type, 500); }
        else setTimeout(type, isDeleting ? 50 : 100);
    }
    if(typedEl) type();

    // 4. CONTACT FORM (The Real-Time Part)
    const form = document.getElementById('contact-form');
    const status = document.getElementById('form-status');
    const btn = document.getElementById('submit-btn');

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-loading').hidden = false;
        
        try {
            const res = await fetch('php/send-message.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            status.textContent = data.success ? data.message : data.error;
            status.className = `form-status ${data.success ? 'success' : 'error'}`;
            status.hidden = false;
            if(data.success) form.reset();
        } catch (err) {
            status.textContent = "Network error. Try again.";
            status.hidden = false;
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-loading').hidden = true;
        }
    });
});