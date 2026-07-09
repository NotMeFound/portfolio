<?php
// ============================================
// SECURITY HEADERS
// ============================================
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ============================================
// SESSION SECURITY
// ============================================
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.use_strict_mode', 1);

session_start();

// ============================================
// SESSION TIMEOUT
// ============================================
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ============================================
// CSRF TOKEN FUNCTIONS - FIXED
// ============================================
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// RATE LIMITING
// ============================================
function check_rate_limit($key, $max_attempts = 5, $time_window = 900) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = $key . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    $data = &$_SESSION['rate_limit'][$key];
    $time_diff = time() - $data['first_attempt'];
    
    if ($time_diff > $time_window) {
        $data = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    if ($data['count'] >= $max_attempts) {
        return false;
    }
    
    $data['count']++;
    return true;
}

// ============================================
// INPUT SANITIZATION
// ============================================
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ============================================
// PASSWORD HASH - REPLACE THIS
// Generate using: echo password_hash('your_password', PASSWORD_ARGON2ID);
// ============================================
define('ADMIN_PASSWORD_HASH', '$2a$12$piqX7yCf5Qto99KVO7hV.ec4bTzHuE/p4CvxsPC20hp4QthD0y/Ji'); // ← CHANGE THIS!

// ============================================
// REQUIRE CONFIG
// ============================================
require_once 'php/config.php';

// ============================================
// VARIABLES
// ============================================
$error = '';
$statusMsg = '';
$statusType = '';
$projects = [];
$messages = [];
$unread_count = 0;
$visitors_today = 0;
$visitors_total = 0;
$projCount = 0;

