# Karan Oli — Portfolio Website

A fully self-interactive portfolio built with **HTML, CSS, JavaScript, PHP, and MySQL** — no frameworks.

---

## 📁 Project Structure

```
karan-portfolio/
├── index.html          ← Main portfolio page
├── admin.php           ← Admin dashboard (projects + contacts)
├── database.sql        ← Run this to create the DB schema
├── .htaccess           ← Apache security & performance config
│
├── css/
│   └── style.css       ← All styles (dark/light mode, responsive)
│
├── js/
│   └── main.js         ← All interactivity (terminal, filter, AJAX, etc.)
│
├── php/
│   ├── config.php      ← DB credentials (change before deploying!)
│   ├── contact.php     ← AJAX contact form handler
│   └── visitor.php     ← Visitor counter endpoint
│
└── assets/
    └── Karan_Oli_Resume.pdf   ← Place your resume here
```

---

## ⚡ Features

| Feature | Tech used |
|---|---|
| Dark / Light mode toggle | CSS variables + localStorage |
| "Quick Scan" recruiter mode | JS class toggle, CSS visibility |
| Typewriter hero text | Vanilla JS |
| Animated skill bars | IntersectionObserver API |
| Project tag filter | Vanilla JS DOM filter |
| Interactive terminal widget | JS command parser |
| AJAX contact form | fetch() + PHP + PDO + MySQL |
| Live visitor counter | PHP session + MySQL |
| Admin dashboard | PHP + PDO + session auth |
| SQL injection protection | PDO prepared statements |
| Responsive design | CSS Grid + Flexbox |

---

## 🚀 Setup (Local — XAMPP)

1. **Copy the folder** into `C:/xampp/htdocs/karan-portfolio/`

2. **Create the database:**
   - Open phpMyAdmin → http://localhost/phpmyadmin
   - Click **Import** → select `database.sql` → Go

3. **Configure DB credentials:**
   - Open `php/config.php`
   - Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - Default for XAMPP: host=`localhost`, user=`root`, pass=`(empty)`

4. **Add your resume:**
   - Place your PDF at `assets/Karan_Oli_Resume.pdf`

5. **Open the site:**
   - http://localhost/karan-portfolio/

6. **Admin panel:**
   - http://localhost/karan-portfolio/admin.php
   - Default password: `karan@admin2024` (change in `admin.php` line 17!)

---

## 🌐 Deployment (cPanel / Shared Hosting)

1. Upload all files to `public_html/` via File Manager or FTP
2. Create a MySQL database via cPanel → MySQL Databases
3. Import `database.sql` via phpMyAdmin
4. Update `php/config.php` with your hosting DB credentials
5. Change the admin password in `admin.php`
6. (Optional) Enable HTTPS redirect by uncommenting lines in `.htaccess`

---

## 🔒 Security Checklist

- [x] PDO prepared statements (prevents SQL injection)
- [x] `htmlspecialchars()` on all output (prevents XSS)
- [x] `strip_tags()` on all input
- [x] Rate limiting: 3 contact submissions per IP per hour
- [x] Session-based admin authentication
- [x] `config.php` blocked from direct browser access via `.htaccess`
- [x] Security headers set in `.htaccess`
- [ ] Change admin password before deploying!
- [ ] Set real DB credentials in `config.php`
- [ ] Enable HTTPS in `.htaccess` (uncomment lines)

---

## 🎨 Customization

| What to change | Where |
|---|---|
| Your name, bio, email | `index.html` — hero + about + contact sections |
| Projects | `index.html` project cards OR admin panel |
| Skills & percentages | `index.html` skill bars (`data-level="90"`) |
| Social links | `index.html` nav + about + footer |
| Accent color | `css/style.css` → `--accent: #06b6d4` |
| Admin password | `admin.php` → `define('ADMIN_PASSWORD', ...)` |

---

Built by Karan Oli · HTML · CSS · JavaScript · PHP · MySQL
