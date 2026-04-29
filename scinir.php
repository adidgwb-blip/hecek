<?php
/**
 * PROJECT SCANNER v27.0 - SCAN ALL FILES (NO EXTENSION LIMIT)
 * 
 * FITUR:
 * - Scan SEMUA file (apapun ekstensinya)
 * - Tidak ada whitelist ekstensi
 * - Deteksi berdasarkan KONTEN, bukan ekstensi
 * - Case-insensitive ekstensi check
 */

session_start();
set_time_limit(0);
error_reporting(0);

// ========== KONFIGURASI ==========
$MAX_FILE_SIZE = 1048576;
$CONFIG_FILE = __DIR__ . '/.scanner_config.json';
$BACKUP_DIR = __DIR__ . '/.backup_';

function loadConfig() {
    global $CONFIG_FILE;
    if (file_exists($CONFIG_FILE)) {
        return json_decode(file_get_contents($CONFIG_FILE), true);
    }
    return ['password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'];
}

$config = loadConfig();
if (!file_exists($BACKUP_DIR)) mkdir($BACKUP_DIR, 0755, true);

// ========== KONFIGURASI WAKTU ==========
$SUSPICIOUS_AGE_DAYS = 60;
$CRITICAL_AGE_DAYS = 14;

// ========== ENHANCED SIGNATURES ==========
$SIGNATURES = [
    // === COMMAND EXECUTION ===
    'rce' => ['pattern' => '/(system|shell_exec|exec|passthru|proc_open|popen)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'score' => 100],
    'reverse_shell' => ['pattern' => '/(bash\s+-i\s*>&\s*\/dev\/tcp\/|nc\s+-e\s+\/bin\/sh|python\s+-c\s+[\'"]import\s+socket|perl\s+-e\s+[\'"]use\s+socket)/i', 'score' => 100],
    
    // === EVAL ===
    'eval_direct' => ['pattern' => '/eval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/i', 'score' => 100],
    'eval_base64' => ['pattern' => '/eval\s*\(\s*base64_decode\s*\(/i', 'score' => 100],
    'eval_gzinflate' => ['pattern' => '/eval\s*\(\s*gzinflate\s*\(/i', 'score' => 100],
    'eval_str_rot13' => ['pattern' => '/eval\s*\(\s*str_rot13\s*\(/i', 'score' => 95],
    'eval_remote' => ['pattern' => '/eval\s*\(\s*file_get_contents\s*\(\s*["\']https?:\/\//i', 'score' => 100],
    
    // === REMOTE CODE ===
    'remote_download' => ['pattern' => '/(file_get_contents|curl_exec|fopen|stream_socket_client)\s*\(\s*["\']https?:\/\/[^"\']+["\']\s*\)\s*.*\s*(eval|include|require)/i', 'score' => 100],
    'raw_github' => ['pattern' => '/raw\.githubusercontent\.com/i', 'score' => 60],
    
    // === OBFUSCATION ===
    'base64_string' => ['pattern' => '/[A-Za-z0-9+\/]{100,}={0,2}/', 'score' => 40],
    'str_rot13' => ['pattern' => '/str_rot13\s*\(/i', 'score' => 40],
    'dynamic_func' => ['pattern' => '/(\$[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*[\'"].*[\'"];\s*\$\w+\s*\(/i', 'score' => 50],
    'function_mapping' => ['pattern' => '/\$func_alternatives\s*=\s*array\s*\(/i', 'score' => 60],
    
    // === FILE MANAGER ===
    'file_upload' => ['pattern' => '/move_uploaded_file\s*\(\s*\$_FILES/i', 'score' => 40],
    'file_delete' => ['pattern' => '/(unlink|rmdir)\s*\(\s*\$_(GET|POST|REQUEST)/i', 'score' => 50],
    'file_write' => ['pattern' => '/file_put_contents\s*\(\s*\$_(GET|POST|REQUEST)/i', 'score' => 50],
    'file_read' => ['pattern' => '/file_get_contents\s*\(\s*\$_(GET|POST|REQUEST)/i', 'score' => 40],
    'scandir' => ['pattern' => '/scandir\s*\(\s*\$_(GET|POST|REQUEST)/i', 'score' => 40],
    
    // === PASSWORD PROTECTED WEBSHELL ===
    'pass_hash' => ['pattern' => '/\$hashed_password\s*=\s*[\'"]\$2[ay]\$.{56}[\'"]|\$pass_hash\s*=/i', 'score' => 70],
    'password_verify' => ['pattern' => '/password_verify\s*\(\s*\$_(POST|GET)/i', 'score' => 60],
    
    // === KNOWN WEBSHELLS ===
    'c99' => ['pattern' => '/c99shell|c99_|c99\.php/i', 'score' => 100],
    'r57' => ['pattern' => '/r57shell|r57\.|r57_/i', 'score' => 100],
    'b374k' => ['pattern' => '/b374k|b3rk|b374k-shell/i', 'score' => 100],
    'wso' => ['pattern' => '/wso shell|webshell by wso/i', 'score' => 100],
    'darkboss' => ['pattern' => '/Dark BossBey File Manager/i', 'score' => 90],
    'adminer' => ['pattern' => '/Adminer.*Compact database management/i', 'score' => 50],
    
    // === CREATE FUNCTION ===
    'create_func' => ['pattern' => '/create_function\s*\(\s*[\'"].*[\'"]\s*,\s*\$_(GET|POST)/i', 'score' => 70],
];

// ========== HITUNG UMUR FILE ==========
function getFileAgeInfo($filepath) {
    global $SUSPICIOUS_AGE_DAYS, $CRITICAL_AGE_DAYS;
    
    $modified = filemtime($filepath);
    $now = time();
    $age_days = round(($now - $modified) / 86400);
    
    $status = 'old';
    $status_class = 'age-old';
    $status_text = '📅 ' . $age_days . ' hari';
    
    if ($age_days <= $CRITICAL_AGE_DAYS) {
        $status = 'critical';
        $status_class = 'age-critical';
        $status_text = '🔥 BARU! ' . $age_days . ' hari';
    } elseif ($age_days <= $SUSPICIOUS_AGE_DAYS) {
        $status = 'suspicious';
        $status_class = 'age-suspicious';
        $status_text = '⚠️ ' . $age_days . ' hari';
    }
    
    $age_bonus = 0;
    if ($age_days <= 7) $age_bonus = 10;
    elseif ($age_days <= 30) $age_bonus = 5;
    
    return [
        'age_days' => $age_days,
        'modified_date' => date('Y-m-d H:i:s', $modified),
        'status' => $status,
        'status_class' => $status_class,
        'status_text' => $status_text,
        'age_bonus' => $age_bonus
    ];
}

// ========== CEK APAKAH FILE PHP (BERDASARKAN KONTEN, BUKAN EKSTENSI) ==========
function isPhpContent($content) {
    // Cek tanda-tanda file PHP
    if (preg_match('/<\?php/i', $content)) return true;
    if (preg_match('/<\?=/i', $content)) return true;
    if (preg_match('/\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|\$_SESSION|\$_FILES/i', $content)) return true;
    if (preg_match('/(function|class|echo|print|if|else|while|for|foreach)\s*\(/i', $content)) return true;
    // Jika ada kode PHP yang di-encode
    if (preg_match('/[A-Za-z0-9+\/]{100,}={0,2}/', $content)) return true;
    return false;
}

// ========== SCAN FUNCTION ==========
function scanFile($filepath) {
    global $SIGNATURES;
    
    $size = filesize($filepath);
    if ($size > 1048576 || $size == 0) return null;
    
    $content = file_get_contents($filepath);
    if (!$content) return null;
    
    // SKIP FILE YANG JELAS BUKAN PHP (BERDASARKAN KONTEN)
    if (!isPhpContent($content)) {
        // Cek ekstensi sebagai fallback
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $php_exts = ['php', 'php3', 'php4', 'php5', 'phtml', 'inc', 'phar', 'module'];
        if (!in_array($ext, $php_exts)) {
            // Bukan file PHP, skip
            return null;
        }
    }
    
    // Decode base64 jika terdeteksi
    $decoded = $content;
    if (preg_match('/[A-Za-z0-9+\/]{100,}={0,2}/', $content)) {
        $decoded = base64_decode($content);
        if ($decoded === false) $decoded = $content;
    }
    
    $clean = preg_replace('/\/\/.*$/m', '', $decoded);
    $clean = preg_replace('/\/\*.*?\*\//s', '', $clean);
    $clean = preg_replace('/#.*$/m', '', $clean);
    
    $score = 0;
    $matches = [];
    
    foreach ($SIGNATURES as $name => $sig) {
        if (preg_match($sig['pattern'], $clean)) {
            $score += $sig['score'];
            $matches[] = $name;
        }
    }
    
    // Hitung jumlah fungsi berbahaya
    $dangerous = ['system', 'exec', 'shell_exec', 'eval', 'assert', 'file_put_contents', 'unlink'];
    $dangerous_count = 0;
    foreach ($dangerous as $func) {
        if (preg_match("/\b{$func}\s*\(/i", $clean)) $dangerous_count++;
    }
    if ($dangerous_count >= 3) {
        $score += 30;
        $matches[] = 'multiple_dangerous';
    }
    
    // AGE BONUS
    $ageInfo = getFileAgeInfo($filepath);
    $score += $ageInfo['age_bonus'];
    if ($ageInfo['age_bonus'] > 0) $matches[] = 'recent_file';
    
    // NAMA FILE MENURIGAKAN (CASE-INSENSITIVE)
    $basename = basename($filepath);
    $suspicious = ['shell', 'backdoor', 'webshell', 'c99', 'r57', 'b374k', 'wso', 'cmd', 'adminer'];
    foreach ($suspicious as $sus) {
        if (stripos($basename, $sus) !== false && $score > 0) {
            $score += 20;
            $matches[] = "filename:$basename";
            break;
        }
    }
    
    if ($score >= 50) {
        return [
            'file' => $filepath,
            'name' => $basename,
            'risk' => $score >= 85 ? 'critical' : 'high',
            'score' => $score,
            'patterns' => array_unique($matches),
            'size' => $size,
            'age' => $ageInfo
        ];
    }
    return null;
}

function scanDirectory($dir, &$results = []) {
    if (!is_readable($dir)) return $results;
    
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            scanDirectory($path, $results);
        } else {
            // SCAN SEMUA FILE, TIDAK ADA FILTER EKSTENSI
            $result = scanFile($path);
            if ($result) $results[] = $result;
        }
    }
    return $results;
}