// ============================================
// HANDLE LOGIN
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (!check_rate_limit('login', 5, 900)) {
        $error = 'Too many login attempts. Please wait 15 minutes.';
    } else {
        $password = sanitize_input($_POST['password'] ?? '');
        
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            unset($_SESSION['rate_limit']['login_' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')]);
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['login_time'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            error_log("Admin login successful from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Incorrect password.';
            error_log("Admin login failed from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}

// ============================================
// HANDLE LOGOUT
// ============================================
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

// ============================================
// CHECK AUTHENTICATION
// ============================================
function is_authenticated() {
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    
    $user_agent = $_SESSION['user_agent'] ?? '';
    $ip_address = $_SESSION['ip_address'] ?? '';
    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if ($user_agent !== $current_ua || $ip_address !== $current_ip) {
        session_unset();
        session_destroy();
        return false;
    }
    
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
        session_unset();
        session_destroy();
        return false;
    }
    
    return true;
}

// ============================================
// AUTHENTICATED USER LOGIC
// ============================================
if (is_authenticated()) {
    try {
        $pdo = get_pdo();
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die('<p style="color:#fc8181;padding:2rem">System temporarily unavailable. Please try again later.</p>');
    }
    
    // ============================================
    // HANDLE POST REQUESTS (AJAX + Form)
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // ============================================
        // HANDLE MARK AS READ (AJAX)
        // ============================================
        if ($action === 'mark_read') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
                exit;
            }
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false]);
            exit;
        }
        
        // ============================================
        // HANDLE MARK ALL READ (AJAX)
        // ============================================
        if ($action === 'mark_all_read') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE contacts SET is_read = 1 WHERE is_read = 0");
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;
        }
        
        // ============================================
        // CSRF CHECK FOR FORM ACTIONS
        // ============================================
        if ($action !== 'login' && $action !== 'mark_read' && $action !== 'mark_all_read') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $statusMsg = 'Security validation failed. Please refresh the page and try again.';
                $statusType = 'error';
                error_log("CSRF validation failed for action: $action from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                goto skip_processing;
            }
        }
        
        // ============================================
        // ADD PROJECT
        // ============================================
        if ($action === 'add_project') {
            $title = sanitize_input($_POST['title'] ?? '');
            
            if (empty($title)) {
                $statusMsg = 'Project title is required.';
                $statusType = 'error';
            } else {
                $description = sanitize_input($_POST['description'] ?? '');
                $tags = sanitize_input($_POST['tags'] ?? '');
                $github_url = sanitize_input($_POST['github_url'] ?? '');
                $demo_url = sanitize_input($_POST['demo_url'] ?? '');
                $icon = sanitize_input($_POST['icon'] ?? '🛠️');
                $sort_order = filter_var($_POST['sort_order'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]) ?: 10;
                
                if (!empty($github_url) && !validate_url($github_url)) {
                    $statusMsg = 'Invalid GitHub URL format.';
                    $statusType = 'error';
                } elseif (!empty($demo_url) && !validate_url($demo_url)) {
                    $statusMsg = 'Invalid Demo URL format.';
                    $statusType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO projects (title, description, tags, github_url, demo_url, icon, sort_order)
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $title,
                        $description,
                        $tags,
                        $github_url,
                        $demo_url,
                        $icon,
                        $sort_order
                    ]);
                    
                    $statusMsg = 'Project added successfully!';
                    $statusType = 'success';
                    error_log("Project added: '$title' by admin");
                }
            }
        }
        
        // ============================================
        // DELETE PROJECT
        // ============================================
        if ($action === 'delete_project') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
                $stmt->execute([$id]);
                $project = $stmt->fetch();
                
                if ($project) {
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$id]);
                    $statusMsg = 'Project deleted successfully.';
                    $statusType = 'success';
                    error_log("Project deleted: '" . $project['title'] . "' (ID: $id) by admin");
                } else {
                    $statusMsg = 'Project not found.';
                    $statusType = 'error';
                }
            } else {
                $statusMsg = 'Invalid project ID.';
                $statusType = 'error';
            }
        }
    }
    
    skip_processing:
    
    // ============================================
    // FETCH DATA
    // ============================================
    try {
        // Projects
        $projects = $pdo->query("SELECT * FROM projects ORDER BY sort_order ASC LIMIT 100")->fetchAll();
        $projCount = count($projects);
        
        // Messages
        $stmt = $pdo->prepare("
            SELECT id, name, email, subject, message, is_read, submitted_at 
            FROM contacts 
            ORDER BY id DESC 
            LIMIT 50
        ");
        $stmt->execute();
        $messages = $stmt->fetchAll();
        
        // Unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE is_read = 0");
        $stmt->execute();
        $unread_count = (int)$stmt->fetchColumn();
        
        // Visitors - today
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT visitor_id) 
            FROM visitors 
            WHERE DATE(visited_at) = ?
        ");
        $stmt->execute([$today]);
        $visitors_today = (int)$stmt->fetchColumn();
        
        // Visitors - total
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT visitor_id) FROM visitors");
        $stmt->execute();
        $visitors_total = (int)$stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage());
        $statusMsg = 'Error loading data. Please refresh the page.';
        $statusType = 'error';
    }
}

// ============================================
// GENERATE CSRF TOKEN
// ============================================
$csrf_token = generate_csrf_token();

