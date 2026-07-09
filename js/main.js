/* ═══════════════════════════════════
   5. TYPEWRITER EFFECT (Fixed)
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
let isTyping = false;

function type() {
  if (isTyping) return;
  isTyping = true;
  
  const current = roles[roleIdx];
  if (!deleting) {
    typedEl.textContent = current.substring(0, charIdx + 1);
    charIdx++;
    if (charIdx === current.length) {
      deleting = true;
      setTimeout(() => {
        isTyping = false;
        type();
      }, 2000);
      return;
    }
  } else {
    typedEl.textContent = current.substring(0, charIdx - 1);
    charIdx--;
    if (charIdx === 0) {
      deleting = false;
      roleIdx = (roleIdx + 1) % roles.length;
      setTimeout(() => {
        isTyping = false;
        type();
      }, 500);
      return;
    }
  }
  setTimeout(() => {
    isTyping = false;
    type();
  }, deleting ? 40 : 80);
}

// Start the typewriter
setTimeout(type, 1000);