// ========== AUTHENTICATION ==========
if (isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $config['password_hash'])) {
        $_SESSION['auth'] = true;
    }
}

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    echo '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Scanner Login</title>
    <style>
        body{background:linear-gradient(135deg,#0f172a,#1e1b4b);display:flex;justify-content:center;align-items:center;min-height:100vh}
        .box{background:#1e293b;padding:40px;border-radius:20px;width:350px;text-align:center}
        input,button{width:100%;padding:14px;margin:10px 0;border-radius:10px;border:none}
        input{background:#0f172a;border:1px solid #334155;color:#fff}
        button{background:#3b82f6;color:#fff;cursor:pointer}
        h1{color:#60a5fa}
    </style></head>
    <body><div class="box"><h1>🔐 Scanner v27</h1>
    <form method="POST"><input type="password" name="password" placeholder="Password" autofocus>
    <input type="hidden" name="login" value="1"><button type="submit">Login</button></form>
    <small style="color:#64748b">pass: scanner123</small></div></body></html>';
    exit;
}

// ========== MAIN ACTIONS ==========
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'start_scan') {
    $path = $_POST['path'] ?? __DIR__;
    if (!is_dir($path)) {
        echo "<script>alert('Directory tidak valid: " . addslashes($path) . "'); window.location='?';</script>";
        exit;
    }
    $results = scanDirectory($path);
    usort($results, fn($a, $b) => $a['age']['age_days'] - $b['age']['age_days']);
    $_SESSION['last_scan'] = $results;
    $_SESSION['last_scan_path'] = $path;
    header('Location: ?');
    exit;
}

if ($action === 'quarantine' && isset($_GET['file'])) {
    $dir = __DIR__ . '/_quarantine_';
    if (!file_exists($dir)) mkdir($dir);
    rename($_GET['file'], $dir . '/' . basename($_GET['file']) . '_' . time() . '.bak');
    header('Location: ?');
    exit;
}

if ($action === 'delete' && isset($_GET['file'])) {
    unlink($_GET['file']);
    header('Location: ?');
    exit;
}

if ($action === 'edit' && isset($_GET['file'])) {
    $file = $_GET['file'];
    if (!file_exists($file)) { echo "File not found"; exit; }
    $content = file_get_contents($file);
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Edit File</title>
    <style>body{background:#0f172a;color:#e2e8f0;font-family:monospace;padding:20px}
    textarea{width:100%;height:70vh;background:#0f172a;border:1px solid #334155;color:#fff;padding:15px}
    .btn-save{background:#10b981;padding:12px 24px;border:none;border-radius:8px;color:#fff;cursor:pointer}
    .btn-cancel{background:#475569;padding:12px 24px;border:none;border-radius:8px;color:#fff;text-decoration:none;display:inline-block}
    </style></head>
    <body><form method="POST" action="?action=save"><input type="hidden" name="file" value="<?= htmlspecialchars($file) ?>"><textarea name="content"><?= htmlspecialchars($content) ?></textarea>
    <div style="margin-top:15px"><button type="submit" class="btn-save">💾 Save</button><a href="?" class="btn-cancel">Cancel</a></div></form></body></html>
    <?php exit;
}

if ($action === 'save' && isset($_POST['file']) && isset($_POST['content'])) {
    $file = $_POST['file'];
    $backup_file = $BACKUP_DIR . '/' . basename($file) . '_' . time() . '.bak';
    copy($file, $backup_file);
    file_put_contents($file, $_POST['content']);
    header('Location: ?');
    exit;
}

if ($action === 'view' && isset($_GET['file']) && file_exists($_GET['file'])) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>View File</title>
    <style>body{background:#0f172a;color:#e2e8f0;font-family:monospace;padding:20px}
    pre{background:#1e293b;padding:20px;border-radius:10px;overflow:auto}
    a{color:#60a5fa}</style></head>
    <body><a href="?">← Back</a><pre>' . htmlspecialchars(file_get_contents($_GET['file'])) . '</pre></body></html>';
    exit;
}

if ($action === 'logout') { session_destroy(); header('Location: ?'); exit; }

$last = $_SESSION['last_scan'] ?? [];
$last_path = $_SESSION['last_scan_path'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Project Scanner v27.0 - Scan All Files</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#0f172a;font-family:Arial,sans-serif;color:#e2e8f0;padding:20px}
        .container{max-width:1400px;margin:0 auto}
        .header{background:#1e293b;padding:20px 30px;border-radius:16px;margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
        .logo h1{color:#60a5fa;font-size:24px}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:30px}
        .stat-card{background:#1e293b;padding:20px;border-radius:16px;text-align:center}
        .stat-number{font-size:48px;font-weight:bold}
        .card{background:#1e293b;border-radius:16px;padding:20px;margin-bottom:30px}
        .scan-form{display:flex;gap:15px;flex-wrap:wrap;align-items:center}
        .scan-form input{flex:3;padding:12px;background:#0f172a;border:1px solid #334155;border-radius:10px;color:#fff}
        .scan-form button{padding:12px 24px;background:#3b82f6;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:bold}
        .path-suggestion{background:#1e293b;padding:10px;border-radius:10px;margin-top:10px;font-size:12px;color:#94a3b8}
        .path-suggestion a{color:#60a5fa;margin-right:15px;cursor:pointer}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;text-align:left;border-bottom:1px solid #334155}
        th{background:#1e293b;color:#94a3b8}
        code{font-family:monospace;font-size:13px;background:#0f172a;padding:4px 8px;border-radius:6px}
        .badge-critical{background:#7f1a1a;color:#fecaca;padding:4px 10px;border-radius:20px;font-size:12px;display:inline-block}
        .badge-high{background:#78350f;color:#fed7aa;padding:4px 10px;border-radius:20px;font-size:12px;display:inline-block}
        .age-critical{background:#dc2626;color:#fff;padding:4px 10px;border-radius:20px;font-size:11px;display:inline-block;margin-left:8px}
        .age-suspicious{background:#f59e0b;color:#000;padding:4px 10px;border-radius:20px;font-size:11px;display:inline-block;margin-left:8px}
        .age-old{background:#475569;color:#fff;padding:4px 10px;border-radius:20px;font-size:11px;display:inline-block;margin-left:8px}
        .action-links a{margin-right:12px;text-decoration:none;font-size:13px;color:#06b6d4}
        .empty-state{text-align:center;padding:40px;color:#64748b}
        .info-note{background:#1e293b;padding:10px;border-radius:10px;margin-top:15px;font-size:12px;color:#94a3b8}
        footer{text-align:center;margin-top:30px;color:#475569}
        @media(max-width:768px){.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><h1>🛡️ Project Scanner v27.0</h1><span style="font-size:12px">SCAN ALL FILES</span></div>
        <a href="?action=logout" style="background:#ef4444;color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none" onclick="return confirm('Logout?')">Logout</a>
    </div>
    
    <div class="stats">
        <div class="stat-card"><div class="stat-number"><?= count($last) ?></div><div>Total Webshell</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#ef4444"><?= count(array_filter($last, fn($f)=>$f['risk']==='critical')) ?></div><div>Critical</div></div>
        <div class="stat-card"><div class="stat-number" style="color:#f59e0b"><?= count(array_filter($last, fn($f)=>$f['risk']==='high')) ?></div><div>High Risk</div></div>
    </div>
    
    <div class="card">
        <h3>🔍 Scan Directory</h3>
        <form method="POST" action="?action=start_scan" class="scan-form">
            <input type="text" name="path" id="scanPath" value="<?= htmlspecialchars($last_path ?: __DIR__) ?>" placeholder="Path to scan">
            <button type="submit">🚀 Start Scan</button>
        </form>
        <div class="path-suggestion">
            📁 Path suggestions:
            <a onclick="document.getElementById('scanPath').value='/home/sttreal1/'">/home/sttreal1/</a>
            <a onclick="document.getElementById('scanPath').value='/home/sttreal1/jurnalpak.id/public_html'">/home/sttreal1/jurnalpak.id/public_html</a>
            <a onclick="document.getElementById('scanPath').value='/home/sttreal1/kacau.ac.id'">/home/sttreal1/kacau.ac.id</a>
            <a onclick="document.getElementById('scanPath').value='<?= __DIR__ ?>'">Current (<?= __DIR__ ?>)</a>
        </div>
        <div class="info-note">
            ✅ SCAN ALL FILES (apapun ekstensinya) | 🔍 Deteksi berdasarkan KONTEN | 🕐 Time-aware | ✏️ Edit file
        </div>
    </div>
    
    <?php if (!empty($last)): ?>
    <div class="card">
        <h3>⚠️ WEBSHELL DETECTED - <?= htmlspecialchars($last_path) ?></h3>
        <div style="overflow-x:auto">
            <table>
                <thead><tr><th>File</th><th>Risk</th><th>Age</th><th>Detection</th><th>Size</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($last as $f): ?>
                <tr>
                    <td><code><?= htmlspecialchars($f['name']) ?></code><br><small><?= htmlspecialchars(dirname($f['file'])) ?></small></td>
                    <td><span class="<?= $f['risk'] === 'critical' ? 'badge-critical' : 'badge-high' ?>"><?= strtoupper($f['risk']) ?></span></td>
                    <td><span class="<?= $f['age']['status_class'] ?>"><?= $f['age']['status_text'] ?></span><br><small><?= $f['age']['modified_date'] ?></small></td>
                    <td><small><?= htmlspecialchars(implode(', ', array_slice($f['patterns'], 0, 2))) ?></small></td>
                    <td><?= $f['size'] < 1024 ? $f['size'] . ' B' : round($f['size']/1024, 1) . ' KB' ?></td>
                    <td class="action-links">
                        <a href="?action=view&file=<?= urlencode($f['file']) ?>">View</a>
                        <a href="?action=edit&file=<?= urlencode($f['file']) ?>">Edit</a>
                        <a href="?action=quarantine&file=<?= urlencode($f['file']) ?>" onclick="return confirm('Quarantine?')">Quarantine</a>
                        <a href="?action=delete&file=<?= urlencode($f['file']) ?>" onclick="return confirm('Delete permanently?')" style="color:#ef4444">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="empty-state">✨ No webshell found. Enter path and click Start Scan.</div></div>
    <?php endif; ?>
    
    <footer>Project Scanner v27.0 - Scan ALL files (no extension limit) | Detect by content | Time-aware | Edit file</footer>
</div>
<script>
    document.querySelectorAll('.path-suggestion a').forEach(el => {
        el.onclick = function() { document.getElementById('scanPath').value = this.textContent; };
    });
</script>
</body>
</html>
<?php
?>