// Get current date for display
$current_date = date('M j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Portfolio Admin — Karan Oli</title>
    <style>
        :root {
            --bg:#0c0e11; --bg2:#12151a; --bg3:#1a1f27;
            --border:#232830; --border2:#2e3540;
            --text:#e2e8f0; --text2:#8b9ab0; --text3:#4a5568;
            --accent:#06b6d4; --orange:#f97316; --green:#10b981;
            --red:#fc8181; --radius:8px;
            --font:'Inter',system-ui,sans-serif;
            --mono:'JetBrains Mono','Fira Code',monospace;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--bg);color:var(--text);font-family:var(--font);line-height:1.6}
        a{color:var(--accent);text-decoration:none}
        button{cursor:pointer;font-family:inherit}

        .admin-header{
            background:var(--bg2);border-bottom:1px solid var(--border);
            padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;
            flex-wrap:wrap;gap:1rem;
        }
        .admin-logo{
            font-family:var(--mono);font-weight:700;color:var(--accent);font-size:1.1rem;
            display:flex;align-items:center;gap:0.5rem;
        }
        .admin-nav{
            display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;
        }
        .admin-nav a{
            font-size:0.85rem;color:var(--text2);padding:0.4rem 0.8rem;
            border-radius:6px;transition:all 0.2s;
        }
        .admin-nav a:hover{
            color:var(--accent);background:rgba(6,182,212,0.08);
        }
        .admin-nav a.active{
            color:var(--accent);background:rgba(6,182,212,0.12);
        }
        .back-link{font-size:0.85rem;color:var(--text2)}
        .back-link:hover{color:var(--accent)}

        .admin-body{max-width:1200px;margin:0 auto;padding:2rem 1.5rem;display:grid;gap:2rem}

        .card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:1.75rem}
        .card-title{font-size:1rem;font-weight:600;margin-bottom:1.5rem;color:var(--accent)}

        /* Login */
        .login-wrap{max-width:380px;margin:8rem auto;padding:0 1.5rem}
        .login-card{background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:2rem}
        .login-card h2{font-size:1.3rem;margin-bottom:1.5rem}
        .form-group{display:flex;flex-direction:column;gap:0.4rem;margin-bottom:1rem}
        .form-group label{font-size:0.82rem;color:var(--text2)}
        .form-group input,
        .form-group textarea{
            padding:0.65rem 1rem;background:var(--bg3);border:1px solid var(--border2);
            border-radius:var(--radius);color:var(--text);font-family:var(--font);font-size:0.9rem;outline:none;
            width:100%;
        }
        .form-group input:focus,
        .form-group textarea:focus{border-color:var(--accent)}
        .form-group textarea{resize:vertical;min-height:60px}
        
        .btn-primary{
            width:100%;padding:0.7rem;background:var(--accent);color:#000;
            font-weight:700;font-size:0.95rem;border:none;border-radius:var(--radius);
            cursor:pointer;transition:background 0.2s
        }
        .btn-primary:hover{background:#0891b2}
        .error-msg{color:var(--red);font-size:0.85rem;margin-top:0.5rem}

        /* Stats */
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem}
        .stat-card{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;text-align:center}
        .stat-num{display:block;font-size:2rem;font-weight:700;color:var(--accent)}
        .stat-lbl{font-size:0.78rem;color:var(--text3);text-transform:uppercase;letter-spacing:0.1em;margin-top:0.2rem}

        .live-clock{font-variant-numeric:tabular-nums;letter-spacing:0.05em}

        /* Tables */
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:0.87rem}
        th{padding:0.6rem 1rem;text-align:left;font-size:0.72rem;font-weight:600;letter-spacing:0.1em;
           text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border)}
        td{padding:0.75rem 1rem;border-bottom:1px solid var(--border);color:var(--text2);vertical-align:top}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:var(--bg3)}

        /* Forms */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .form-grid .span-2{grid-column:1/-1}
        .btn-add{
            padding:0.65rem 1.5rem;background:var(--accent);color:#000;
            font-weight:700;border:none;border-radius:var(--radius);cursor:pointer;transition:background 0.2s
        }
        .btn-add:hover{background:#0891b2}
        .btn-del{
            padding:0.3rem 0.6rem;background:rgba(252,129,129,0.12);color:var(--red);
            border:1px solid rgba(252,129,129,0.3);border-radius:5px;font-size:0.78rem;cursor:pointer;
            transition:background 0.2s;
        }
        .btn-del:hover{background:rgba(252,129,129,0.25)}
        .logout-btn{
            padding:0.4rem 1rem;border:1px solid var(--border2);background:none;
            color:var(--text2);border-radius:6px;font-size:0.85rem;cursor:pointer;transition:all 0.2s
        }
        .logout-btn:hover{border-color:var(--red);color:var(--red)}
        
        /* Status Messages */
        .status-msg{padding:0.6rem 1rem;border-radius:var(--radius);font-size:0.85rem;margin-top:0.75rem}
        .status-msg.success{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:var(--green)}
        .status-msg.error{background:rgba(252,129,129,0.1);border:1px solid rgba(252,129,129,0.3);color:var(--red)}
        
        .table-actions{display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap}
        .token-info{font-size:0.7rem;color:var(--text3);margin-top:0.5rem;text-align:center}

        /* ── INBOX STYLES ── */
        .card-header-inbox {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .unread-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            background: var(--red);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 999px;
            margin-left: 0.5rem;
        }
        .unread-badge.zero { background: var(--text3); }

        .btn-refresh, .btn-mark-all {
            padding: 0.3rem 0.8rem;
            border: 1px solid var(--border2);
            border-radius: 6px;
            background: var(--bg3);
            color: var(--text2);
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-refresh:hover, .btn-mark-all:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-mark-all { background: var(--accent-bg); }

        .inbox-container {
            max-height: 500px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .inbox-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--bg3);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: all 0.2s;
        }
        .inbox-item.unread { border-left: 3px solid var(--accent); background: var(--accent-bg); }
        .inbox-item.read { opacity: 0.7; }
        .inbox-item:hover { border-color: var(--border2); }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            margin-top: 6px;
        }
        .unread-dot { background: var(--accent); animation: pulse 2s infinite; }
        .read-dot { background: var(--text3); }

        .inbox-content { flex: 1; min-width: 0; }
        .inbox-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.3rem;
        }
        .inbox-name { color: var(--text); font-size: 0.9rem; }
        .inbox-email { color: var(--text2); font-size: 0.8rem; }
        .inbox-date { color: var(--text3); font-size: 0.7rem; margin-left: auto; font-family: var(--mono); }
        .inbox-subject {
            color: var(--text2);
            font-size: 0.82rem;
            margin-bottom: 0.2rem;
        }
        .inbox-message {
            color: var(--text2);
            font-size: 0.85rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .btn-mark-read {
            padding: 0.2rem 0.6rem;
            border: 1px solid var(--border2);
            border-radius: 4px;
            background: transparent;
            color: var(--text3);
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-mark-read:hover {
            border-color: var(--green);
            color: var(--green);
        }
        .inbox-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
            font-size: 0.78rem;
            color: var(--text3);
        }

        /* ── TOAST STYLES ── */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 360px;
        }
        .toast {
            padding: 12px 16px;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font-size: 0.85rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toast.success { border-color: var(--green); }
        .toast.info { border-color: var(--accent); }
        .toast.error { border-color: var(--red); }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }

        @media (max-width:768px){
            .form-grid{grid-template-columns:1fr}
            .form-grid .span-2{grid-column:1}
            .admin-header{flex-direction:column;align-items:stretch;text-align:center}
            .admin-nav{justify-content:center;}
            .card-header-inbox{flex-direction:column;align-items:stretch}
            .inbox-header{flex-direction:column;align-items:flex-start}
            .inbox-date{margin-left:0}
        }
    </style>
</head>
<body>

<?php if (!is_authenticated()): ?>
<!-- ============================================ -->
<!-- LOGIN FORM -->
<!-- ============================================ -->
<div class="login-wrap">
    <div class="login-card">
        <h2>🔐 Admin Login</h2>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="login" />
            <div class="form-group">
                <label for="pwd">Password</label>
                <input type="password" name="password" id="pwd" required autofocus 
                       placeholder="Enter admin password" />
            </div>
            <button type="submit" class="btn-primary">Sign in</button>
            <?php if ($error): ?>
                <p class="error-msg">⚠️ <?= htmlspecialchars($error) ?></p>
            <?php endif ?>
        </form>
        <?php 
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (isset($_SESSION['rate_limit']['login_' . $ip])): 
        ?>
        <p style="color:var(--text3);font-size:0.75rem;margin-top:1rem;text-align:center">
            Attempts: <?= $_SESSION['rate_limit']['login_' . $ip]['count'] ?? 0 ?>/5
        </p>
        <?php endif ?>
    </div>
</div>

<?php else: ?>

<!-- ============================================ -->
<!-- ADMIN DASHBOARD -->
<!-- ============================================ -->
<header class="admin-header">
    <div class="admin-logo">
        <span>🔒</span> Portfolio Admin
    </div>
    
    <nav class="admin-nav">
        <a href="admin.php" class="active">📊 Dashboard</a>
        <a href="index.html" class="back-link">← View Portfolio</a>
        
        <!-- Logout Form -->
        <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="logout" />
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>" />
            <button type="submit" class="logout-btn">🚪 Logout</button>
        </form>
    </nav>
</header>

<div class="admin-body">

    <!-- ============================================ -->
    <!-- STATS - Dashboard Overview -->
    <!-- ============================================ -->
    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-num" id="todayVisitors"><?= number_format($visitors_today) ?></span>
            <span class="stat-lbl">👤 Today's Visitors</span>
        </div>
        <div class="stat-card">
            <span class="stat-num" id="totalVisitors"><?= number_format($visitors_total) ?></span>
            <span class="stat-lbl">📊 Total Visitors</span>
        </div>
        <div class="stat-card">
            <span class="stat-num" id="unreadCount"><?= $unread_count ?></span>
            <span class="stat-lbl">📬 Unread Messages</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?= $projCount ?></span>
            <span class="stat-lbl">📁 Projects</span>
        </div>
        <div class="stat-card">
            <span class="stat-num" id="currentDate"><?= $current_date ?></span>
            <span class="stat-lbl">📅 Today</span>
        </div>
        <div class="stat-card">
            <span class="stat-num live-clock" id="liveClock">--:--:--</span>
            <span class="stat-lbl">🕐 Live Time</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- STATUS MESSAGES -->
    <!-- ============================================ -->
    <?php if ($statusMsg): ?>
    <div class="status-msg <?= $statusType ?>">
        <?= htmlspecialchars($statusMsg) ?>
    </div>
    <?php endif ?>

    <!-- ============================================ -->
    <!-- PROJECTS MANAGEMENT -->
    <!-- ============================================ -->
    <div class="card">
        <p class="card-title">📁 Manage Projects</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Title</th>
                        <th>Tags</th>
                        <th>Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                    <tr>
                        <td style="font-size:1.5rem;"><?= htmlspecialchars($p['icon']) ?></td>
                        <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                        <td style="font-size:0.78rem;color:var(--text3)"><?= htmlspecialchars($p['tags']) ?></td>
                        <td><?= (int)$p['sort_order'] ?></td>
                        <td>
                            <form method="POST" style="margin:0" onsubmit="return confirm('⚠️ Delete project &quot;<?= htmlspecialchars($p['title']) ?>&quot; permanently?')">
                                <input type="hidden" name="action" value="delete_project" />
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>" />
                                <button type="submit" class="btn-del">🗑 Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach ?>
                    <?php if (empty($projects)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:2rem">
                        📂 No projects yet. Add one below!
                    </td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>

        <!-- ============================================ -->
        <!-- ADD PROJECT FORM -->
        <!-- ============================================ -->
        <div style="margin-top:2rem;border-top:1px solid var(--border);padding-top:1.5rem">
            <p style="font-size:0.85rem;font-weight:600;color:var(--text2);margin-bottom:1rem">
                ➕ Add New Project
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="add_project" />
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>" />
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" name="title" id="title" required 
                               placeholder="My Awesome Project" maxlength="100" />
                    </div>
                    
                    <div class="form-group">
                        <label for="icon">Icon (emoji)</label>
                        <input type="text" name="icon" id="icon" placeholder="🛠️" maxlength="4" />
                    </div>
                    
                    <div class="form-group span-2">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" rows="3" 
                                  placeholder="What this project does..." maxlength="500"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags (comma-separated)</label>
                        <input type="text" name="tags" id="tags" placeholder="PHP,SQL,JavaScript" maxlength="200" />
                    </div>
                    
                    <div class="form-group">
                        <label for="sort_order">Sort order</label>
                        <input type="number" name="sort_order" id="sort_order" value="10" min="0" max="999" />
                    </div>
                    
                    <div class="form-group">
                        <label for="github_url">GitHub URL</label>
                        <input type="url" name="github_url" id="github_url" 
                               placeholder="https://github.com/..." maxlength="255" />
                    </div>
                    
                    <div class="form-group">
                        <label for="demo_url">Demo URL</label>
                        <input type="url" name="demo_url" id="demo_url" 
                               placeholder="https://..." maxlength="255" />
                    </div>
                </div>
                
                <button type="submit" class="btn-add">➕ Add Project</button>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MESSAGES INBOX - Live Feed -->
    <!-- ============================================ -->
    <div class="card" id="inbox-card">
        <div class="card-header-inbox">
            <p class="card-title" style="margin-bottom:0">📬 Messages Inbox <span class="unread-badge <?= $unread_count === 0 ? 'zero' : '' ?>" id="unreadBadge"><?= $unread_count ?></span></p>
            <button class="btn-refresh" id="refreshInbox" title="Refresh now">🔄</button>
            <?php if ($unread_count > 0): ?>
            <button class="btn-mark-all" id="markAllRead">✓ Mark all read</button>
            <?php endif; ?>
        </div>
        <div class="inbox-container" id="inboxContainer">
            <?php if (empty($messages)): ?>
                <p style="text-align:center;color:var(--text3);padding:2rem;">📭 No messages yet</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                <div class="inbox-item <?= $msg['is_read'] ? 'read' : 'unread' ?>" data-id="<?= $msg['id'] ?>">
                    <div class="inbox-status">
                        <span class="status-dot <?= $msg['is_read'] ? 'read-dot' : 'unread-dot' ?>"></span>
                    </div>
                    <div class="inbox-content">
                        <div class="inbox-header">
                            <strong class="inbox-name"><?= htmlspecialchars($msg['name']) ?></strong>
                            <span class="inbox-email"><?= htmlspecialchars($msg['email']) ?></span>
                            <span class="inbox-date"><?= date('M j, Y g:i A', strtotime($msg['submitted_at'])) ?></span>
                        </div>
                        <div class="inbox-subject"><?= htmlspecialchars($msg['subject'] ?: '(No subject)') ?></div>
                        <div class="inbox-message"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    </div>
                    <?php if (!$msg['is_read']): ?>
                    <button class="btn-mark-read" data-id="<?= $msg['id'] ?>">Mark read</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="inbox-footer">
            <span class="inbox-status-text" id="inboxStatus">Auto-refresh every 15s</span>
            <span class="inbox-count" id="inboxCount"><?= count($messages) ?> messages</span>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ============================================ -->
    <div class="card" style="border-color:var(--border2);">
        <p class="card-title">⚡ Quick Actions</p>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <a href="index.html" style="background:var(--bg3);padding:0.75rem 1.5rem;border-radius:var(--radius);border:1px solid var(--border);display:inline-flex;align-items:center;gap:0.5rem;color:var(--text2);transition:all 0.2s;">
                🌐 View Portfolio
            </a>
            <a href="#" onclick="window.location.reload();" style="background:var(--bg3);padding:0.75rem 1.5rem;border-radius:var(--radius);border:1px solid var(--border);display:inline-flex;align-items:center;gap:0.5rem;color:var(--text2);transition:all 0.2s;">
                🔄 Refresh Dashboard
            </a>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SECURITY FOOTER -->
    <!-- ============================================ -->
    <div style="text-align:center;font-size:0.75rem;color:var(--text3);border-top:1px solid var(--border);padding-top:1rem">
        🔒 CSRF Protected • Rate Limiting (5/15min) • Session Security • Input Validation
        <br>⏱ Session expires after 1 hour of inactivity
        <br><span class="token-info">🔑 Token: <?= substr($csrf_token, 0, 10) ?>... (valid for this session)</span>
    </div>

</div>

<?php endif; ?>

<!-- ============================================ -->
<!-- JAVASCRIPT - LIVE CLOCK & FUNCTIONALITY -->
<!-- ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // LIVE CLOCK - Updates every second
    // ============================================
    function updateClock() {
        const now = new Date();
        
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const timeString = hours + ':' + minutes + ':' + seconds;
        
        const clockElement = document.getElementById('liveClock');
        if (clockElement) {
            clockElement.textContent = timeString;
        }
        
        const dateElement = document.getElementById('currentDate');
        if (dateElement) {
            const options = { month: 'short', day: 'numeric', year: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);
            dateElement.textContent = dateString;
        }
    }
    
    updateClock();
    setInterval(updateClock, 1000);

    <?php if (is_authenticated()): ?>
    // ============================================
    // INBOX AUTO-REFRESH (every 15 seconds)
    // ============================================
    let lastMessageId = <?= !empty($messages) ? (int)$messages[0]['id'] : 0 ?>;
    let pollingInterval = null;

    function refreshInbox() {
        const url = `php/messages.php?since_id=${lastMessageId}&limit=20`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    // Update unread badge
                    const badge = document.getElementById('unreadBadge');
                    if (badge) {
                        badge.textContent = data.unread_count;
                        badge.classList.toggle('zero', data.unread_count === 0);
                    }
                    
                    // Update unread count stat
                    const unreadStat = document.getElementById('unreadCount');
                    if (unreadStat) {
                        unreadStat.textContent = data.unread_count;
                    }
                    
                    // Update last message ID
                    if (data.latest_id > lastMessageId) {
                        lastMessageId = data.latest_id;
                    }
                    
                    // Prepend new messages to the inbox
                    const container = document.getElementById('inboxContainer');
                    if (container) {
                        // Remove "no messages" placeholder
                        const placeholder = container.querySelector('p[style*="text-align:center"]');
                        if (placeholder && data.messages.length > 0) {
                            placeholder.remove();
                        }
                        
                        // Add new messages at the top
                        let hasNew = false;
                        data.messages.forEach(msg => {
                            // Check if message already exists
                            const existing = container.querySelector(`.inbox-item[data-id="${msg.id}"]`);
                            if (!existing) {
                                hasNew = true;
                                const item = createInboxItem(msg);
                                container.prepend(item);
                            }
                        });
                        
                        // Update count
                        const countEl = document.getElementById('inboxCount');
                        if (countEl) {
                            const totalItems = container.querySelectorAll('.inbox-item').length;
                            countEl.textContent = `${totalItems} messages`;
                        }
                        
                        // Show notification if new messages
                        if (hasNew) {
                            showToast('📬 New message received!', 'success');
                        }
                    }
                }
                
                // Update status text
                const statusEl = document.getElementById('inboxStatus');
                if (statusEl) {
                    const now = new Date();
                    statusEl.textContent = `Auto-refresh every 15s • Last update: ${now.toLocaleTimeString()}`;
                }
            })
            .catch(err => {
                console.error('Inbox refresh error:', err);
            });
    }

    function createInboxItem(msg) {
        const div = document.createElement('div');
        div.className = `inbox-item ${msg.is_read ? 'read' : 'unread'}`;
        div.dataset.id = msg.id;
        
        const isUnread = !msg.is_read;
        
        div.innerHTML = `
            <div class="inbox-status">
                <span class="status-dot ${isUnread ? 'unread-dot' : 'read-dot'}"></span>
            </div>
            <div class="inbox-content">
                <div class="inbox-header">
                    <strong class="inbox-name">${escapeHtml(msg.name)}</strong>
                    <span class="inbox-email">${escapeHtml(msg.email)}</span>
                    <span class="inbox-date">${escapeHtml(msg.submitted_at)}</span>
                </div>
                <div class="inbox-subject">${escapeHtml(msg.subject || '(No subject)')}</div>
                <div class="inbox-message">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
            </div>
            ${isUnread ? `<button class="btn-mark-read" data-id="${msg.id}">Mark read</button>` : ''}
        `;
        
        // Add mark read handler
        const markBtn = div.querySelector('.btn-mark-read');
        if (markBtn) {
            markBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                markAsRead(this.dataset.id);
            });
        }
        
        return div;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function markAsRead(id) {
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`.inbox-item[data-id="${id}"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    const dot = item.querySelector('.status-dot');
                    if (dot) {
                        dot.className = 'status-dot read-dot';
                    }
                    const markBtn = item.querySelector('.btn-mark-read');
                    if (markBtn) markBtn.remove();
                    
                    // Update badge
                    const badge = document.getElementById('unreadBadge');
                    if (badge) {
                        const current = parseInt(badge.textContent) || 0;
                        const newCount = Math.max(0, current - 1);
                        badge.textContent = newCount;
                        badge.classList.toggle('zero', newCount === 0);
                    }
                    
                    // Update unread stat
                    const unreadStat = document.getElementById('unreadCount');
                    if (unreadStat) {
                        const current = parseInt(unreadStat.textContent) || 0;
                        unreadStat.textContent = Math.max(0, current - 1);
                    }
                    
                    // Update mark all button
                    updateMarkAllButton();
                }
            }
        })
        .catch(err => console.error('Mark read error:', err));
    }

    function updateMarkAllButton() {
        const badge = document.getElementById('unreadBadge');
        const markAllBtn = document.getElementById('markAllRead');
        const unreadCount = parseInt(badge?.textContent || 0);
        
        if (unreadCount === 0) {
            if (markAllBtn) markAllBtn.style.display = 'none';
        } else {
            if (markAllBtn) markAllBtn.style.display = 'inline-block';
        }
    }

    // Mark all read
    document.getElementById('markAllRead')?.addEventListener('click', function() {
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        
        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        formData.append('csrf_token', csrfToken);
        
        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.inbox-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    const dot = item.querySelector('.status-dot');
                    if (dot) {
                        dot.className = 'status-dot read-dot';
                    }
                    const markBtn = item.querySelector('.btn-mark-read');
                    if (markBtn) markBtn.remove();
                });
                const badge = document.getElementById('unreadBadge');
                if (badge) {
                    badge.textContent = '0';
                    badge.classList.add('zero');
                }
                const unreadStat = document.getElementById('unreadCount');
                if (unreadStat) {
                    unreadStat.textContent = '0';
                }
                updateMarkAllButton();
                showToast('✓ All messages marked as read', 'success');
            }
        })
        .catch(err => console.error('Mark all read error:', err));
    });

    // Manual refresh button
    document.getElementById('refreshInbox')?.addEventListener('click', refreshInbox);

    // Start polling
    function startPolling() {
        setTimeout(refreshInbox, 1000);
        pollingInterval = setInterval(refreshInbox, 15000);
    }

    startPolling();

    // ============================================
    // VISITOR COUNTER AUTO-REFRESH (every 30s)
    // ============================================
    function refreshVisitors() {
        fetch('php/visitor.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const todayEl = document.getElementById('todayVisitors');
                    if (todayEl) {
                        animateNumber(todayEl, parseInt(todayEl.textContent.replace(/,/g, '')), data.today, 600);
                    }
                    const totalEl = document.getElementById('totalVisitors');
                    if (totalEl) {
                        animateNumber(totalEl, parseInt(totalEl.textContent.replace(/,/g, '')), data.total, 600);
                    }
                }
            })
            .catch(err => console.error('Visitor refresh error:', err));
    }

    function animateNumber(el, from, to, duration) {
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

    // Refresh visitors every 30 seconds
    setInterval(refreshVisitors, 30000);

    // ============================================
    // TOAST NOTIFICATIONS
    // ============================================
    function showToast(message, type = 'info') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // ============================================
    // CSRF TOKEN LOGGING
    // ============================================
    const forms = document.querySelectorAll('form');
    console.log('🔒 CSRF Protection Active');
    console.log('📝 Forms found:', forms.length);
    <?php endif; ?>
});
</script>

</body>
</html>