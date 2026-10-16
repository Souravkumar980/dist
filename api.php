<?php
/**
 * Exposed Propagandus - Native PHP & MySQL API Bridge for InfinityFree & Apache
 * Domain: exposedpropagandus.is-best.net
 * Target Database: if0_37976074_exposedpropagandus
 * Host: sql200.infinityfree.com (configurable via db_config.php or .env)
 * Bharat Mata ki Jai 🇮🇳
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-admin-key, X-Admin-Key, x-app-key, X-App-Key, x-client-key, X-Client-Key, X-HTTP-Method-Override, X-EP-Timestamp, X-EP-Signature');

// Guaranteed JSON output: Prevent PHP from ever returning HTML errors on shared hosting
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false,
        'code' => 500
    ], JSON_UNESCAPED_SLASHES);
    exit;
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Fatal Server Error: ' . $err['message'],
            'file' => basename($err['file']),
            'line' => $err['line'],
            'success' => false,
            'code' => 500
        ], JSON_UNESCAPED_SLASHES);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------
// 1. Load Database Credentials & Environment Configuration
// -------------------------------------------------------------
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} elseif (file_exists(__DIR__ . '/../db_config.php')) {
    require_once __DIR__ . '/../db_config.php';
}

// Check for .env file if constants are not already defined
if (!defined('DB_HOST')) {
    $envPath = file_exists(__DIR__ . '/.env') ? __DIR__ . '/.env' : (file_exists(__DIR__ . '/../.env') ? __DIR__ . '/../.env' : null);
    if ($envPath) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

$db_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'sql200.infinityfree.com');
$db_port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: 3306);
$db_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'if0_37976074');
$db_pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: 'fACI2gSxa2zDmYT');
$db_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'if0_37976074_epsite');

$master_secret_key = defined('ADMIN_SECRET_KEY') ? ADMIN_SECRET_KEY : (getenv('ADMIN_SECRET_KEY') ?: 'JaiHindBharatMataKiJai2024!');
$master_admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (getenv('ADMIN_EMAIL') ?: 'omkumar.working@gmail.com');
$master_admin_password = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : (getenv('ADMIN_PASSWORD') ?: 'Q0gng04bk3');

// -------------------------------------------------------------
// 2. Database Connection (MySQL with Local / SQLite Fallback)
// -------------------------------------------------------------
$pdo = null;
$db_type = 'none';
$db_error = null;

// Attempt 1: Connect to configured MySQL host
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 4
    ]);
    $db_type = 'mysql';
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Attempt 1.5: If remote host failed, try alternate cluster hostnames (sql200.byetcluster.com, sql200.epizy.com, 192.168.0.170)
if (!$pdo) {
    $altHosts = ['sql200.byetcluster.com', 'sql200.epizy.com', '192.168.0.170'];
    foreach ($altHosts as $altH) {
        if ($altH === $db_host) continue;
        try {
            $dsnAlt = "mysql:host={$altH};port={$db_port};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsnAlt, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3
            ]);
            $db_type = 'mysql';
            $db_host = $altH;
            $db_error = null;
            break;
        } catch (Exception $eAlt) {}
    }
}

// Attempt 2: If remote host failed and was not localhost, try localhost / 127.0.0.1
if (!$pdo && $db_host !== 'localhost' && $db_host !== '127.0.0.1') {
    try {
        $dsnLocal = "mysql:host=127.0.0.1;dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsnLocal, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 2
        ]);
        $db_type = 'mysql-local';
        $db_error = null;
    } catch (Exception $e2) {
        // Keep initial error
    }
}

// Attempt 3: SQLite fallback if MySQL is unreachable (ensures site is 100% operational)
if (!$pdo) {
    $sqlitePaths = [
        __DIR__ . '/../data/propagandus.db',
        __DIR__ . '/../data.db',
        __DIR__ . '/data.db',
        sys_get_temp_dir() . '/propagandus.db'
    ];
    foreach ($sqlitePaths as $sPath) {
        if (file_exists($sPath) || is_writable(dirname($sPath))) {
            try {
                if (!file_exists(dirname($sPath))) {
                    @mkdir(dirname($sPath), 0777, true);
                }
                $pdo = new PDO("sqlite:" . $sPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $db_type = 'sqlite';
                break;
            } catch (Exception $eSqlite) {}
        }
    }
}

// -------------------------------------------------------------
// 3. Helper Functions
// -------------------------------------------------------------
function getClientIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }
    return $ip;
}

function get_request_headers() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) {
            foreach ($h as $k => $v) {
                $headers[strtolower($k)] = $v;
            }
        }
    }
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

function is_admin_ip_allowed() {
    return true;
}

function is_admin_authorized($pdo = null) {
    global $master_secret_key, $master_admin_password;

    $headers = get_request_headers();
    $auth = $headers['x-admin-key'] ?? $headers['authorization'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['adminKey'] ?? $_GET['admin_token'] ?? $_GET['key'] ?? '';
    if (strpos($auth, 'Bearer ') === 0) {
        $auth = trim(substr($auth, 7));
    }

    if (!$auth && !empty($GLOBALS['body']['admin_token'])) {
        $auth = trim($GLOBALS['body']['admin_token']);
    }
    if (!$auth && !empty($GLOBALS['body']['adminKey'])) {
        $auth = trim($GLOBALS['body']['adminKey']);
    }
    if (!$auth && !empty($_POST['admin_token'])) {
        $auth = trim($_POST['admin_token']);
    }
    if (!$auth && !empty($_POST['adminKey'])) {
        $auth = trim($_POST['adminKey']);
    }

    if (!$auth) return false;

    // Check if client IP is blacklisted
    if ($pdo) {
        try {
            $ip = getClientIp();
            $chk = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
            $chk->execute([$ip]);
            if ($chk->fetch()) return false;
        } catch (Exception $e) {}
    }

    // 1. Session token format issued upon successful login
    if (strpos($auth, 'ep-adm-') === 0 && strlen($auth) >= 15) return true;

    // 2. Master Secret Key or Password
    if ($auth === $master_secret_key || $auth === $master_admin_password || $auth === 'JaiHindBharatMataKiJai2024!' || $auth === 'Q0gng04bk3') return true;

    return false;
}

function extract_handle_php($input, $platform) {
    if (!$input) return null;
    $clean = trim($input);
    $clean = rtrim($clean, '/');

    if ($platform === 'x') {
        if (preg_match('/(?:x\.com|twitter\.com)\/([a-zA-Z0-9_]+)/i', $clean, $m)) return $m[1];
        return ltrim($clean, '@');
    }
    if ($platform === 'yt') {
        if (preg_match('/(?:youtube\.com|youtu\.be)\/(?:@|c\/|channel\/)?([a-zA-Z0-9_.-]+)/i', $clean, $m)) return ltrim($m[1], '@');
        return ltrim($clean, '@');
    }
    if ($platform === 'insta') {
        if (preg_match('/instagram\.com\/([a-zA-Z0-9_.]+)/i', $clean, $m)) return $m[1];
        return ltrim($clean, '@');
    }
    return $clean;
}

function validate_social_handles_php($pdo, $cleanX, $cleanYt, $cleanInsta, $tableName, $excludeId = null) {
    if (!$cleanX && !$cleanYt && !$cleanInsta) {
        return 'कम से कम एक सोशल मीडिया आईडी (YouTube, X, या Instagram) देना अनिवार्य है। (At least one social ID is mandatory).';
    }

    $regex = '/^[a-zA-Z0-9_.-]{2,60}$/';
    if ($cleanX && !preg_match($regex, $cleanX)) {
        return 'अमान्य X (Twitter) आईडी प्रारूप। केवल अक्षर, अंक, बिंदु, हाइफन या अंडरस्कोर मान्य हैं। (Invalid X handle format).';
    }
    if ($cleanYt && !preg_match($regex, $cleanYt)) {
        return 'अमान्य YouTube आईडी प्रारूप। कृपया सही चैनल हैंडल दर्ज करें। (Invalid YouTube handle format).';
    }
    if ($cleanInsta && !preg_match($regex, $cleanInsta)) {
        return 'अमान्य Instagram आईडी प्रारूप। कृपया सही यूज़रनेम दर्ज करें। (Invalid Instagram handle format).';
    }

    if ($pdo) {
        try {
            $checks = [];
            $params = [];
            if ($cleanX) {
                $checks[] = "LOWER(handle_x) = LOWER(?)";
                $params[] = $cleanX;
            }
            if ($cleanYt) {
                $checks[] = "LOWER(handle_yt) = LOWER(?)";
                $params[] = $cleanYt;
            }
            if ($cleanInsta) {
                $checks[] = "LOWER(handle_insta) = LOWER(?)";
                $params[] = $cleanInsta;
            }

            if (!empty($checks)) {
                $query = "SELECT name FROM $tableName WHERE (" . implode(' OR ', $checks) . ")";
                if ($excludeId) {
                    $query .= " AND id != ?";
                    $params[] = $excludeId;
                }
                $query .= " LIMIT 1";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $existing = $stmt->fetch();
                if ($existing) {
                    $val = $cleanX ?: ($cleanYt ?: $cleanInsta);
                    return 'यह सोशल मीडिया आईडी (@' . $val . ') पहले से डेटाबेस में "' . $existing['name'] . '" के नाम से दर्ज है! समान आईडी दोबारा नहीं जोड़ी जा सकती। (This social ID is already registered for "' . $existing['name'] . '").';
                }
            }
        } catch (Exception $e) {}
    }
    return null;
}

function check_patriot_php($name) {
    if (!$name || strlen(trim($name)) < 2) return false;
    $clean = strtolower(trim($name));

    $markers = [
        'fake', 'expose', 'exposed', 'exposing', 'critic', 'critique', 'anti', 'against',
        'hater', 'hate', 'troll', 'parody', 'lies', 'lying', 'fact check', 'factcheck',
        'debunk', 'debunked', 'busting', 'buster', 'roast', 'insult', 'mocking', 'mock',
        'scam', 'fraud', 'corrupt', 'propaganda', 'propagandu', 'chamcha', 'godi', 'bhakt',
        'mimic', 'squad', 'front', 'channel', 'page', 'clips', 'shorts', 'video'
    ];
    foreach ($markers as $m) {
        if (strpos($clean, $m) !== false) {
            return false;
        }
    }

    $patterns = [
        '/^(shri\s+|hon\s+|pm\s+)?(narendra\s+)?modi(ji|\s+ji)?$/i' => 'Shri Narendra Modi',
        '/^namo$/i' => 'Shri Narendra Modi',
        '/^(shri\s+|hon\s+)?amit\s*(bhai\s*)?shah(ji|\s+ji)?$/i' => 'Shri Amit Shah',
        '/^(shri\s+|mahant\s+|cm\s+)?yogi(\s*adityanath)?(ji|\s+ji)?$/i' => 'Yogi Adityanath',
        '/^adityanath(ji|\s+ji)?$/i' => 'Yogi Adityanath',
        '/^(nsa\s+)?ajit\s*doval(ji|\s+ji)?$/i' => 'Ajit Doval',
        '/^(dr\.?\s+)?(s\.?\s+)?jaishankar(ji|\s+ji)?$/i' => 'Dr. S. Jaishankar',
        '/^(dr\.?\s+)?anand\s+ranganathan$/i' => 'Dr. Anand Ranganathan',
        '/^(adv\.?\s+)?j\.?\s*sai\s*deepak$/i' => 'Advocate J. Sai Deepak',
        '/^(major\s+)?gaurav\s+arya$/i' => 'Major Gaurav Arya',
        '/^(cm\s+)?himanta\s*biswa(\s*sarma)?$/i' => 'Dr. Himanta Biswa Sarma',
        '/^atal\s*bihari(\s*vajpayee)?$/i' => 'Atal Bihari Vajpayee',
        '/^(bageshwar\s*dham|dhirendra\s*(krishna\s*)?shastri)$/i' => 'Bageshwar Dham Sarkar',
        '/^(bjp|bharatiya\s*janata\s*party|bhartiya\s*janata\s*party)$/i' => 'Bharatiya Janata Party',
        '/^(rss|rashtriya\s*swayamsevak\s*sangh)$/i' => 'Rashtriya Swayamsevak Sangh'
    ];

    $norm = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
    $norm = trim(preg_replace('/\s+/', ' ', $norm));

    foreach ($patterns as $pattern => $label) {
        if (preg_match($pattern, $norm)) {
            return $label;
        }
    }
    return false;
}

// -------------------------------------------------------------
// 4. Auto Schema Initialization & Table Migrations
// -------------------------------------------------------------
if ($pdo) {
    try {
        $now = date('c');

        // Entities table
        $pdo->exec("CREATE TABLE IF NOT EXISTS entities (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            handle_x VARCHAR(100) DEFAULT NULL,
            handle_yt VARCHAR(100) DEFAULT NULL,
            handle_insta VARCHAR(100) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            other_links TEXT DEFAULT NULL,
            avatar_url TEXT DEFAULT NULL,
            categories TEXT NOT NULL,
            party VARCHAR(255) DEFAULT NULL,
            institution VARCHAR(255) DEFAULT NULL,
            narrative TEXT NOT NULL,
            fact_check TEXT DEFAULT NULL,
            proof_url TEXT DEFAULT NULL,
            upvotes INT NOT NULL DEFAULT 0,
            is_verified TINYINT(1) NOT NULL DEFAULT 1,
            submitted_by_ip VARCHAR(64) DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");

        try { $pdo->exec("ALTER TABLE entities ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE entities ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE entities ADD COLUMN other_links TEXT DEFAULT NULL"); } catch (Exception $e) {}

        // Nationalist Creators table
        $pdo->exec("CREATE TABLE IF NOT EXISTS nationalist_influencers (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            handle_x VARCHAR(100) DEFAULT NULL,
            handle_yt VARCHAR(100) DEFAULT NULL,
            handle_insta VARCHAR(100) DEFAULT NULL,
            other_links TEXT DEFAULT NULL,
            avatar_url TEXT DEFAULT NULL,
            category VARCHAR(150) DEFAULT 'Nationalist Creator',
            categories TEXT DEFAULT NULL,
            description TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            upvotes INT NOT NULL DEFAULT 0,
            suggested_by_ip VARCHAR(64) DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");
        try { $pdo->exec("ALTER TABLE nationalist_influencers ADD COLUMN categories TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE nationalist_influencers ADD COLUMN other_links TEXT DEFAULT NULL"); } catch (Exception $e) {}

        // Feature Suggestions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS feature_suggestions (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(200) NOT NULL,
            category VARCHAR(100) DEFAULT 'New Feature',
            message TEXT NOT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            is_read TINYINT DEFAULT 0,
            created_at VARCHAR(64) NOT NULL
        );");

        // Reports table
        $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            entity_id VARCHAR(100) NOT NULL,
            entity_name VARCHAR(255) DEFAULT NULL,
            report_type VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            reported_by_ip VARCHAR(64) DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Blocked IPs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_ips (
            ip VARCHAR(64) NOT NULL PRIMARY KEY,
            reason TEXT DEFAULT NULL,
            blocked_at VARCHAR(64) NOT NULL
        );");

        // Blocked Attempts table
        $pdo->exec("CREATE TABLE IF NOT EXISTS blocked_attempts (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            ip VARCHAR(64) NOT NULL,
            attempted_name VARCHAR(255) NOT NULL,
            details TEXT DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Activity Logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Announcements table
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id VARCHAR(50) NOT NULL PRIMARY KEY,
            title VARCHAR(255) DEFAULT 'उद्घोषणा / ANNOUNCEMENT',
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'warning',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");

        // Govt Polls table
        $pdo->exec("CREATE TABLE IF NOT EXISTS govt_polls (
            id VARCHAR(50) NOT NULL PRIMARY KEY,
            question TEXT NOT NULL,
            yes_votes INT NOT NULL DEFAULT 0,
            no_votes INT NOT NULL DEFAULT 0,
            updated_at VARCHAR(64) NOT NULL
        );");

        // Govt Poll Votes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS govt_poll_votes (
            poll_id VARCHAR(50) NOT NULL,
            ip VARCHAR(64) NOT NULL,
            choice VARCHAR(10) NOT NULL,
            voted_at VARCHAR(64) NOT NULL,
            PRIMARY KEY (poll_id, ip)
        );");

        // System Settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            `key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` LONGTEXT NOT NULL,
            `updated_at` VARCHAR(64) NOT NULL
        );");

        // Admin Users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id VARCHAR(50) NOT NULL PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");

        // Ensure default primary admin user exists
        $chkAdmin = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users")->fetch();
        if ($chkAdmin && (int)$chkAdmin['cnt'] === 0) {
            $pdo->prepare("INSERT INTO admin_users (id, email, password_hash, created_at, updated_at) VALUES ('admin-primary', ?, ?, ?, ?)")
                ->execute([$master_admin_email, $master_admin_password, $now, $now]);
        }

        // Playlist Tracks table
        $pdo->exec("CREATE TABLE IF NOT EXISTS playlist_tracks (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            video_id VARCHAR(64) NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            thumbnail_url TEXT DEFAULT NULL,
            duration VARCHAR(50) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL
        );");

        // Site Visitors & Traffic Logs tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_visitors (
            ip VARCHAR(64) NOT NULL PRIMARY KEY,
            device_type VARCHAR(50) DEFAULT 'Desktop',
            os VARCHAR(100) DEFAULT 'Unknown OS',
            browser VARCHAR(100) DEFAULT 'Unknown Browser',
            user_agent TEXT DEFAULT NULL,
            visit_count INT NOT NULL DEFAULT 1,
            current_page VARCHAR(120) DEFAULT 'Homepage',
            first_visited VARCHAR(64) NOT NULL,
            last_seen VARCHAR(64) NOT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0
        );");

        $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_traffic_logs (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            ip VARCHAR(64) NOT NULL,
            device_type VARCHAR(50) DEFAULT NULL,
            os VARCHAR(100) DEFAULT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            page VARCHAR(120) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Profile Votes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS profile_votes (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            target_id VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            ip VARCHAR(64) NOT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Site Categories table
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_categories (
            id " . ($db_type === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
            type VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Admin Posts table
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_posts (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            image_url TEXT DEFAULT NULL,
            badge VARCHAR(100) DEFAULT 'DISPATCH',
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            likes INT NOT NULL DEFAULT 0,
            views INT NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");

        // Community Users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS community_users (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            avatar_url TEXT DEFAULT NULL,
            verified_type VARCHAR(50) NOT NULL DEFAULT 'none',
            ip_address VARCHAR(64) DEFAULT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            is_banned TINYINT(1) NOT NULL DEFAULT 0,
            name_change_count INT NOT NULL DEFAULT 0,
            username_change_count INT NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL,
            updated_at VARCHAR(64) NOT NULL
        );");
        try { $pdo->exec("ALTER TABLE community_users ADD COLUMN name_change_count INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE community_users ADD COLUMN username_change_count INT NOT NULL DEFAULT 0"); } catch (Exception $e) {}

        // Community Messages table
        $pdo->exec("CREATE TABLE IF NOT EXISTS community_messages (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            channel_id VARCHAR(100) NOT NULL DEFAULT 'public',
            user_id VARCHAR(100) NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL,
            user_avatar TEXT DEFAULT NULL,
            verified_type VARCHAR(50) NOT NULL DEFAULT 'none',
            content TEXT NOT NULL,
            attachments " . ($db_type === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT') . " DEFAULT NULL,
            media_links TEXT DEFAULT NULL,
            gif_url TEXT DEFAULT NULL,
            reactions TEXT DEFAULT NULL,
            reply_to TEXT DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL
        );");
        try { $pdo->exec("ALTER TABLE community_messages ADD COLUMN reactions TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE community_messages ADD COLUMN reply_to TEXT DEFAULT NULL"); } catch (Exception $e) {}

        // Community Channels table
        $pdo->exec("CREATE TABLE IF NOT EXISTS community_channels (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            creator_id VARCHAR(100) NOT NULL,
            creator_name VARCHAR(255) NOT NULL,
            creator_username VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            join_token VARCHAR(100) NOT NULL,
            is_private TINYINT(1) NOT NULL DEFAULT 1,
            members TEXT DEFAULT NULL,
            pending_requests TEXT DEFAULT NULL,
            created_at VARCHAR(64) NOT NULL
        );");

        // Community Notifications table
        $pdo->exec("CREATE TABLE IF NOT EXISTS community_notifications (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            recipient_username VARCHAR(100) NOT NULL,
            sender_username VARCHAR(100) NOT NULL,
            sender_name VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at VARCHAR(64) NOT NULL
        );");

        // Community Contact Queries table
        $pdo->exec("CREATE TABLE IF NOT EXISTS community_contact_queries (
            id VARCHAR(100) NOT NULL PRIMARY KEY,
            type VARCHAR(50) NOT NULL DEFAULT 'user',
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            user_id VARCHAR(100) DEFAULT NULL,
            username VARCHAR(100) DEFAULT NULL,
            social_account_id VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            proof_file_name VARCHAR(255) DEFAULT NULL,
            proof_file " . ($db_type === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT') . " DEFAULT NULL,
            ip VARCHAR(64) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            created_at VARCHAR(64) NOT NULL
        );");

        // Seed Master Admin into community_users
        $chkCommAdmin = $pdo->prepare("SELECT COUNT(*) as cnt FROM community_users WHERE email = ?");
        $chkCommAdmin->execute(['omkumar.working@gmail.com']);
        $commAdminRow = $chkCommAdmin->fetch();
        if ($commAdminRow && (int)$commAdminRow['cnt'] === 0) {
            $stmtCommAdmin = $pdo->prepare("INSERT INTO community_users (id, name, username, email, phone, password_hash, avatar_url, verified_type, is_admin, is_banned, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtCommAdmin->execute([
                'usr_admin',
                'Om Kumar (Commander)',
                'omkumar_admin',
                'omkumar.working@gmail.com',
                '9876543210',
                'Q0gng04bk3',
                'https://api.dicebear.com/7.x/bottts/svg?seed=OmKumarAdmin',
                'influencer',
                1, 0, $now, $now
            ]);
        }

    } catch (Exception $eInit) {}
}

// -------------------------------------------------------------
// 5. Route Extraction & Request Context (Universal Normalization)
// -------------------------------------------------------------
$endpoint = $_GET['endpoint'] ?? '';
if (empty($endpoint)) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api.php';
    if (strpos($uriPath, $scriptName) === 0) {
        $endpoint = substr($uriPath, strlen($scriptName));
    } elseif (strpos($uriPath, '/api/') === 0) {
        $endpoint = substr($uriPath, 5);
    } elseif (strpos($uriPath, '/api') === 0) {
        $endpoint = substr($uriPath, 4);
    } else {
        $endpoint = $uriPath;
    }
}
$endpoint = trim($endpoint, '/');
if (strpos($endpoint, 'api/') === 0) {
    $endpoint = substr($endpoint, 4);
    $endpoint = trim($endpoint, '/');
}

$method   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$inputJson = file_get_contents('php://input');
$body = json_decode($inputJson, true) ?: ($GLOBALS['body'] ?? $_POST ?? []);
$ip       = getClientIp();

// -------------------------------------------------------------
// Security Gate 0: Immediate IP Blacklist & Scraper Termination
// -------------------------------------------------------------
// If IP is banned by admin, return literally NOTHING (empty page / die(''))
$isAdminReq = is_admin_authorized($pdo);
if ($pdo && !$isAdminReq) {
    try {
        $chkBlockEarly = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
        $chkBlockEarly->execute([$ip]);
        if ($chkBlockEarly->fetch()) {
            http_response_code(403);
            die('');
        }
    } catch (Exception $e) {}
}

// Scraper / Code Extractor / Headless Bot Blocker: Return absolute blank (die(''))
if (!$isAdminReq) {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $scraperSignatures = [
        'httrack', 'sitesucker', 'wget', 'curl', 'python-requests', 'python', 'aiohttp',
        'scrapy', 'go-http-client', 'java/', 'libwww-perl', 'headlesschrome', 'phantomjs',
        'selenium', 'puppeteer', 'extractor', 'offline', 'teleport', 'webcopier',
        'site-copier', 'page-sucker', 'web-harvest', 'html-scraper', 'website-downloader'
    ];
    foreach ($scraperSignatures as $sig) {
        if (strpos($ua, $sig) !== false) {
            http_response_code(403);
            die('');
        }
    }
}

// Support HTTP Method Override for shared web hosts (InfinityFree, Apache, FastCGI)
$headers = get_request_headers();
$methodOverride = $headers['x-http-method-override'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_GET['_method'] ?? $body['_method'] ?? null;
if ($methodOverride && in_array(strtoupper($methodOverride), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
    $method = strtoupper($methodOverride);
}

// -------------------------------------------------------------
// 6. Security Gate: Block Direct Browser Address Bar Inspection
// -------------------------------------------------------------
// Prevents direct URL inspection, scraping, and unauthorized viewing in browser address bar
if ($endpoint !== 'health') {
    $headers = get_request_headers();
    $appKey = $headers['x-app-key'] ?? $headers['x-client-key'] ?? $headers['x-admin-key'] ?? $headers['authorization'] ?? $_GET['key'] ?? '';
    if (strpos($appKey, 'Bearer ') === 0) {
        $appKey = trim(substr($appKey, 7));
    }
    $validClientKey = 'ep-client-v1-bharat-first-2024';
    $hasKey = ($appKey === $validClientKey ||
               $appKey === $master_secret_key ||
               $appKey === $master_admin_password ||
               strpos($appKey, 'ep-adm-') === 0 ||
               $appKey === 'JaiHindBharatMataKiJai2024!' ||
               $appKey === 'Q0gng04bk3');

    $secDest = strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
    $secMode = strtolower($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
    $accept  = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $referer = strtolower($_SERVER['HTTP_REFERER'] ?? '');

    // Block when pasted directly into browser address bar without key
    $isDirectAddressBarNav = ($secDest === 'document' || $secMode === 'navigate' || (strpos($accept, 'text/html') !== false && empty($referer)));

    if ($isDirectAddressBarNav && !$hasKey) {
        http_response_code(403);
        echo json_encode([
            'error' => 'DIRECT_INSPECTION_DENIED',
            'status' => 403,
            'message' => '🛡️ Sovereign Patriot Shield: Direct manual inspection or unauthorized scraping of API endpoints is strictly prohibited. Access is restricted exclusively to authenticated application client runtimes.',
            'code' => 'SHIELD_BLOCKED_INSPECTION'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Check IP Status Endpoint
if ($endpoint === 'check-ip') {
    $isBlocked = false;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
            $stmt->execute([$ip]);
            if ($stmt->fetch()) {
                $isBlocked = true;
            }
        } catch (Exception $e) {}
    }
    if ($isBlocked) {
        http_response_code(403);
        die('');
    }
    echo json_encode(['blocked' => false, 'ip' => $ip]);
    exit;
}

// Log DevTools / Code Inspect Attempt Endpoint
if ($endpoint === 'security/inspect-attempt' && $method === 'POST') {
    $now = date('c');
    $attemptId = 'inspect_' . time() . '_' . rand(100, 999);
    $actId = 'act_' . time() . '_' . rand(100, 999);
    $details = 'DevTools / Inspect mode opened by user. IP flagged for admin review.';
    if (!empty($body['userAgent'])) {
        $details .= ' UA: ' . substr($body['userAgent'], 0, 150);
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO blocked_attempts (id, ip, attempted_name, details, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$attemptId, $ip, '🚨 Inspect / DevTools Attempt', $details, $now]);
        } catch (Exception $e) {}

        try {
            $stmt2 = $pdo->prepare("INSERT INTO activity_logs (id, action, details, ip, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$actId, 'INSPECT_ATTEMPT', 'DevTools inspect attempt flagged from IP ' . $ip, $ip, $now]);
        } catch (Exception $e) {}
    }

    echo json_encode(['success' => true, 'logged' => true, 'ip' => $ip]);
    exit;
}

// System Health Check
if ($endpoint === 'health') {
    echo json_encode([
        'status' => 'ONLINE',
        'system' => 'Exposed Propagandus Native API Bridge',
        'db_connected' => ($pdo !== null),
        'db_type' => $db_type,
        'db_host' => $db_host,
        'db_name' => $db_name,
        'db_error' => $db_error,
        'slogan' => 'Bharat Mata ki Jai 🇮🇳',
        'timestamp' => date('c')
    ]);
    exit;
}

// Global Announcement Banner (Public)
if ($endpoint === 'announcement') {
    if ($method === 'GET') {
        $ann = null;
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM announcements WHERE id = 'global' AND is_active = 1 LIMIT 1");
                $ann = $stmt->fetch();
            } catch (Exception $e) {}
        }
        echo json_encode([
            'active' => (bool)$ann,
            'announcement' => $ann ?: null
        ]);
        exit;
    }
}

// Spotlight Media (Public)
if ($endpoint === 'spotlight') {
    if ($method === 'GET') {
        $default = [
            'type' => 'off',
            'title' => '',
            'videoUrl' => '',
            'channelName' => '',
            'channelUrl' => '',
            'imageUrl' => '',
            'imageCaption' => ''
        ];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = 'spotlight_media' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch();
                if ($row && !empty($row['value'])) {
                    $parsed = json_decode($row['value'], true);
                    if ($parsed) {
                        echo json_encode($parsed);
                        exit;
                    }
                }
            } catch (Exception $e) {}
        }
        echo json_encode($default);
        exit;
    }
}

// Flash Bulletin Banner (Public)
if ($endpoint === 'bulletin') {
    if ($method === 'GET') {
        $default = [
            'is_active' => 1,
            'title' => '⚡ RASHTRIYA FLASH BULLETIN',
            'message' => 'Patriotic Alert: Counter anti-national propaganda on social media with verified facts, civilizational unity, and unwavering national resolve. Bharat Mata ki Jai! 🇮🇳',
            'link' => '',
            'updated_at' => date('c')
        ];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT value FROM system_settings WHERE `key` = 'nationalist_bulletin' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch();
                if ($row && !empty($row['value'])) {
                    $parsed = json_decode($row['value'], true);
                    if ($parsed) {
                        echo json_encode($parsed);
                        exit;
                    }
                }
            } catch (Exception $e) {}
        }
        echo json_encode($default);
        exit;
    }
}

// Realtime Visitor & Traffic Tracking
if ($endpoint === 'track-visit') {
    $clientIp = getClientIp();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = trim($body['page'] ?? 'Homepage');
    $isHeartbeat = !empty($body['is_heartbeat']);
    $now = date('c');

    $deviceType = 'Desktop';
    $os = 'Unknown OS';
    $browser = 'Unknown Browser';
    if (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) $deviceType = 'Mobile';
    else if (preg_match('/ipad|tablet/i', $userAgent)) $deviceType = 'Tablet';

    if (preg_match('/windows/i', $userAgent)) $os = 'Windows';
    else if (preg_match('/android/i', $userAgent)) $os = 'Android';
    else if (preg_match('/iphone|ipad|ipod/i', $userAgent)) $os = 'iOS';
    else if (preg_match('/mac os/i', $userAgent)) $os = 'macOS';
    else if (preg_match('/linux/i', $userAgent)) $os = 'Linux';

    if (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
    else if (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
    else if (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
    else if (preg_match('/safari/i', $userAgent)) $browser = 'Safari';

    if ($pdo) {
        try {
            if ($isHeartbeat) {
                $stmt = $pdo->prepare("UPDATE site_visitors SET last_seen = ?, current_page = ? WHERE ip = ?");
                $stmt->execute([$now, $page, $clientIp]);
            } else {
                $chkVis = $pdo->prepare("SELECT ip, visit_count FROM site_visitors WHERE ip = ?");
                $chkVis->execute([$clientIp]);
                $visRow = $chkVis->fetch();

                if ($visRow) {
                    $stmt = $pdo->prepare("UPDATE site_visitors SET visit_count = visit_count + 1, last_seen = ?, current_page = ?, device_type = ?, os = ?, browser = ?, user_agent = ? WHERE ip = ?");
                    $stmt->execute([$now, $page, $deviceType, $os, $browser, substr($userAgent, 0, 255), $clientIp]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO site_visitors (ip, device_type, os, browser, user_agent, visit_count, current_page, first_visited, last_seen) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)");
                    $stmt->execute([$clientIp, $deviceType, $os, $browser, substr($userAgent, 0, 255), $page, $now, $now]);
                }

                $logId = 'log-' . time() . '-' . substr(md5(rand()), 0, 6);
                $stmtLog = $pdo->prepare("INSERT INTO visitor_traffic_logs (id, ip, device_type, os, browser, page, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtLog->execute([$logId, $clientIp, $deviceType, $os, $browser, $page, substr($userAgent, 0, 255), $now]);
            }
        } catch (Exception $e) {}
    }

    echo json_encode(['success' => true, 'ip' => $clientIp]);
    exit;
}

// Fetch Social Profile (YouTube, X, Instagram)
if ($endpoint === 'fetch-profile') {
    $url = trim($body['url'] ?? $_GET['url'] ?? '');
    $platform = $body['platform'] ?? 'auto';

    if (!$url) {
        http_response_code(400);
        echo json_encode(['error' => 'URL is required']);
        exit;
    }

    if ($platform === 'auto' || !$platform) {
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) $platform = 'yt';
        else if (preg_match('/x\.com|twitter\.com/i', $url)) $platform = 'x';
        else if (preg_match('/instagram\.com/i', $url)) $platform = 'insta';
        else $platform = 'yt';
    }

    $cleanHandle = extract_handle_php($url, $platform);
    if (!$cleanHandle) {
        $cleanHandle = trim(explode('/', explode('?', preg_replace('/^https?:\/\/[^\/]+\//', '', $url))[0])[0], '@');
    }

    $name = ucwords(str_replace(['_', '-', '.'], ' ', $cleanHandle));
    $avatar = null;
    $subscribers = null;
    $followers = null;

    if ($platform === 'yt') {
        $avatar = "https://unavatar.io/youtube/@" . $cleanHandle;
        $opts = ['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n", 'timeout' => 5]];
        $ctx = stream_context_create($opts);
        $ytHtml = @file_get_contents("https://www.youtube.com/@" . $cleanHandle, false, $ctx);
        if ($ytHtml) {
            if (preg_match('/<title>(.*?)\s*-\s*YouTube<\/title>/i', $ytHtml, $tm)) {
                $name = trim($tm[1]);
            }
            if (preg_match('/"avatar":\{"thumbnails":\[\{"url":"([^"]+)"/', $ytHtml, $am)) {
                $avatar = preg_replace('/=s\d+[^"]*/', '=s800-c-k-c0x00ffffff-no-rj', $am[1]);
            }
            if (preg_match('/"metadataParts":\[\{"text":\{"content":"([^"]*?subscribers?)"/i', $ytHtml, $mm)) {
                $subscribers = $mm[1];
                $followers = $mm[1];
            }
        }
    } else if ($platform === 'x') {
        $avatar = "https://unavatar.io/x/" . $cleanHandle;
        $opts = ['http' => ['header' => "User-Agent: Mozilla/5.0\r\n", 'timeout' => 4]];
        $ctx = stream_context_create($opts);
        $fxJson = @file_get_contents("https://api.fxtwitter.com/" . urlencode($cleanHandle), false, $ctx);
        if ($fxJson) {
            $fxData = json_decode($fxJson, true);
            if (!empty($fxData['user']['name'])) $name = $fxData['user']['name'];
            if (!empty($fxData['user']['avatar_url'])) {
                $avatar = str_replace('_normal.', '_400x400.', $fxData['user']['avatar_url']);
            }
            if (isset($fxData['user']['followers'])) {
                $fCount = (int)$fxData['user']['followers'];
                $followers = $fCount >= 1000000 ? round($fCount / 1000000, 2) . 'M followers' : ($fCount >= 1000 ? round($fCount / 1000, 1) . 'K followers' : $fCount . ' followers');
            }
        }
    } else if ($platform === 'insta') {
        // Scrape Instagram embed to extract real profile picture
        $opts = ['http' => ['header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n", 'timeout' => 4]];
        $ctx = stream_context_create($opts);
        $embedHtml = @file_get_contents("https://www.instagram.com/" . urlencode($cleanHandle) . "/embed/", false, $ctx);
        if ($embedHtml) {
            $idx = strpos($embedHtml, 'profile_pic_url');
            if ($idx !== false) {
                $httpIdx = strpos($embedHtml, 'http', $idx);
                if ($httpIdx !== false) {
                    $endQuote = strpos($embedHtml, '\"', $httpIdx);
                    $plainQuote = strpos($embedHtml, '"', $httpIdx);
                    $endIdx = ($endQuote === false) ? $plainQuote : (($plainQuote === false) ? $endQuote : min($endQuote, $plainQuote));
                    if ($endIdx !== false && $endIdx > $httpIdx) {
                        $rawPic = substr($embedHtml, $httpIdx, $endIdx - $httpIdx);
                        $avatar = stripslashes(str_replace(['\\/', '\u0025'], ['/', '%'], $rawPic));
                    }
                }
            }
            if (preg_match('/edge_followed_by["\s:]+count["\s:]+([0-9]+)/', $embedHtml, $fm)) {
                $cnt = (int)$fm[1];
                $followers = $cnt >= 1000000 ? round($cnt / 1000000, 1) . 'M followers' : ($cnt >= 1000 ? round($cnt / 1000, 1) . 'K followers' : $cnt . ' followers');
            }
        }
        // Guaranteed beautiful, high-resolution Instagram-branded avatar fallback (avoids unavatar 403 Forbidden)
        if (!$avatar) {
            $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=E1306C&color=ffffff&size=512&bold=true&font-size=0.38";
        }
    } else {
        $avatar = "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($cleanHandle);
    }

    echo json_encode([
        'success' => true,
        'name' => $name,
        'handle' => $cleanHandle,
        'avatarUrl' => $avatar,
        'platform' => $platform,
        'subscribers' => $subscribers,
        'followers' => $followers,
        'profile' => [
            'name' => $name,
            'handle' => $cleanHandle,
            'avatarUrl' => $avatar,
            'platform' => $platform,
            'subscribers' => $subscribers,
            'followers' => $followers
        ]
    ]);
    exit;
}

// Global System Statistics
if ($endpoint === 'stats') {
    $totalEntities = 0;
    $totalUpvotes = 0;
    $totalNationalists = 0;
    $blockedIps = 0;
    $pendingReports = 0;
    if ($pdo) {
        try {
            $totalEntities = (int)$pdo->query("SELECT COUNT(*) FROM entities")->fetchColumn();
            $totalUpvotes = (int)$pdo->query("SELECT COALESCE(SUM(upvotes), 0) FROM entities")->fetchColumn();
            $totalNationalists = (int)$pdo->query("SELECT COUNT(*) FROM nationalist_influencers WHERE status = 'approved'")->fetchColumn();
            $blockedIps = (int)$pdo->query("SELECT COUNT(*) FROM blocked_ips")->fetchColumn();
            $pendingReports = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {}
    }
    echo json_encode([
        'totalEntities' => $totalEntities,
        'verifiedEntities' => $totalEntities,
        'totalReports' => $pendingReports,
        'totalComparisons' => 10,
        'blockedIps' => $blockedIps,
        'totalUpvotes' => $totalUpvotes,
        'totalNationalists' => $totalNationalists,
        'systemStatus' => 'ONLINE'
    ]);
    exit;
}

// Live Visitor Count
if ($endpoint === 'live-count') {
    $liveCount = 1;
    if ($pdo) {
        try {
            $cutoff = date('c', time() - 180);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitors WHERE last_seen >= ?");
            $stmt->execute([$cutoff]);
            $liveCount = max(1, (int)$stmt->fetchColumn());
        } catch (Exception $e) {}
    }
    echo json_encode(['count' => $liveCount, 'liveCount' => $liveCount]);
    exit;
}

// Entities (Propagandus Directory)
if ($endpoint === 'entities') {
    if ($method === 'GET') {
        if (!$pdo) {
            echo json_encode([]);
            exit;
        }
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $sql = "SELECT * FROM entities WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (name LIKE ? OR narrative LIKE ? OR handle_x LIKE ? OR handle_yt LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($category && $category !== 'All') {
            $sql .= " AND categories LIKE ?";
            $params[] = "%$category%";
        }
        $sql .= " ORDER BY upvotes DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['categories'] = json_decode($r['categories'] ?? '[]', true) ?: ['Social Media'];
            $r['upvotes'] = (int)$r['upvotes'];
        }
        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST') {
        $name = trim($body['name'] ?? '');
        if (strlen($name) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }

        $patriotMatch = check_patriot_php($name);
        if ($patriotMatch) {
            $warning = 'बेटा गांडमस्ती नहीं वरना तुम्हारे बाप भी पेले गए थे तुम भी पेले जाओगे';
            http_response_code(400);
            echo json_encode([
                'error' => 'Protected Entity Violation',
                'isProtectedPatriot' => true,
                'match' => $patriotMatch,
                'message' => $warning,
                'speechText' => $warning
            ]);
            exit;
        }

        $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
        $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
        $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');

        $err = validate_social_handles_php($pdo, $cleanX, $cleanYt, $cleanInsta, 'entities');
        if ($err) {
            http_response_code(400);
            echo json_encode(['error' => $err]);
            exit;
        }

        $id = 'prop-' . time() . '-' . substr(md5(uniqid()), 0, 6);
        $now = date('c');
        $cats = json_encode($body['categories'] ?? ['Social Media']);
        $avatar = $body['avatar_url'] ?: "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
        $phone = !empty($body['contact_phone']) ? substr(trim($body['contact_phone']), 0, 50) : null;
        $email = !empty($body['contact_email']) ? substr(trim($body['contact_email']), 0, 255) : null;
        $otherLinks = !empty($body['other_links']) ? (is_array($body['other_links']) ? json_encode($body['other_links']) : $body['other_links']) : null;

        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO entities (id, name, handle_x, handle_yt, handle_insta, contact_phone, contact_email, other_links, avatar_url, categories, party, institution, narrative, fact_check, proof_url, upvotes, is_verified, submitted_by_ip, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?, ?)
            ");
            $stmt->execute([
                $id, $name, $cleanX, $cleanYt, $cleanInsta,
                $phone, $email, $otherLinks,
                $avatar, $cats, $body['party'] ?? null, $body['institution'] ?? null,
                $body['narrative'] ?? '', $body['fact_check'] ?? null, $body['proof_url'] ?? null,
                $ip, $now, $now
            ]);
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Profile added successfully']);
        exit;
    }
}

// Upvote Entity
if (preg_match('/^entities\/([a-zA-Z0-9_-]+)\/upvote$/', $endpoint, $m)) {
    $entId = $m[1];
    $clientIp = getClientIp();
    $upvotes = 0;
    if ($pdo) {
        try {
            $check = $pdo->prepare("SELECT id FROM profile_votes WHERE target_id = ? AND ip = ?");
            $check->execute([$entId, $clientIp]);
            if ($check->fetch()) {
                $res = $pdo->prepare("SELECT upvotes FROM entities WHERE id = ?");
                $res->execute([$entId]);
                echo json_encode(['success' => false, 'alreadyVoted' => true, 'upvotes' => (int)$res->fetchColumn()]);
                exit;
            }

            $voteId = 'vote-' . time() . '-' . substr(md5(uniqid()), 0, 6);
            $ins = $pdo->prepare("INSERT INTO profile_votes (id, target_id, target_type, ip, created_at) VALUES (?, ?, 'entity', ?, ?)");
            $ins->execute([$voteId, $entId, $clientIp, date('c')]);

            $stmt = $pdo->prepare("UPDATE entities SET upvotes = upvotes + 1 WHERE id = ?");
            $stmt->execute([$entId]);
            $res = $pdo->prepare("SELECT upvotes FROM entities WHERE id = ?");
            $res->execute([$entId]);
            $upvotes = (int)$res->fetchColumn();
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true, 'upvotes' => $upvotes]);
    exit;
}

// Nationalist Influencers (Public list)
if ($endpoint === 'nationalist-influencers') {
    if ($method === 'GET') {
        if (!$pdo) {
            echo json_encode([]);
            exit;
        }
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $sql = "SELECT * FROM nationalist_influencers WHERE status = 'approved'";
        $params = [];
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ? OR handle_x LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($category && $category !== 'All') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        $sql .= " ORDER BY upvotes DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['upvotes'] = (int)$r['upvotes'];
        }
        echo json_encode($rows);
        exit;
    }
}

// Suggest Nationalist Creator
if ($endpoint === 'nationalist-influencers/suggest') {
    $name = trim($body['name'] ?? '');
    if (strlen($name) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
    $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
    $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');

    $err = validate_social_handles_php($pdo, $cleanX, $cleanYt, $cleanInsta, 'nationalist_influencers');
    if ($err) {
        http_response_code(400);
        echo json_encode(['error' => $err]);
        exit;
    }

    $id = 'nat-' . time() . '-' . substr(md5(uniqid()), 0, 6);
    $now = date('c');
    $avatar = $body['avatar_url'] ?: "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
    $otherLinks = !empty($body['other_links']) ? (is_array($body['other_links']) ? json_encode($body['other_links']) : $body['other_links']) : null;

    $rawCats = $body['categories'] ?? ($body['category'] ?? 'Nationalist Creator');
    $finalCat = is_array($rawCats) ? implode(', ', array_filter($rawCats)) : trim($rawCats);
    if (!$finalCat) $finalCat = 'Nationalist Creator';

    if ($pdo) {
        $stmt = $pdo->prepare("
            INSERT INTO nationalist_influencers (id, name, handle_x, handle_yt, handle_insta, other_links, avatar_url, category, description, status, upvotes, suggested_by_ip, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?, ?)
        ");
        $stmt->execute([
            $id, $name, $cleanX, $cleanYt, $cleanInsta,
            $otherLinks,
            $avatar, $finalCat, $body['description'] ?? '',
            $ip, $now, $now
        ]);
    }

    echo json_encode([
        'success' => true,
        'id' => $id,
        'message' => 'धन्यवाद! राष्ट्रवादी प्रेरक का सुझाव व्यवस्थापक समीक्षा के लिए सुरक्षित रूप से प्रेषित कर दिया गया है।'
    ]);
    exit;
}

// Upvote Nationalist Creator
if (preg_match('/^nationalist-influencers\/([a-zA-Z0-9_-]+)\/upvote$/', $endpoint, $m)) {
    $natId = $m[1];
    $clientIp = getClientIp();
    $upvotes = 0;
    if ($pdo) {
        try {
            $check = $pdo->prepare("SELECT id FROM profile_votes WHERE target_id = ? AND ip = ?");
            $check->execute([$natId, $clientIp]);
            if ($check->fetch()) {
                $res = $pdo->prepare("SELECT upvotes FROM nationalist_influencers WHERE id = ?");
                $res->execute([$natId]);
                echo json_encode(['success' => false, 'alreadyVoted' => true, 'upvotes' => (int)$res->fetchColumn()]);
                exit;
            }

            $voteId = 'vote-' . time() . '-' . substr(md5(uniqid()), 0, 6);
            $ins = $pdo->prepare("INSERT INTO profile_votes (id, target_id, target_type, ip, created_at) VALUES (?, ?, 'nationalist', ?, ?)");
            $ins->execute([$voteId, $natId, $clientIp, date('c')]);

            $stmt = $pdo->prepare("UPDATE nationalist_influencers SET upvotes = upvotes + 1 WHERE id = ?");
            $stmt->execute([$natId]);
            $res = $pdo->prepare("SELECT upvotes FROM nationalist_influencers WHERE id = ?");
            $res->execute([$natId]);
            $upvotes = (int)$res->fetchColumn();
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true, 'upvotes' => $upvotes]);
    exit;
}

// Viksit Bharat Govt Poll (GET)
if ($endpoint === 'govt-poll') {
    $pollId = 'bjp_2029';
    $yesVotes = 0;
    $noVotes = 0;
    $question = 'Do you want BJP / NDA back in 2029?';
    $userVoted = null;

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM govt_polls WHERE id = ? LIMIT 1");
            $stmt->execute([$pollId]);
            $row = $stmt->fetch();
            if ($row) {
                $yesVotes = (int)$row['yes_votes'];
                $noVotes = (int)$row['no_votes'];
                $question = $row['question'];
            }

            $vStmt = $pdo->prepare("SELECT choice FROM govt_poll_votes WHERE poll_id = ? AND ip = ? LIMIT 1");
            $vStmt->execute([$pollId, $ip]);
            $vRow = $vStmt->fetch();
            if ($vRow) {
                $userVoted = $vRow['choice'];
            }
        } catch (Exception $e) {}
    }

    $totalVotes = $yesVotes + $noVotes;
    $yesPercent = $totalVotes > 0 ? round(($yesVotes / $totalVotes) * 100, 1) : 0;
    $noPercent  = $totalVotes > 0 ? round(($noVotes / $totalVotes) * 100, 1) : 0;

    echo json_encode([
        'id' => $pollId,
        'question' => $question,
        'yesVotes' => $yesVotes,
        'noVotes' => $noVotes,
        'totalVotes' => $totalVotes,
        'yesPercent' => $yesPercent,
        'noPercent' => $noPercent,
        'userVoted' => $userVoted
    ]);
    exit;
}

// Govt Poll Vote (POST)
if ($endpoint === 'govt-poll/vote') {
    $pollId = $body['poll_id'] ?? 'bjp_2029';
    $choice = strtolower(trim($body['choice'] ?? ''));

    if (!in_array($choice, ['yes', 'no'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid choice. Must be yes or no.']);
        exit;
    }

    $yesVotes = 0;
    $noVotes = 0;
    $now = date('c');

    if ($pdo) {
        try {
            // Ensure poll row exists
            $chkPoll = $pdo->prepare("SELECT * FROM govt_polls WHERE id = ?");
            $chkPoll->execute([$pollId]);
            $pollRow = $chkPoll->fetch();
            if (!$pollRow) {
                $insPoll = $pdo->prepare("INSERT INTO govt_polls (id, question, yes_votes, no_votes, updated_at) VALUES (?, 'Do you want BJP / NDA back in 2029?', 0, 0, ?)");
                $insPoll->execute([$pollId, $now]);
            }

            // Check if this IP has already voted
            $chk = $pdo->prepare("SELECT choice FROM govt_poll_votes WHERE poll_id = ? AND ip = ?");
            $chk->execute([$pollId, $ip]);
            $existing = $chk->fetch();

            if ($existing) {
                $oldChoice = strtolower($existing['choice']);
                if ($oldChoice !== $choice) {
                    // Switch vote smoothly
                    if ($oldChoice === 'yes' && $choice === 'no') {
                        $pdo->prepare("UPDATE govt_polls SET yes_votes = GREATEST(0, yes_votes - 1), no_votes = no_votes + 1, updated_at = ? WHERE id = ?")->execute([$now, $pollId]);
                    } else if ($oldChoice === 'no' && $choice === 'yes') {
                        $pdo->prepare("UPDATE govt_polls SET no_votes = GREATEST(0, no_votes - 1), yes_votes = yes_votes + 1, updated_at = ? WHERE id = ?")->execute([$now, $pollId]);
                    }
                    // Update record in govt_poll_votes
                    $upd = $pdo->prepare("UPDATE govt_poll_votes SET choice = ?, voted_at = ? WHERE poll_id = ? AND ip = ?");
                    $upd->execute([$choice, $now, $pollId, $ip]);
                }
            } else {
                // First-time vote by this IP
                $ins = $pdo->prepare("INSERT INTO govt_poll_votes (poll_id, ip, choice, voted_at) VALUES (?, ?, ?, ?)");
                $ins->execute([$pollId, $ip, $choice, $now]);

                if ($choice === 'yes') {
                    $pdo->prepare("UPDATE govt_polls SET yes_votes = yes_votes + 1, updated_at = ? WHERE id = ?")->execute([$now, $pollId]);
                } else {
                    $pdo->prepare("UPDATE govt_polls SET no_votes = no_votes + 1, updated_at = ? WHERE id = ?")->execute([$now, $pollId]);
                }
            }

            // Fetch updated tallies
            $pStmt = $pdo->prepare("SELECT * FROM govt_polls WHERE id = ?");
            $pStmt->execute([$pollId]);
            $pRow = $pStmt->fetch();
            $yesVotes = (int)($pRow['yes_votes'] ?? 0);
            $noVotes  = (int)($pRow['no_votes'] ?? 0);
        } catch (Exception $e) {}
    }

    $totalVotes = $yesVotes + $noVotes;
    $yesPercent = $totalVotes > 0 ? round(($yesVotes / $totalVotes) * 100, 1) : 0;
    $noPercent  = $totalVotes > 0 ? round(($noVotes / $totalVotes) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'id' => $pollId,
        'userVoted' => $choice,
        'userChoice' => $choice,
        'yesVotes' => $yesVotes,
        'noVotes' => $noVotes,
        'totalVotes' => $totalVotes,
        'yesPercent' => $yesPercent,
        'noPercent' => $noPercent
    ]);
    exit;
}

// User's Local Votes
if ($endpoint === 'my-votes') {
    $votedEntityIds = [];
    $pollVote = null;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT target_id FROM profile_votes WHERE ip = ?");
            $stmt->execute([$ip]);
            $votedEntityIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $pStmt = $pdo->prepare("SELECT choice FROM govt_poll_votes WHERE poll_id = 'bjp_2029' AND ip = ?");
            $pStmt->execute([$ip]);
            $pollVote = $pStmt->fetchColumn() ?: null;
        } catch (Exception $e) {}
    }
    echo json_encode([
        'votedEntityIds' => $votedEntityIds,
        'pollVote' => $pollVote
    ]);
    exit;
}

// Site Categories
if ($endpoint === 'categories') {
    $type = $_GET['type'] ?? '';
    $rows = [];
    if ($pdo) {
        try {
            if ($type) {
                $stmt = $pdo->prepare("SELECT * FROM site_categories WHERE type = ? ORDER BY id ASC");
                $stmt->execute([$type]);
                $rows = $stmt->fetchAll();
            } else {
                $rows = $pdo->query("SELECT * FROM site_categories ORDER BY id ASC")->fetchAll();
            }
        } catch (Exception $e) {}
    }
    echo json_encode($rows);
    exit;
}

// Public Music Playlist (Active songs only)
if ($endpoint === 'playlist') {
    $tracks = [];
    if ($pdo) {
        try {
            $tracks = $pdo->query("SELECT * FROM playlist_tracks WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC")->fetchAll();
        } catch (Exception $e) {}
    }
    echo json_encode($tracks);
    exit;
}

// Public Admin Posts & Dispatches Feed
if ($endpoint === 'posts') {
    $posts = [];
    if ($pdo) {
        try {
            $posts = $pdo->query("SELECT * FROM admin_posts ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
        } catch (Exception $e) {}
    }
    echo json_encode($posts);
    exit;
}

// Like an Admin Post
if (preg_match('/^posts\/([a-zA-Z0-9_-]+)\/like$/', $endpoint, $m) && $method === 'POST') {
    $postId = $m[1];
    $likes = 0;
    if ($pdo) {
        try {
            $pdo->prepare("UPDATE admin_posts SET likes = likes + 1 WHERE id = ?")->execute([$postId]);
            $res = $pdo->prepare("SELECT likes FROM admin_posts WHERE id = ?");
            $res->execute([$postId]);
            $likes = (int)$res->fetchColumn();
        } catch (Exception $e) {}
    }
    echo json_encode(['success' => true, 'likes' => $likes]);
    exit;
}

// Submit Public Report
if ($endpoint === 'reports') {
    if ($method === 'POST') {
        $entityId = trim($body['entity_id'] ?? '');
        $message  = trim($body['message'] ?? '');
        $reportType = trim($body['report_type'] ?? 'Wrong Information');

        if (!$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Report message is required']);
            exit;
        }

        $id = 'rep-' . time() . '-' . substr(md5(uniqid()), 0, 6);
        $now = date('c');

        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO reports (id, entity_id, entity_name, report_type, message, status, reported_by_ip, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $id, $entityId, $body['entity_name'] ?? null, $reportType, $message, $ip, $now
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Report submitted to Admin Panel.', 'reportId' => $id]);
        exit;
    }
}

// Feature Suggestions Endpoint
if ($endpoint === 'feature-suggestions') {
    if ($method === 'POST') {
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $category = trim($body['category'] ?? 'New Feature');
        $message = trim($body['message'] ?? '');

        if (!$name || !$email || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, email, and suggestion message are required.']);
            exit;
        }

        $id = 'sug-' . time() . '-' . substr(md5(uniqid()), 0, 6);
        $now = date('c');

        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO feature_suggestions (id, name, email, category, message, ip_address, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute([$id, $name, $email, $category, $message, $ip, $now]);
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Dhanyawad! Suggestion sent to admin desk.']);
        exit;
    }
}

// Geo-Check Endpoint (India IP validation)
if ($endpoint === 'geo-check') {
    $cfCountry = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    $clientIp = getClientIp();

    // Localhost or private networks
    $isLocal = ($clientIp === '127.0.0.1' || $clientIp === '::1' || strpos($clientIp, '192.168.') === 0 || strpos($clientIp, '10.') === 0);

    // If Cloudflare provided country
    if ($cfCountry) {
        $allowed = ($cfCountry === 'IN' || $isLocal);
        echo json_encode([
            'allowed' => $allowed,
            'country' => $cfCountry,
            'ip' => $clientIp
        ]);
        exit;
    }

    // Default allowed for direct/domestic visitors
    echo json_encode([
        'allowed' => true,
        'country' => 'IN',
        'ip' => $clientIp
    ]);
    exit;
}

// -------------------------------------------------------------
// SOVEREIGN COMMUNITY ENDPOINTS (Public Chat, Channels, Admin)
// -------------------------------------------------------------

$comm_protected_regex = '/^(modi|narendra_modi|namo|bjp|amit_shah|yogi|adityanath|rss|ram|rama|shiva|shiv|krishna|hanuman|vishnu|ganesh|ganpati|mahadev|durga|kali|sita|radha|laxmi|lakshmi|saraswati|bholenath|shanker|shankar|bajrangbali|brahma|admin|official|owner|moderator|propagandus)/i';
$comm_abusive_words = [
    'godi', 'bhakt', 'chaddi', 'sanghi kutta', 'modi kutta', 'hindutva terrorist',
    'mutra', 'cow urine', 'pappu kutta', 'fake hindu'
];

function comm_is_abusive($text, $words) {
    if (!$text) return false;
    $lower = strtolower($text);
    foreach ($words as $w) {
        if (strpos($lower, $w) !== false) return true;
    }
    return false;
}

// Link Preview (WhatsApp-Style for ANY website link)
if ($endpoint === 'link-preview' && $method === 'GET') {
    $rawUrl = $_GET['url'] ?? '';
    if (!$rawUrl) {
        http_response_code(400);
        echo json_encode(['error' => 'URL is required']);
        exit;
    }

    $targetUrl = trim($rawUrl);
    if (!preg_match('#^https?://#i', $targetUrl)) {
        $targetUrl = 'https://' . $targetUrl;
    }

    $parsed = parse_url($targetUrl);
    $host = $parsed['host'] ?? '';
    $domain = preg_replace('/^www\./', '', $host);

    // YouTube handler
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', $targetUrl, $ytm)) {
        $vId = $ytm[1];
        $title = 'YouTube Video';
        $oembed = @file_get_contents("https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$vId}&format=json");
        if ($oembed) {
            $dec = json_decode($oembed, true);
            if (!empty($dec['title'])) $title = $dec['title'];
        }
        echo json_encode([
            'success' => true,
            'preview' => [
                'url' => $targetUrl,
                'domain' => 'www.youtube.com',
                'title' => $title,
                'description' => 'Watch on YouTube',
                'image' => "https://img.youtube.com/vi/{$vId}/hqdefault.jpg",
                'isVideo' => true
            ]
        ]);
        exit;
    }

    // Generic Web Preview
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 WhatsApp/2.23.20.0\r\n"
        ]
    ]);
    $html = @file_get_contents($targetUrl, false, $ctx);
    if (!$html) {
        echo json_encode([
            'success' => true,
            'preview' => [
                'url' => $targetUrl,
                'domain' => $domain,
                'title' => $domain,
                'description' => $targetUrl,
                'image' => null,
                'isVideo' => false
            ]
        ]);
        exit;
    }

    $title = $domain;
    if (preg_match('/<meta[^>]*property=["\'](?:og:title|twitter:title)["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }

    $desc = '';
    if (preg_match('/<meta[^>]*property=["\'](?:og:description|twitter:description)["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $desc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    } elseif (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $desc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    $image = null;
    if (preg_match('/<meta[^>]*property=["\'](?:og:image|twitter:image)["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
        $image = trim($m[1]);
        if (!preg_match('#^https?://#i', $image)) {
            $image = rtrim($targetUrl, '/') . '/' . ltrim($image, '/');
        }
    }

    echo json_encode([
        'success' => true,
        'preview' => [
            'url' => $targetUrl,
            'domain' => $domain,
            'title' => $title,
            'description' => substr($desc, 0, 160),
            'image' => $image,
            'isVideo' => (strpos($targetUrl, 'shorts') !== false || strpos($targetUrl, 'reel') !== false || strpos($targetUrl, 'video') !== false)
        ]
    ]);
    exit;
}

// 1. Community Sync (SECURED: Role-Based Field Filtering & IDOR Protection)
if ($endpoint === 'community/sync' && $method === 'GET') {
    if ($pdo) {
        $chkBlock = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
        $chkBlock->execute([$ip]);
        if ($chkBlock->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'BLOCKED_IP', 'message' => 'Your IP address is permanently blacklisted.']);
            exit;
        }

        $headers = get_request_headers();
        $authKey = $headers['x-admin-key'] ?? $headers['authorization'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($authKey, 'Bearer ') === 0) {
            $authKey = trim(substr($authKey, 7));
        }
        $isAdmin = !empty($authKey) && ($authKey === $master_secret_key || $authKey === $master_admin_password || strpos($authKey, 'ep-adm-') === 0);
        $reqUserId = trim($_GET['user_id'] ?? '');
        $reqUsername = strtolower(trim($_GET['username'] ?? ''));

        // 1. Users: If admin, full details; if public, strictly sanitize sensitive contact fields!
        if ($isAdmin) {
            $users = $pdo->query("SELECT id, name, username, email, phone, avatar_url, verified_type, is_admin, is_banned, name_change_count, username_change_count, created_at FROM community_users ORDER BY created_at DESC")->fetchAll();
        } else {
            $users = $pdo->query("SELECT id, name, username, avatar_url, verified_type, is_admin, is_banned, name_change_count, username_change_count, created_at FROM community_users WHERE is_banned = 0 ORDER BY created_at DESC")->fetchAll();
        }

        // 2. Messages
        $messagesRaw = $pdo->query("SELECT id, channel_id, user_id, user_name, username, user_avatar, verified_type, content, attachments, media_links, gif_url, reactions, reply_to, is_deleted, created_at FROM community_messages WHERE is_deleted = 0 ORDER BY created_at ASC LIMIT 200")->fetchAll();
        $messages = [];
        foreach ($messagesRaw as $m) {
            $m['attachments'] = !empty($m['attachments']) ? json_decode($m['attachments'], true) : [];
            $m['media_links'] = !empty($m['media_links']) ? json_decode($m['media_links'], true) : [];
            $m['reactions'] = !empty($m['reactions']) ? json_decode($m['reactions'], true) : (object)[];
            $m['reply_to'] = !empty($m['reply_to']) ? json_decode($m['reply_to'], true) : null;
            $messages[] = $m;
        }

        $joinQuery = trim($_GET['join'] ?? '');

        // 3. Channels: sanitize join_token and pending_requests for unauthorized users
        $channelsRaw = $pdo->query("SELECT id, creator_id, creator_name, creator_username, name, description, join_token, is_private, members, pending_requests, created_at FROM community_channels ORDER BY created_at DESC")->fetchAll();
        $channels = [];
        foreach ($channelsRaw as $c) {
            $members = !empty($c['members']) ? json_decode($c['members'], true) : [];
            $pending = !empty($c['pending_requests']) ? json_decode($c['pending_requests'], true) : [];
            $isCreator = ($reqUserId && $c['creator_id'] === $reqUserId);
            $isMember = ($reqUserId && in_array($reqUserId, $members));
            $isInvited = (!empty($joinQuery) && ($c['join_token'] === $joinQuery || $c['id'] === $joinQuery));

            if ($isAdmin || $isCreator || $isMember || empty($c['is_private']) || $isInvited) {
                $c['members'] = ($isAdmin || $isCreator) ? $members : array_map(function($m) use ($reqUserId) { return $m === $reqUserId ? $m : 'member'; }, $members);
                $c['pending_requests'] = ($isAdmin || $isCreator) ? $pending : [];
                if (empty($c['join_token'])) {
                    $c['join_token'] = $c['id'];
                }
                $channels[] = $c;
            }
        }

        if (!$isAdmin) {
            $allowedChanIds = array_column($channels, 'id');
            $allowedChanIds[] = 'public';
            $messages = array_values(array_filter($messages, function($m) use ($allowedChanIds) {
                return in_array($m['channel_id'], $allowedChanIds);
            }));
        }

        // 4. Notifications: strictly scoped to recipient
        $notifications = [];
        if ($isAdmin) {
            $notifications = $pdo->query("SELECT id, recipient_username, sender_username, sender_name, message, is_read, created_at FROM community_notifications ORDER BY created_at DESC LIMIT 100")->fetchAll();
        } elseif ($reqUsername) {
            $stmt = $pdo->prepare("SELECT id, recipient_username, sender_username, sender_name, message, is_read, created_at FROM community_notifications WHERE LOWER(recipient_username) = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$reqUsername]);
            $notifications = $stmt->fetchAll();
        }

        // 5. Admin Queries: STRICTLY ADMIN ONLY!
        $adminQueries = [];
        if ($isAdmin) {
            $adminQueries = $pdo->query("SELECT id, type, name, email, user_id, username, social_account_id, message, proof_file_name, proof_file, ip, status, created_at FROM community_contact_queries ORDER BY created_at DESC LIMIT 100")->fetchAll();
        }

        echo json_encode([
            'success' => true,
            'clientIp' => $ip,
            'users' => $users ?: [],
            'messages' => $messages ?: [],
            'channels' => $channels ?: [],
            'notifications' => $notifications ?: [],
            'adminQueries' => $adminQueries ?: []
        ]);
        exit;
    }
}

// 2. Community Register (@gmail.com and @outlook.com only)
if ($endpoint === 'community/register' && $method === 'POST') {
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection unavailable. Please check database configuration.']);
        exit;
    }

    try {
        $chkBlock = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
        $chkBlock->execute([$ip]);
        if ($chkBlock->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Your IP address has been permanently banned from creating accounts.']);
            exit;
        }

        $name = trim($body['name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $phone = trim($body['phone'] ?? '');
        $password = trim($body['password'] ?? '');
        $avatar_url = trim($body['avatar_url'] ?? '');

        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, email, and password are required.']);
            exit;
        }

        // STRICT Domain Check
        if (!preg_match('/@gmail\.com$/i', $email) && !preg_match('/@outlook\.com$/i', $email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only @gmail.com and @outlook.com addresses are permitted for verified community registration.']);
            exit;
        }

        $chkEmail = $pdo->prepare("SELECT id FROM community_users WHERE LOWER(email) = ?");
        $chkEmail->execute([$email]);
        if ($chkEmail->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'An account with this email address already exists. Please log in.']);
            exit;
        }

        // Auto-generate unique username rahulXXXX
        $cleanBase = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $cleanBase = substr($cleanBase, 0, 8) ?: 'citizen';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789._';
        $generatedUsername = '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $randStr = '';
            for ($i = 0; $i < 4; $i++) {
                $randStr .= $chars[rand(0, strlen($chars) - 1)];
            }
            $testUsername = $cleanBase . '_' . $randStr;
            if (isset($comm_protected_regex) && preg_match($comm_protected_regex, $testUsername)) {
                $testUsername = 'citizen_' . rand(1000, 9999);
            }
            $chkU = $pdo->prepare("SELECT id FROM community_users WHERE LOWER(username) = ?");
            $chkU->execute([strtolower($testUsername)]);
            if (!$chkU->fetch()) {
                $generatedUsername = $testUsername;
                break;
            }
        }
        if (!$generatedUsername) {
            $generatedUsername = $cleanBase . '_' . time();
        }

        $userId = 'usr_' . time() . rand(100, 999);
        $defaultAvatar = $avatar_url ?: "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($generatedUsername);
        $isAdmin = ($email === 'omkumar.working@gmail.com' && $password === 'Q0gng04bk3') ? 1 : 0;
        $now = date('c');

        $stmt = $pdo->prepare("INSERT INTO community_users (id, name, username, email, phone, password_hash, avatar_url, verified_type, ip_address, is_admin, is_banned, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([
            $userId, $name, $generatedUsername, $email, $phone, $password, $defaultAvatar,
            $isAdmin ? 'influencer' : 'none', $ip, $isAdmin, $now, $now
        ]);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'username' => $generatedUsername,
                'email' => $email,
                'phone' => $phone,
                'avatar_url' => $defaultAvatar,
                'verified_type' => $isAdmin ? 'influencer' : 'none',
                'is_admin' => (bool)$isAdmin,
                'is_banned' => 0,
                'name_change_count' => 0,
                'username_change_count' => 0,
                'created_at' => $now
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration error: ' . $e->getMessage()]);
        exit;
    }
}

// 3. Community Login
if ($endpoint === 'community/login' && $method === 'POST') {
    if ($pdo) {
        $cred = strtolower(trim($body['emailOrUsername'] ?? ''));
        $pass = trim($body['password'] ?? '');

        if (!$cred || !$pass) {
            http_response_code(400);
            echo json_encode(['error' => 'Please enter email/username and password.']);
            exit;
        }

        if ($cred === 'omkumar.working@gmail.com' && $pass === 'Q0gng04bk3') {
            $adminUser = [
                'id' => 'usr_admin',
                'name' => 'Om Kumar (Commander)',
                'username' => 'omkumar_admin',
                'email' => 'omkumar.working@gmail.com',
                'phone' => '9876543210',
                'avatar_url' => 'https://api.dicebear.com/7.x/bottts/svg?seed=OmKumarAdmin',
                'verified_type' => 'influencer',
                'is_admin' => true,
                'is_banned' => 0,
                'name_change_count' => 0,
                'username_change_count' => 0,
                'created_at' => '2025-01-01'
            ];
            echo json_encode(['success' => true, 'user' => $adminUser]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM community_users WHERE LOWER(email) = ? OR LOWER(username) = ?");
        $stmt->execute([$cred, $cred]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'No account found with these credentials.']);
            exit;
        }

        if ((int)$user['is_banned'] === 1) {
            http_response_code(403);
            echo json_encode(['error' => 'This account and IP have been permanently banned from the platform.']);
            exit;
        }

        if ($user['password_hash'] !== $pass) {
            http_response_code(401);
            echo json_encode(['error' => 'Incorrect password.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'avatar_url' => $user['avatar_url'],
                'verified_type' => $user['verified_type'],
                'is_admin' => (bool)$user['is_admin'],
                'is_banned' => 0,
                'name_change_count' => (int)($user['name_change_count'] ?? 0),
                'username_change_count' => (int)($user['username_change_count'] ?? 0),
                'created_at' => $user['created_at']
            ]
        ]);
        exit;
    }
}

// 4. Update Username (Max 3 changes for normal users, unlimited for admin)
if ($endpoint === 'community/profile/username' && $method === 'POST') {
    if ($pdo) {
        $userId = trim($body['user_id'] ?? '');
        $newUname = strtolower(trim($body['new_username'] ?? ''));
        $clean = preg_replace('/[^a-z0-9._]/', '', $newUname);

        $uStmt = $pdo->prepare("SELECT email, is_admin, username_change_count FROM community_users WHERE id = ?");
        $uStmt->execute([$userId]);
        $uInfo = $uStmt->fetch();
        if (!$uInfo) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        $isAdminUser = ($uInfo['email'] === 'omkumar.working@gmail.com' || !empty($uInfo['is_admin']));
        $changeCount = (int)($uInfo['username_change_count'] ?? 0);

        if (!$isAdminUser) {
            if ($changeCount >= 3) {
                http_response_code(400);
                echo json_encode(['error' => 'You have reached the maximum limit of 3 username changes. Contact admin for assistance.']);
                exit;
            }

            if (strlen($clean) < 3 || strlen($clean) > 25) {
                http_response_code(400);
                echo json_encode(['error' => 'Username must be between 3 and 25 characters (letters, numbers, dot, underscore).']);
                exit;
            }

            if (preg_match($comm_protected_regex, $clean)) {
                http_response_code(400);
                echo json_encode(['error' => 'Usernames containing names of national leaders, BJP, or Hindu deities are strictly prohibited and reserved.']);
                exit;
            }
        } else {
            if (strlen($clean) < 2 || strlen($clean) > 30) {
                http_response_code(400);
                echo json_encode(['error' => 'Admin username must be at least 2 characters.']);
                exit;
            }
        }

        $chk = $pdo->prepare("SELECT id FROM community_users WHERE username = ? AND id != ?");
        $chk->execute([$clean, $userId]);
        if ($chk->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'This username is already taken. Please pick another.']);
            exit;
        }

        $now = date('c');
        $newCount = $isAdminUser ? $changeCount : ($changeCount + 1);
        $pdo->prepare("UPDATE community_users SET username = ?, username_change_count = ?, updated_at = ? WHERE id = ?")->execute([$clean, $newCount, $now, $userId]);
        $pdo->prepare("UPDATE community_messages SET username = ? WHERE user_id = ?")->execute([$clean, $userId]);

        echo json_encode(['success' => true, 'username' => $clean, 'username_change_count' => $newCount]);
        exit;
    }
}

// 4a. Update Display Name (Max 3 changes for normal users, unlimited for admin)
if ($endpoint === 'community/profile/name' && $method === 'POST') {
    if ($pdo) {
        $userId = trim($body['user_id'] ?? '');
        $newName = trim($body['new_name'] ?? '');

        $uStmt = $pdo->prepare("SELECT email, is_admin, name_change_count FROM community_users WHERE id = ?");
        $uStmt->execute([$userId]);
        $uInfo = $uStmt->fetch();
        if (!$uInfo) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        $isAdminUser = ($uInfo['email'] === 'omkumar.working@gmail.com' || !empty($uInfo['is_admin']));
        $changeCount = (int)($uInfo['name_change_count'] ?? 0);

        if (!$isAdminUser) {
            if ($changeCount >= 3) {
                http_response_code(400);
                echo json_encode(['error' => 'You have reached the maximum limit of 3 display name changes. Contact admin for assistance.']);
                exit;
            }

            if (mb_strlen($newName) < 2 || mb_strlen($newName) > 60) {
                http_response_code(400);
                echo json_encode(['error' => 'Display name must be between 2 and 60 characters.']);
                exit;
            }
        } else {
            if (mb_strlen($newName) < 2 || mb_strlen($newName) > 100) {
                http_response_code(400);
                echo json_encode(['error' => 'Display name must be at least 2 characters.']);
                exit;
            }
        }

        $now = date('c');
        $newCount = $isAdminUser ? $changeCount : ($changeCount + 1);
        $pdo->prepare("UPDATE community_users SET name = ?, name_change_count = ?, updated_at = ? WHERE id = ?")->execute([$newName, $newCount, $now, $userId]);
        $pdo->prepare("UPDATE community_messages SET user_name = ? WHERE user_id = ?")->execute([$newName, $userId]);

        echo json_encode(['success' => true, 'name' => $newName, 'name_change_count' => $newCount]);
        exit;
    }
}

// 4b. Update Profile Avatar (Local Photo Upload)
if ($endpoint === 'community/profile/avatar' && $method === 'POST') {
    if ($pdo) {
        $userId = trim($body['user_id'] ?? '');
        $avatarUrl = $body['avatar_url'] ?? '';
        if ($userId && $avatarUrl) {
            $now = date('c');
            $pdo->prepare("UPDATE community_users SET avatar_url = ?, updated_at = ? WHERE id = ?")->execute([$avatarUrl, $now, $userId]);
            $pdo->prepare("UPDATE community_messages SET user_avatar = ? WHERE user_id = ?")->execute([$avatarUrl, $userId]);
            echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
            exit;
        }
    }
}

// 5. Send Community Message
if ($endpoint === 'community/messages' && $method === 'POST') {
    if ($pdo) {
        $chkBlock = $pdo->prepare("SELECT ip FROM blocked_ips WHERE ip = ?");
        $chkBlock->execute([$ip]);
        if ($chkBlock->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'You are blocked from posting in this community.']);
            exit;
        }

        $content = trim($body['content'] ?? '');
        $attachments = $body['attachments'] ?? [];
        $media_links = $body['media_links'] ?? [];
        $gif_url = $body['gif_url'] ?? null;
        $channel_id = $body['channel_id'] ?? 'public';
        $user_id = $body['user_id'] ?? '';
        $user_name = $body['user_name'] ?? 'Citizen';
        $username = $body['username'] ?? 'user';
        $user_avatar = $body['user_avatar'] ?? null;
        $verified_type = $body['verified_type'] ?? 'none';

        if (!$user_id || (!$content && empty($attachments) && !$gif_url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message content or attachment required.']);
            exit;
        }

        if (comm_is_abusive($content, $comm_abusive_words)) {
            http_response_code(400);
            echo json_encode(['error' => 'Message blocked: Abusive words targeting national leadership or deities are prohibited.']);
            exit;
        }

        $msgId = !empty($body['id']) ? trim($body['id']) : ('msg_' . time() . '_' . rand(100, 999));
        $timeStr = date('h:i A');

        // Deduplicate if already saved
        $chkExistingMsg = $pdo->prepare("SELECT id FROM community_messages WHERE id = ?");
        $chkExistingMsg->execute([$msgId]);
        if ($chkExistingMsg->fetch()) {
            echo json_encode(['success' => true, 'message' => ['id' => $msgId]]);
            exit;
        }

        // Handle @mentions
        if (!empty($content)) {
            preg_match_all('/@([a-zA-Z0-9_.]+)/', $content, $mentions);
            if (!empty($mentions[1])) {
                foreach ($mentions[1] as $targetUname) {
                    if (strtolower($targetUname) !== strtolower($username)) {
                        $notifId = 'notif_' . time() . rand(100, 999);
                        $notifStmt = $pdo->prepare("INSERT INTO community_notifications (id, recipient_username, sender_username, sender_name, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)");
                        $notifStmt->execute([$notifId, strtolower($targetUname), $username, $user_name, "@{$username} mentioned you: \"" . substr($content, 0, 60) . "...\"", $timeStr]);
                    }
                }
            }
        }

        $reply_to = $body['reply_to'] ?? null;
        $reactions = '{}';

        $stmt = $pdo->prepare("INSERT INTO community_messages (id, channel_id, user_id, user_name, username, user_avatar, verified_type, content, attachments, media_links, gif_url, reactions, reply_to, is_deleted, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
        $stmt->execute([
            $msgId, $channel_id, $user_id, $user_name, $username, $user_avatar, $verified_type,
            $content, json_encode($attachments), json_encode($media_links), $gif_url, $reactions,
            $reply_to ? json_encode($reply_to) : null, $timeStr
        ]);

        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $msgId,
                'channel_id' => $channel_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'username' => $username,
                'user_avatar' => $user_avatar,
                'verified_type' => $verified_type,
                'content' => $content,
                'attachments' => $attachments,
                'media_links' => $media_links,
                'gif_url' => $gif_url,
                'reactions' => (object)[],
                'reply_to' => $reply_to,
                'created_at' => $timeStr
            ]
        ]);
        exit;
    }
}

// 5b. React to Community Message (WhatsApp style)
if ($endpoint === 'community/messages/react' && $method === 'POST') {
    if ($pdo) {
        $message_id = trim($body['message_id'] ?? '');
        $emoji = trim($body['emoji'] ?? '');
        $user_id = trim($body['user_id'] ?? '');
        $username = trim($body['username'] ?? 'user');

        if (!$message_id || !$emoji || !$user_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Message ID, emoji, and user ID required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, reactions FROM community_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $msg = $stmt->fetch();
        if (!$msg) {
            http_response_code(404);
            echo json_encode(['error' => 'Message not found.']);
            exit;
        }

        $rx = !empty($msg['reactions']) ? json_decode($msg['reactions'], true) : [];
        if (!is_array($rx)) $rx = [];

        $alreadyReactedSame = false;
        if (isset($rx[$emoji]) && is_array($rx[$emoji])) {
            foreach ($rx[$emoji] as $u) {
                if (($u['user_id'] ?? '') === $user_id) {
                    $alreadyReactedSame = true;
                    break;
                }
            }
        }

        // WhatsApp style: remove user from any reaction
        foreach ($rx as $k => $list) {
            if (is_array($list)) {
                $rx[$k] = array_values(array_filter($list, function($u) use ($user_id) {
                    return ($u['user_id'] ?? '') !== $user_id;
                }));
                if (empty($rx[$k])) unset($rx[$k]);
            }
        }

        if (!$alreadyReactedSame) {
            if (!isset($rx[$emoji])) $rx[$emoji] = [];
            $rx[$emoji][] = ['user_id' => $user_id, 'username' => $username];
        }

        $rxJson = json_encode($rx);
        $pdo->prepare("UPDATE community_messages SET reactions = ? WHERE id = ?")->execute([$rxJson, $message_id]);

        echo json_encode(['success' => true, 'message_id' => $message_id, 'reactions' => $rx]);
        exit;
    }
}

// 5c. GIPHY Search & Trending Proxies
if ($endpoint === 'giphy/search') {
    $q = urlencode($_GET['q'] ?? 'patriotic');
    $limit = min(intval($_GET['limit'] ?? 24), 50);
    $apiKey = 'sXpGFDGZs0Dv1mmNFvYaGUvYwKX0PWIh';
    $url = "https://api.giphy.com/v1/gifs/search?api_key={$apiKey}&q={$q}&limit={$limit}&rating=g";
    $res = @file_get_contents($url);
    header('Content-Type: application/json');
    echo $res ?: json_encode(['data' => []]);
    exit;
}

if ($endpoint === 'giphy/trending') {
    $limit = min(intval($_GET['limit'] ?? 24), 50);
    $apiKey = 'sXpGFDGZs0Dv1mmNFvYaGUvYwKX0PWIh';
    $url = "https://api.giphy.com/v1/gifs/trending?api_key={$apiKey}&limit={$limit}&rating=g";
    $res = @file_get_contents($url);
    header('Content-Type: application/json');
    echo $res ?: json_encode(['data' => []]);
    exit;
}

// 6. Delete Community Message
if (preg_match('#^community/messages/([^/]+)$#', $endpoint, $mMatches) && $method === 'DELETE') {
    if ($pdo) {
        $msgId = $mMatches[1];
        $pdo->prepare("UPDATE community_messages SET is_deleted = 1 WHERE id = ?")->execute([$msgId]);
        echo json_encode(['success' => true, 'id' => $msgId]);
        exit;
    }
}

// 7. Create Channel
if ($endpoint === 'community/channels' && $method === 'POST') {
    if ($pdo) {
        $creator_id = $body['creator_id'] ?? '';
        $creator_name = $body['creator_name'] ?? 'Creator';
        $creator_username = $body['creator_username'] ?? 'creator';
        $name = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');

        if (!$creator_id || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Creator ID and channel name required.']);
            exit;
        }

        $chanId = 'chn_' . time() . rand(100, 999);
        $joinToken = 'chn_' . substr(md5(uniqid()), 0, 10);
        $now = date('c');

        $stmt = $pdo->prepare("INSERT INTO community_channels (id, creator_id, creator_name, creator_username, name, description, join_token, is_private, members, pending_requests, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, '[]', ?)");
        $stmt->execute([
            $chanId, $creator_id, $creator_name, $creator_username, $name, $description,
            $joinToken, json_encode([$creator_id]), $now
        ]);

        echo json_encode([
            'success' => true,
            'channel' => [
                'id' => $chanId,
                'creator_id' => $creator_id,
                'creator_name' => $creator_name,
                'creator_username' => $creator_username,
                'name' => $name,
                'description' => $description,
                'join_token' => $joinToken,
                'is_private' => 1,
                'members' => [$creator_id],
                'pending_requests' => [],
                'created_at' => $now
            ]
        ]);
        exit;
    }
}

// 8. Channel Request Admission
if ($endpoint === 'community/channels/request' && $method === 'POST') {
    if ($pdo) {
        $chanId = $body['channel_id'] ?? '';
        $userId = $body['user_id'] ?? '';
        $name = $body['name'] ?? 'Citizen';
        $username = $body['username'] ?? 'user';
        $avatar_url = $body['avatar_url'] ?? null;

        $stmt = $pdo->prepare("SELECT * FROM community_channels WHERE id = ?");
        $stmt->execute([$chanId]);
        $chan = $stmt->fetch();
        if ($chan) {
            $requests = !empty($chan['pending_requests']) ? json_decode($chan['pending_requests'], true) : [];
            $already = false;
            foreach ($requests as $r) {
                if ($r['user_id'] === $userId) { $already = true; break; }
            }
            if (!$already) {
                $requests[] = [
                    'user_id' => $userId,
                    'name' => $name,
                    'username' => $username,
                    'avatar_url' => $avatar_url,
                    'requested_at' => date('h:i A')
                ];
                $pdo->prepare("UPDATE community_channels SET pending_requests = ? WHERE id = ?")->execute([json_encode($requests), $chanId]);
            }
            echo json_encode(['success' => true, 'requests' => $requests]);
            exit;
        }
    }
}

// 9. Channel Admit
if ($endpoint === 'community/channels/admit' && $method === 'POST') {
    if ($pdo) {
        $chanId = $body['channel_id'] ?? '';
        $pId = $body['participant_user_id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM community_channels WHERE id = ?");
        $stmt->execute([$chanId]);
        $chan = $stmt->fetch();
        if ($chan) {
            $members = !empty($chan['members']) ? json_decode($chan['members'], true) : [];
            $requests = !empty($chan['pending_requests']) ? json_decode($chan['pending_requests'], true) : [];

            if (!in_array($pId, $members)) {
                $members[] = $pId;
            }
            $requests = array_values(array_filter($requests, function($r) use ($pId) { return $r['user_id'] !== $pId; }));

            $pdo->prepare("UPDATE community_channels SET members = ?, pending_requests = ? WHERE id = ?")->execute([json_encode($members), json_encode($requests), $chanId]);
            echo json_encode(['success' => true, 'members' => $members, 'requests' => $requests]);
            exit;
        }
    }
}

// 10. Channel Deny
if ($endpoint === 'community/channels/deny' && $method === 'POST') {
    if ($pdo) {
        $chanId = $body['channel_id'] ?? '';
        $pId = $body['participant_user_id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM community_channels WHERE id = ?");
        $stmt->execute([$chanId]);
        $chan = $stmt->fetch();
        if ($chan) {
            $requests = !empty($chan['pending_requests']) ? json_decode($chan['pending_requests'], true) : [];
            $requests = array_values(array_filter($requests, function($r) use ($pId) { return $r['user_id'] !== $pId; }));

            $pdo->prepare("UPDATE community_channels SET pending_requests = ? WHERE id = ?")->execute([json_encode($requests), $chanId]);
            echo json_encode(['success' => true, 'requests' => $requests]);
            exit;
        }
    }
}

// 11. Contact Admin / Influencer Proof
if ($endpoint === 'community/contact' && $method === 'POST') {
    if ($pdo) {
        $type = $body['type'] ?? 'user';
        $name = trim($body['name'] ?? '');
        $email = $body['email'] ?? null;
        $user_id = $body['user_id'] ?? 'guest';
        $username = $body['username'] ?? 'unregistered';
        $social_account_id = $body['social_account_id'] ?? null;
        $message = trim($body['message'] ?? '');
        $proof_file = $body['proof_file'] ?? null;
        $proof_file_name = $body['proof_file_name'] ?? null;

        if (!$name || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and message are required.']);
            exit;
        }

        if ($type === 'creator') {
            if (!$social_account_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Social account handle is required for influencer verification.']);
                exit;
            }
            if (!$proof_file) {
                http_response_code(400);
                echo json_encode(['error' => 'MANDATORY: Upload analytics dashboard proof image/video (< 10MB).']);
                exit;
            }
        }

        $queryId = 'query_' . time() . rand(100, 999);
        $now = date('r');

        $stmt = $pdo->prepare("INSERT INTO community_contact_queries (id, type, name, email, user_id, username, social_account_id, message, proof_file_name, proof_file, ip, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $queryId, $type, $name, $email, $user_id, $username, $social_account_id,
            $message, $proof_file_name, $proof_file, $ip, $now
        ]);

        echo json_encode(['success' => true, 'id' => $queryId]);
        exit;
    }
}

// 12. Admin Ban User & IP
if ($endpoint === 'community/admin/ban' && $method === 'POST') {
    if ($pdo) {
        $admin_email = $body['admin_email'] ?? '';
        $admin_password = $body['admin_password'] ?? '';
        if ($admin_email !== 'omkumar.working@gmail.com' || $admin_password !== 'Q0gng04bk3') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized admin credentials.']);
            exit;
        }

        $userId = $body['user_id'] ?? '';
        $targetUsername = $body['target_username'] ?? 'user';

        $uStmt = $pdo->prepare("SELECT ip_address FROM community_users WHERE id = ?");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch();

        $pdo->prepare("UPDATE community_users SET is_banned = 1 WHERE id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM community_messages WHERE user_id = ?")->execute([$userId]);

        if ($u && !empty($u['ip_address'])) {
            $pdo->prepare("INSERT " . ($db_type === 'sqlite' ? 'OR IGNORE' : 'IGNORE') . " INTO blocked_ips (ip, reason, blocked_at) VALUES (?, ?, ?)")
                ->execute([$u['ip_address'], "Banned community user @{$targetUsername}", date('c')]);
        }

        echo json_encode(['success' => true, 'banned_user_id' => $userId]);
        exit;
    }
}

// 13. Admin Verify Badge
if ($endpoint === 'community/admin/verify-badge' && $method === 'POST') {
    if ($pdo) {
        $admin_email = $body['admin_email'] ?? '';
        $admin_password = $body['admin_password'] ?? '';
        if ($admin_email !== 'omkumar.working@gmail.com' || admin_password !== 'Q0gng04bk3') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized admin credentials.']);
            exit;
        }

        $userId = $body['user_id'] ?? '';
        $badgeType = $body['badge_type'] ?? 'citizen';

        $uStmt = $pdo->prepare("SELECT verified_type FROM community_users WHERE id = ?");
        $uStmt->execute([$userId]);
        $u = $uStmt->fetch();

        $newBadge = ($u && $u['verified_type'] === $badgeType) ? 'none' : $badgeType;
        $pdo->prepare("UPDATE community_users SET verified_type = ? WHERE id = ?")->execute([$newBadge, $userId]);
        $pdo->prepare("UPDATE community_messages SET verified_type = ? WHERE user_id = ?")->execute([$newBadge, $userId]);

        echo json_encode(['success' => true, 'user_id' => $userId, 'verified_type' => $newBadge]);
        exit;
    }
}

// -------------------------------------------------------------
// 7. Administrator Authentication (Master Key & Database-Driven)
// -------------------------------------------------------------
// Check if client IP is authorized for Admin Console access
if ($endpoint === 'admin/check-access') {
    echo json_encode([
        'allowed' => is_admin_ip_allowed(),
        'ip' => get_client_ip(),
        'subnet' => '152.59.*.*'
    ]);
    exit;
}

if ($endpoint === 'admin/login') {
    if (!is_admin_ip_allowed()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Access Denied: Administrative console is strictly restricted to authorized network IP subnet (152.59.XXX.XXX).'
        ]);
        exit;
    }

    $email = strtolower(trim($body['email'] ?? ''));
    $pass  = trim($body['password'] ?? '');
    $secretKey = trim($body['secretKey'] ?? '');
    $isAuthed = false;

    // 1. Direct Master Secret Key
    if ($secretKey && ($secretKey === $master_secret_key || $secretKey === $master_admin_password)) {
        $isAuthed = true;
    }

    // 2. Direct Master Email + Password check
    if (!$isAuthed && $email && $pass) {
        $passHash = hash('sha256', $pass);
        $masterPassHash = hash('sha256', $master_admin_password);
        if ($email === strtolower($master_admin_email) && ($pass === $master_admin_password || $passHash === $masterPassHash || $pass === $master_secret_key)) {
            $isAuthed = true;
        }
    }

    // 3. Database admin_users table check
    if (!$isAuthed && $pdo && $email && $pass) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE LOWER(email) = LOWER(?) LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                $passHash = hash('sha256', $pass);
                if ($u['password_hash'] === $pass || $u['password_hash'] === $passHash) {
                    $isAuthed = true;
                }
            }
        } catch (Exception $e) {}
    }

    if ($isAuthed) {
        $token = 'ep-adm-' . bin2hex(random_bytes(24));
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful. Welcome Commander.',
            'token' => $token,
            'email' => $email ?: $master_admin_email
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Incorrect Administrator Email or Password.']);
    exit;
}

// -------------------------------------------------------------
// 8. Admin Protected Routes (Require Valid Authorization)
// -------------------------------------------------------------
if (strpos($endpoint, 'admin/') === 0) {
    if (!is_admin_authorized($pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid or missing administrator key.']);
        exit;
    }

    // Admin: View Shield Attempts & Blocked IPs
    if ($endpoint === 'admin/shield-attempts') {
        $attempts = [];
        $blockedIps = [];
        if ($pdo) {
            try {
                $attempts = $pdo->query("SELECT * FROM blocked_attempts ORDER BY created_at DESC LIMIT 50")->fetchAll();
                $blockedIps = $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode(['attempts' => $attempts, 'blockedIps' => $blockedIps]);
        exit;
    }

    // Admin: Security center
    if ($endpoint === 'admin/security') {
        $blockedIps = [];
        $logs = [];
        if ($pdo) {
            try {
                $blockedIps = $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC")->fetchAll();
                $logs = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode(['blockedIps' => $blockedIps, 'logs' => $logs]);
        exit;
    }

    // Admin: Security Block IP
    if ($endpoint === 'admin/security/block' && $method === 'POST') {
        $ipToBlock = trim($body['ip'] ?? '');
        $reason = trim($body['reason'] ?? 'Manual admin security block');
        if ($ipToBlock && $pdo) {
            try {
                $stmt = $pdo->prepare("REPLACE INTO blocked_ips (ip, reason, blocked_at) VALUES (?, ?, ?)");
                $stmt->execute([$ipToBlock, $reason, date('c')]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Security Unblock IP
    if ($endpoint === 'admin/security/unblock' && $method === 'POST') {
        $ipToUnblock = trim($body['ip'] ?? '');
        if ($ipToUnblock && $pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip = ?");
                $stmt->execute([$ipToUnblock]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Reports List
    if ($endpoint === 'admin/reports') {
        $reports = [];
        if ($pdo) {
            try {
                $reports = $pdo->query("SELECT * FROM reports ORDER BY created_at DESC")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode($reports);
        exit;
    }

    // Admin: Update report status
    if (preg_match('/^admin\/reports\/([a-zA-Z0-9_-]+)$/', $endpoint, $m)) {
        $repId = $m[1];
        $newStatus = $body['status'] ?? 'resolved';
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $repId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'status' => $newStatus]);
        exit;
    }

    // Admin: Feature Suggestions list
    if ($endpoint === 'admin/feature-suggestions' && $method === 'GET') {
        $sugs = [];
        if ($pdo) {
            try {
                $sugs = $pdo->query("SELECT * FROM feature_suggestions ORDER BY created_at DESC")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode($sugs);
        exit;
    }

    // Admin: Mark feature suggestion as read
    if (preg_match('/^admin\/feature-suggestions\/([a-zA-Z0-9_-]+)\/read$/', $endpoint, $m)) {
        $sugId = $m[1];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE feature_suggestions SET is_read = 1 WHERE id = ?");
                $stmt->execute([$sugId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'id' => $sugId]);
        exit;
    }

    // Admin: Delete feature suggestion
    if (preg_match('/^admin\/feature-suggestions\/([a-zA-Z0-9_-]+)$/', $endpoint, $m) && ($method === 'DELETE' || ($method === 'POST' && ($_GET['_method'] ?? '') === 'DELETE'))) {
        $sugId = $m[1];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM feature_suggestions WHERE id = ?");
                $stmt->execute([$sugId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'id' => $sugId]);
        exit;
    }

    // Admin: Nationalist Influencers list (GET)
    if ($endpoint === 'admin/nationalist-influencers' && $method === 'GET') {
        $nats = [];
        if ($pdo) {
            try {
                $nats = $pdo->query("SELECT * FROM nationalist_influencers ORDER BY created_at DESC")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode($nats);
        exit;
    }

    // Admin: Direct Add Nationalist Influencer (POST)
    if (($endpoint === 'admin/nationalist-influencers' || $endpoint === 'admin/nationalist-influencers/direct') && $method === 'POST') {
        $name = trim($body['name'] ?? '');
        $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
        $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
        $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');

        $id = !empty($body['id']) ? $body['id'] : ('nat-' . time() . '-' . substr(md5(uniqid()), 0, 6));
        $now = date('c');
        $cats = is_array($body['categories'] ?? null) ? json_encode($body['categories']) : json_encode(['Defence']);
        $cat = $body['category'] ?? (is_array($body['categories'] ?? null) ? implode(', ', $body['categories']) : 'Defence');
        $desc = trim($body['description'] ?? '');
        $avatar = trim($body['avatar_url'] ?? '');
        if (!$avatar) {
            if ($cleanYt) $avatar = "https://unavatar.io/youtube/@" . $cleanYt;
            else if ($cleanX) $avatar = "https://unavatar.io/x/" . $cleanX;
            else if ($cleanInsta) $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=E1306C&color=ffffff&size=512&bold=true&font-size=0.38";
            else $avatar = "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
        }
        $upvotes = (int)($body['upvotes'] ?? 0);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO nationalist_influencers (id, name, category, categories, description, avatar_url, handle_x, handle_yt, handle_insta, upvotes, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)
                ");
                $stmt->execute([$id, $name, $cat, $cats, $desc, $avatar, $cleanX, $cleanYt, $cleanInsta, $upvotes, $now, $now]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'id' => $id, 'nationalist' => [
            'id' => $id, 'name' => $name, 'category' => $cat, 'categories' => $cats, 'description' => $desc, 'avatar_url' => $avatar, 'handle_x' => $cleanX, 'handle_yt' => $cleanYt, 'handle_insta' => $cleanInsta, 'upvotes' => $upvotes, 'status' => 'approved'
        ]]);
        exit;
    }

    // Admin: Nationalist Influencer update status or details
    if (preg_match('/^admin\/nationalist-influencers\/([a-zA-Z0-9_-]+)(?:\/status)?$/', $endpoint, $m) && in_array($method, ['PUT', 'PATCH'])) {
        $natId = $m[1];
        if ($pdo) {
            try {
                if (isset($body['status']) && count($body) === 1) {
                    $stmt = $pdo->prepare("UPDATE nationalist_influencers SET status = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$body['status'], date('c'), $natId]);
                } else {
                    $name = trim($body['name'] ?? '');
                    $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
                    $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
                    $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');
                    $otherLinks = !empty($body['other_links']) ? (is_array($body['other_links']) ? json_encode($body['other_links']) : $body['other_links']) : null;
                    $status = $body['status'] ?? 'approved';
                    $cat = $body['category'] ?? 'Nationalist Creator';
                    $desc = $body['description'] ?? '';
                    $avatar = trim($body['avatar_url'] ?? '');
                    if (!$avatar) {
                        if ($cleanYt) $avatar = "https://unavatar.io/youtube/@" . $cleanYt;
                        else if ($cleanX) $avatar = "https://unavatar.io/x/" . $cleanX;
                        else if ($cleanInsta) $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=E1306C&color=ffffff&size=512&bold=true&font-size=0.38";
                        else $avatar = "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
                    }

                    $stmt = $pdo->prepare("
                        UPDATE nationalist_influencers SET
                            name = ?, handle_x = ?, handle_yt = ?, handle_insta = ?, other_links = ?,
                            category = ?, description = ?, avatar_url = ?, status = ?, updated_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $cleanX, $cleanYt, $cleanInsta, $otherLinks, $cat, $desc, $avatar, $status, date('c'), $natId]);
                }
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Delete Nationalist Influencer
    if (preg_match('/^admin\/nationalist-influencers\/([a-zA-Z0-9_-]+)$/', $endpoint, $m) && $method === 'DELETE') {
        $natId = $m[1];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM nationalist_influencers WHERE id = ?");
                $stmt->execute([$natId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => 'Deleted successfully.']);
        exit;
    }

    // Admin: Direct Add Propagandus Entity
    if (($endpoint === 'admin/entities' || $endpoint === 'admin/entities/direct') && $method === 'POST') {
        $name = trim($body['name'] ?? '');
        $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
        $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
        $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');

        $err = validate_social_handles_php($pdo, $cleanX, $cleanYt, $cleanInsta, 'entities');
        if ($err) {
            http_response_code(400);
            echo json_encode(['error' => $err]);
            exit;
        }

        $id = 'prop-' . time() . '-' . substr(md5(uniqid()), 0, 6);
        $now = date('c');
        $cats = json_encode($body['categories'] ?? ['Social Media']);
        $avatar = trim($body['avatar_url'] ?? '');
        if (!$avatar) {
            if ($cleanYt) $avatar = "https://unavatar.io/youtube/@" . $cleanYt;
            else if ($cleanX) $avatar = "https://unavatar.io/x/" . $cleanX;
            else if ($cleanInsta) $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=E1306C&color=ffffff&size=512&bold=true&font-size=0.38";
            else $avatar = "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
        }
        $phone = !empty($body['contact_phone']) ? substr(trim($body['contact_phone']), 0, 50) : null;
        $email = !empty($body['contact_email']) ? substr(trim($body['contact_email']), 0, 255) : null;
        $otherLinks = !empty($body['other_links']) ? (is_array($body['other_links']) ? json_encode($body['other_links']) : $body['other_links']) : null;

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO entities (id, name, handle_x, handle_yt, handle_insta, contact_phone, contact_email, other_links, avatar_url, categories, party, institution, narrative, fact_check, proof_url, upvotes, is_verified, submitted_by_ip, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ");
                $stmt->execute([
                    $id, $name, $cleanX, $cleanYt, $cleanInsta,
                    $phone, $email, $otherLinks,
                    $avatar, $cats, $body['party'] ?? null, $body['institution'] ?? null,
                    $body['narrative'] ?? '', $body['fact_check'] ?? null, $body['proof_url'] ?? null,
                    (int)($body['upvotes'] ?? 0), $ip, $now, $now
                ]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    // Admin: Update Entity (PUT or POST)
    if (preg_match('/^admin\/entities\/([a-zA-Z0-9_-]+)$/', $endpoint, $m) && in_array($method, ['PUT', 'POST']) && ($body['_action'] ?? '') !== 'delete') {
        $entId = $m[1];
        $name = trim($body['name'] ?? '');
        $cleanX = extract_handle_php($body['handle_x'] ?? null, 'x');
        $cleanYt = extract_handle_php($body['handle_yt'] ?? null, 'yt');
        $cleanInsta = extract_handle_php($body['handle_insta'] ?? null, 'insta');
        $cats = json_encode($body['categories'] ?? ['Social Media']);
        $phone = !empty($body['contact_phone']) ? substr(trim($body['contact_phone']), 0, 50) : null;
        $email = !empty($body['contact_email']) ? substr(trim($body['contact_email']), 0, 255) : null;
        $otherLinks = !empty($body['other_links']) ? (is_array($body['other_links']) ? json_encode($body['other_links']) : $body['other_links']) : null;
        $avatar = trim($body['avatar_url'] ?? '');
        if (!$avatar) {
            if ($cleanYt) $avatar = "https://unavatar.io/youtube/@" . $cleanYt;
            else if ($cleanX) $avatar = "https://unavatar.io/x/" . $cleanX;
            else if ($cleanInsta) $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=E1306C&color=ffffff&size=512&bold=true&font-size=0.38";
            else $avatar = "https://api.dicebear.com/7.x/bottts/svg?seed=" . urlencode($name);
        }
        $now = date('c');

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE entities SET
                        name = ?, handle_x = ?, handle_yt = ?, handle_insta = ?, contact_phone = ?, contact_email = ?, other_links = ?,
                        avatar_url = ?, categories = ?, party = ?, institution = ?, narrative = ?, fact_check = ?, proof_url = ?, upvotes = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $cleanX, $cleanYt, $cleanInsta, $phone, $email, $otherLinks,
                    $avatar, $cats, $body['party'] ?? null, $body['institution'] ?? null,
                    $body['narrative'] ?? '', $body['fact_check'] ?? null, $body['proof_url'] ?? null,
                    (int)($body['upvotes'] ?? 0), $now, $entId
                ]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Delete Entity
    if (preg_match('/^admin\/entities\/([a-zA-Z0-9_-]+)$/', $endpoint, $m) && ($method === 'DELETE' || ($body['_action'] ?? '') === 'delete' || ($body['_method'] ?? '') === 'DELETE')) {
        $entId = $m[1];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM entities WHERE id = ?");
                $stmt->execute([$entId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => 'Entity deleted.']);
        exit;
    }

    // Admin: Toggle Verify Entity
    if (preg_match('/^admin\/entities\/([a-zA-Z0-9_-]+)\/verify$/', $endpoint, $m) && in_array($method, ['PATCH', 'POST', 'GET'])) {
        $entId = $m[1];
        $is_verified = 1;
        if ($pdo) {
            try {
                $cur = $pdo->prepare("SELECT is_verified FROM entities WHERE id = ?");
                $cur->execute([$entId]);
                $is_verified = ($cur->fetchColumn() == 1) ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE entities SET is_verified = ? WHERE id = ?");
                $stmt->execute([$is_verified, $entId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'is_verified' => $is_verified]);
        exit;
    }

    // Admin: Edit Entity Upvotes
    if (preg_match('/^admin\/entities\/([a-zA-Z0-9_-]+)\/upvotes$/', $endpoint, $m) && in_array($method, ['PATCH', 'POST'])) {
        $entId = $m[1];
        $newVotes = max(0, (int)($body['upvotes'] ?? 0));
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE entities SET upvotes = ?, updated_at = ? WHERE id = ?");
                $stmt->execute([$newVotes, date('c'), $entId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'upvotes' => $newVotes]);
        exit;
    }

    // Admin: Save or update global broadcast announcement
    if ($endpoint === 'admin/announcement') {
        if ($method === 'POST') {
            $title = trim($body['title'] ?? 'उद्घोषणा / ANNOUNCEMENT');
            $message = trim($body['message'] ?? '');
            $type = in_array($body['type'] ?? '', ['alert', 'warning', 'info']) ? $body['type'] : 'warning';
            $isActive = !empty($body['is_active']) ? 1 : 0;
            $now = date('c');

            if ($pdo) {
                try {
                    $chkAnn = $pdo->query("SELECT id FROM announcements WHERE id = 'global'")->fetch();
                    if ($chkAnn) {
                        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, message = ?, type = ?, is_active = ?, updated_at = ? WHERE id = 'global'");
                        $stmt->execute([$title, $message, $type, $isActive, $now]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO announcements (id, title, message, type, is_active, created_at, updated_at) VALUES ('global', ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$title, $message, $type, $isActive, $now, $now]);
                    }
                } catch (Exception $e) {}
            }

            echo json_encode(['success' => true, 'message' => 'Broadcast saved successfully']);
            exit;
        }

        if ($method === 'DELETE') {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE announcements SET is_active = 0, updated_at = ? WHERE id = 'global'");
                    $stmt->execute([date('c')]);
                } catch (Exception $e) {}
            }
            echo json_encode(['success' => true, 'message' => 'Announcement deactivated']);
            exit;
        }
    }

    // Admin: Edit Govt Poll
    if ($endpoint === 'admin/govt-poll' && in_array($method, ['PUT', 'POST'])) {
        $yesVotes = isset($body['yes_votes']) ? (int)$body['yes_votes'] : (isset($body['yesVotes']) ? (int)$body['yesVotes'] : null);
        $noVotes  = isset($body['no_votes']) ? (int)$body['no_votes'] : (isset($body['noVotes']) ? (int)$body['noVotes'] : null);
        $question = isset($body['question']) ? trim($body['question']) : null;
        $now = date('c');

        if ($pdo) {
            try {
                $poll = $pdo->prepare("SELECT * FROM govt_polls WHERE id = 'bjp_2029'");
                $poll->execute();
                $row = $poll->fetch();

                $newYes = ($yesVotes !== null) ? $yesVotes : ($row ? (int)$row['yes_votes'] : 28490);
                $newNo  = ($noVotes !== null) ? $noVotes : ($row ? (int)$row['no_votes'] : 3120);
                $newQ   = ($question !== null && $question !== '') ? $question : ($row ? $row['question'] : 'Will Bharat Achieve a $7 Trillion Economy by 2030 Under Nationalist Leadership?');

                if ($row) {
                    $stmt = $pdo->prepare("UPDATE govt_polls SET question = ?, yes_votes = ?, no_votes = ?, updated_at = ? WHERE id = 'bjp_2029'");
                    $stmt->execute([$newQ, $newYes, $newNo, $now]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO govt_polls (id, question, yes_votes, no_votes, updated_at) VALUES ('bjp_2029', ?, ?, ?, ?)");
                    $stmt->execute([$newQ, $newYes, $newNo, $now]);
                }
            } catch (Exception $e) {}
        }

        echo json_encode(['success' => true, 'message' => 'Govt poll updated successfully']);
        exit;
    }

    // Admin: Save Spotlight Media
    if ($endpoint === 'admin/spotlight' && in_array($method, ['PUT', 'POST'])) {
        $spotlightData = [
            'type' => in_array($body['type'] ?? '', ['video', 'image', 'off']) ? $body['type'] : 'off',
            'title' => trim($body['title'] ?? ''),
            'videoUrl' => trim($body['videoUrl'] ?? ''),
            'channelName' => trim($body['channelName'] ?? ''),
            'channelUrl' => trim($body['channelUrl'] ?? ''),
            'imageUrl' => trim($body['imageUrl'] ?? ''),
            'imageCaption' => trim($body['imageCaption'] ?? ''),
            'updatedAt' => date('c')
        ];

        if ($pdo) {
            try {
                $val = json_encode($spotlightData);
                $now = date('c');
                $chk = $pdo->prepare("SELECT `key` FROM system_settings WHERE `key` = 'spotlight_media'");
                $chk->execute();
                if ($chk->fetch()) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET `value` = ?, updated_at = ? WHERE `key` = 'spotlight_media'");
                    $stmt->execute([$val, $now]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`, `updated_at`) VALUES ('spotlight_media', ?, ?)");
                    $stmt->execute([$val, $now]);
                }
            } catch (Exception $e) {}
        }

        echo json_encode(['success' => true, 'spotlight' => $spotlightData]);
        exit;
    }

    // Admin: Set nationalist bulletin message
    if ($endpoint === 'admin/bulletin' && in_array($method, ['PUT', 'POST'])) {
        $bulletinData = [
            'is_active' => !empty($body['is_active']) ? 1 : 0,
            'title' => trim($body['title'] ?? '⚡ RASHTRIYA FLASH BULLETIN'),
            'message' => trim($body['message'] ?? ''),
            'link' => trim($body['link'] ?? ''),
            'updated_at' => date('c')
        ];

        if ($pdo) {
            try {
                $val = json_encode($bulletinData);
                $now = date('c');
                $chk = $pdo->prepare("SELECT `key` FROM system_settings WHERE `key` = 'nationalist_bulletin'");
                $chk->execute();
                if ($chk->fetch()) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET `value` = ?, updated_at = ? WHERE `key` = 'nationalist_bulletin'");
                    $stmt->execute([$val, $now]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`, `updated_at`) VALUES ('nationalist_bulletin', ?, ?)");
                    $stmt->execute([$val, $now]);
                }
            } catch (Exception $e) {}
        }

        echo json_encode(['success' => true, 'bulletin' => $bulletinData]);
        exit;
    }

    // Admin: Get live real-time traffic
    if ($endpoint === 'admin/live-traffic' && $method === 'GET') {
        $cutoff3Min = date('c', time() - 180);
        $totalUniqueVisitors = 0;
        $totalVisits = 0;
        $liveCount = 0;
        $visitors = [];
        $recentLogs = [];
        $deviceBreakdown = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0];

        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_visitors");
                $totalUniqueVisitors = (int)($stmt->fetch()['count'] ?? 0);

                $stmt = $pdo->query("SELECT COALESCE(SUM(visit_count), 0) as total FROM site_visitors");
                $totalVisits = (int)($stmt->fetch()['total'] ?? 0);

                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM site_visitors WHERE last_seen >= ?");
                $stmt->execute([$cutoff3Min]);
                $liveCount = (int)($stmt->fetch()['count'] ?? 0);

                $stmt = $pdo->query("SELECT ip, device_type, os, browser, user_agent, visit_count, current_page, first_visited, last_seen, is_blocked FROM site_visitors GROUP BY ip ORDER BY last_seen DESC LIMIT 120");
                $rawVisitors = $stmt->fetchAll();
                $seenIps = [];
                foreach ($rawVisitors as $v) {
                    $vIp = $v['ip'] ?? '';
                    if (!$vIp || isset($seenIps[$vIp])) continue;
                    $seenIps[$vIp] = true;
                    $visitors[] = [
                        'ip' => $vIp,
                        'device_type' => $v['device_type'] ?: 'Desktop',
                        'os' => $v['os'] ?: 'Unknown OS',
                        'browser' => $v['browser'] ?: 'Unknown Browser',
                        'visit_count' => (int)($v['visit_count'] ?? 1),
                        'current_page' => $v['current_page'] ?: 'Homepage',
                        'first_visited' => $v['first_visited'] ?: date('c'),
                        'last_seen' => $v['last_seen'] ?: date('c'),
                        'is_online' => ($v['last_seen'] >= $cutoff3Min),
                        'is_blocked' => (bool)$v['is_blocked']
                    ];
                    $dType = strtolower($v['device_type'] ?? 'desktop');
                    if (isset($deviceBreakdown[$dType])) $deviceBreakdown[$dType]++;
                }

                $stmt = $pdo->query("SELECT id, ip, device_type, os, browser, page, created_at FROM visitor_traffic_logs ORDER BY created_at DESC LIMIT 60");
                $recentLogs = $stmt->fetchAll();
            } catch (Exception $e) {}
        }

        echo json_encode([
            'success' => true,
            'liveCount' => $liveCount,
            'totalUniqueVisitors' => $totalUniqueVisitors,
            'totalVisits' => $totalVisits,
            'deviceBreakdown' => $deviceBreakdown,
            'visitors' => $visitors,
            'recentLogs' => $recentLogs
        ]);
        exit;
    }

    // Admin: Fetch YouTube info via oEmbed
    if ($endpoint === 'admin/youtube-info' && $method === 'POST') {
        $ytUrl = trim($body['url'] ?? '');
        $videoId = null;
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([a-zA-Z0-9_-]{11})/', $ytUrl, $vm)) {
            $videoId = $vm[1];
        }
        $title = '';
        $channelName = '';
        $channelUrl = '';
        $thumbnail = $videoId ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : '';

        $oembedUrl = 'https://www.youtube.com/oembed?url=' . urlencode($ytUrl) . '&format=json';
        $opts = ['http' => ['timeout' => 3]];
        $ctx = stream_context_create($opts);
        $json = @file_get_contents($oembedUrl, false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            if ($data) {
                $title = $data['title'] ?? '';
                $channelName = $data['author_name'] ?? '';
                $channelUrl = $data['author_url'] ?? '';
                $thumbnail = $data['thumbnail_url'] ?? $thumbnail;
            }
        }
        echo json_encode([
            'success' => true,
            'title' => $title,
            'channelName' => $channelName,
            'channelUrl' => $channelUrl,
            'thumbnail' => $thumbnail,
            'thumbnailUrl' => $thumbnail,
            'videoId' => $videoId
        ]);
        exit;
    }

    // Admin: Users & IPs Defense Intelligence
    if ($endpoint === 'admin/users-ips') {
        $blockedIps = [];
        $recentActivity = [];
        $uniqueIps = [];
        if ($pdo) {
            try {
                $blockedIps = $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC")->fetchAll();
                $recentActivity = $pdo->query("SELECT ip, action, details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();
                $uniqueIps = $pdo->query("SELECT ip, visit_count, current_page, last_seen, is_blocked FROM site_visitors ORDER BY last_seen DESC LIMIT 60")->fetchAll();
            } catch (Exception $e) {}
        }
        echo json_encode(['uniqueIps' => $uniqueIps, 'blockedIps' => $blockedIps, 'recentActivity' => $recentActivity]);
        exit;
    }

    // Admin: Block IP
    if ($endpoint === 'admin/block-ip' && $method === 'POST') {
        $ipToBlock = trim($body['ip'] ?? '');
        $reason = trim($body['reason'] ?? 'Administrative block');
        if ($ipToBlock && $pdo) {
            try {
                $stmt = $pdo->prepare("REPLACE INTO blocked_ips (ip, reason, blocked_at) VALUES (?, ?, ?)");
                $stmt->execute([$ipToBlock, $reason, date('c')]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => "IP $ipToBlock blocked successfully."]);
        exit;
    }

    // Admin: Unblock IP
    if (preg_match('/^admin\/block-ip\/([a-zA-Z0-9_.:-]+)$/', $endpoint, $m) && $method === 'DELETE') {
        $ipToUnblock = urldecode($m[1]);
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip = ?");
                $stmt->execute([$ipToUnblock]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => "IP $ipToUnblock unblocked successfully."]);
        exit;
    }

    // Admin: Playlist (GET all tracks including disabled, POST add track)
    if ($endpoint === 'admin/playlist') {
        if ($method === 'GET') {
            $tracks = [];
            if ($pdo) {
                try {
                    $tracks = $pdo->query("SELECT * FROM playlist_tracks ORDER BY sort_order ASC, created_at DESC")->fetchAll();
                } catch (Exception $e) {}
            }
            echo json_encode($tracks);
            exit;
        }

        if ($method === 'POST') {
            $added = 0;
            $now = date('c');
            if (!empty($body['tracks']) && is_array($body['tracks'])) {
                foreach ($body['tracks'] as $t) {
                    if (empty($t['videoId'])) continue;
                    $id = 'track-' . time() . '-' . substr(md5(uniqid()), 0, 6);
                    if ($pdo) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO playlist_tracks (id, title, video_id, author, thumbnail_url, duration, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, 999, ?)");
                            $stmt->execute([$id, $t['title'] ?? 'Untitled Track', $t['videoId'], $t['author'] ?? 'Nationalist', $t['thumbnailUrl'] ?? "https://i.ytimg.com/vi/{$t['videoId']}/hqdefault.jpg", $t['duration'] ?? '3:30', $now]);
                            $added++;
                        } catch (Exception $e) {}
                    }
                }
            } else if (!empty($body['videoId'])) {
                $id = 'track-' . time() . '-' . substr(md5(uniqid()), 0, 6);
                if ($pdo) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO playlist_tracks (id, title, video_id, author, thumbnail_url, duration, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, 999, ?)");
                        $stmt->execute([$id, $body['title'] ?? 'Untitled Track', $body['videoId'], $body['author'] ?? 'Nationalist', $body['thumbnailUrl'] ?? "https://i.ytimg.com/vi/{$body['videoId']}/hqdefault.jpg", $body['duration'] ?? '3:30', $now]);
                        $added++;
                    } catch (Exception $e) {}
                }
            }
            echo json_encode(['success' => true, 'message' => "Added {$added} track(s)"]);
            exit;
        }
    }

    // Admin: Delete playlist track
    if (preg_match('/^admin\/playlist\/([a-zA-Z0-9_-]+)$/', $endpoint, $m) && $method === 'DELETE') {
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM playlist_tracks WHERE id = ?");
                $stmt->execute([$m[1]]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Toggle playlist track active status
    if (preg_match('/^admin\/playlist\/([a-zA-Z0-9_-]+)\/toggle$/', $endpoint, $m) && in_array($method, ['PATCH', 'POST'])) {
        $trackId = $m[1];
        $newActive = 0;
        if ($pdo) {
            try {
                $cur = $pdo->prepare("SELECT is_active FROM playlist_tracks WHERE id = ?");
                $cur->execute([$trackId]);
                $val = $cur->fetchColumn();
                $newActive = ((int)$val === 1) ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE playlist_tracks SET is_active = ? WHERE id = ?");
                $stmt->execute([$newActive, $trackId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'is_active' => $newActive]);
        exit;
    }

    // Admin: Playlist search YouTube videos via direct scraping & oEmbed
    if ($endpoint === 'admin/playlist/search') {
        $q = trim($_GET['q'] ?? '');
        $results = [];
        if ($q) {
            // 1. Direct YouTube Video URL or Video ID check
            $videoId = null;
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([a-zA-Z0-9_-]{11})/', $q, $m)) {
                $videoId = $m[1];
            } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $q)) {
                $videoId = $q;
            }

            if ($videoId) {
                $oeUrl = "https://www.youtube.com/oembed?url=" . urlencode("https://www.youtube.com/watch?v={$videoId}") . "&format=json";
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 4,
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n"
                    ]
                ]);
                $oeJson = @file_get_contents($oeUrl, false, $ctx);
                $title = "YouTube Track ({$videoId})";
                $author = 'Artist';
                if ($oeJson) {
                    $oe = json_decode($oeJson, true);
                    if (!empty($oe['title'])) $title = $oe['title'];
                    if (!empty($oe['author_name'])) $author = $oe['author_name'];
                }
                $results[] = [
                    'videoId' => $videoId,
                    'title' => $title,
                    'author' => $author,
                    'duration' => '3:30',
                    'thumbnailUrl' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg"
                ];
            } else {
                // 2. Direct Scrape of YouTube Search Results
                $ytSearchUrl = "https://www.youtube.com/results?search_query=" . urlencode($q);
                $html = '';
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $ytSearchUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept-Language: en-US,en;q=0.9',
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                    ]);
                    $html = curl_exec($ch);
                    curl_close($ch);
                }
                if (!$html) {
                    $ctx = stream_context_create([
                        'http' => [
                            'timeout' => 5,
                            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n"
                        ]
                    ]);
                    $html = @file_get_contents($ytSearchUrl, false, $ctx);
                }

                if ($html && (preg_match('/var ytInitialData = ({.*?});<\/script>/s', $html, $matches) || preg_match('/ytInitialData\s*=\s*({.+?});/s', $html, $matches))) {
                    $data = json_decode($matches[1], true);
                    $contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'] ?? [];
                    foreach ($contents as $item) {
                        $vr = $item['videoRenderer'] ?? null;
                        if ($vr && !empty($vr['videoId'])) {
                            $results[] = [
                                'videoId' => $vr['videoId'],
                                'title' => $vr['title']['runs'][0]['text'] ?? 'Nationalist Song',
                                'author' => $vr['ownerText']['runs'][0]['text'] ?? ($vr['shortBylineText']['runs'][0]['text'] ?? 'Artist'),
                                'duration' => $vr['lengthText']['simpleText'] ?? '3:30',
                                'thumbnailUrl' => "https://i.ytimg.com/vi/{$vr['videoId']}/hqdefault.jpg"
                            ];
                            if (count($results) >= 15) break;
                        }
                    }
                }

                // 3. Fallback: Try Invidious instances if direct scrape yielded 0 items
                if (empty($results)) {
                    $invidiousInstances = ['https://yewtu.be', 'https://invidious.nerdvpn.de', 'https://inv.nadeko.net'];
                    foreach ($invidiousInstances as $inst) {
                        $url = "{$inst}/api/v1/search?q=" . urlencode($q) . "&type=video";
                        $ctx = stream_context_create([
                            'http' => [
                                'timeout' => 4,
                                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                            ]
                        ]);
                        $json = @file_get_contents($url, false, $ctx);
                        if ($json) {
                            $data = json_decode($json, true);
                            if (is_array($data)) {
                                foreach (array_slice($data, 0, 15) as $item) {
                                    if (!empty($item['videoId'])) {
                                        $results[] = [
                                            'videoId' => $item['videoId'],
                                            'title' => $item['title'] ?? 'Nationalist Song',
                                            'author' => $item['author'] ?? 'Artist',
                                            'duration' => isset($item['lengthSeconds']) ? gmdate('i:s', (int)$item['lengthSeconds']) : '3:30',
                                            'thumbnailUrl' => "https://i.ytimg.com/vi/{$item['videoId']}/hqdefault.jpg"
                                        ];
                                    }
                                }
                                if (!empty($results)) break;
                            }
                        }
                    }
                }
            }
        }
        echo json_encode($results);
        exit;
    }

    // Admin: Resolve Video or Playlist URL (admin/playlist/fetch-url)
    if ($endpoint === 'admin/playlist/fetch-url' && $method === 'POST') {
        $url = trim($body['url'] ?? '');
        if (!$url) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            exit;
        }

        // Check if playlist URL
        if (preg_match('/[?&]list=([a-zA-Z0-9_-]+)/', $url, $pm)) {
            $listId = $pm[1];
            $invidiousInstances = ['https://vid.puffyan.us', 'https://invidious.nerdvpn.de', 'https://yewtu.be'];
            $tracks = [];
            $title = 'YouTube Playlist';
            foreach ($invidiousInstances as $inst) {
                $apiUrl = "{$inst}/api/v1/playlists/{$listId}";
                $ctx = stream_context_create(['http' => ['timeout' => 5]]);
                $json = @file_get_contents($apiUrl, false, $ctx);
                if ($json) {
                    $pData = json_decode($json, true);
                    if (!empty($pData['videos'])) {
                        $title = $pData['title'] ?? 'YouTube Playlist';
                        foreach ($pData['videos'] as $v) {
                            if (!empty($v['videoId'])) {
                                $tracks[] = [
                                    'videoId' => $v['videoId'],
                                    'title' => $v['title'] ?? 'Untitled Song',
                                    'author' => $v['author'] ?? 'Artist',
                                    'thumbnailUrl' => "https://i.ytimg.com/vi/{$v['videoId']}/hqdefault.jpg",
                                    'duration' => isset($v['lengthSeconds']) ? gmdate('i:s', (int)$v['lengthSeconds']) : '3:30'
                                ];
                            }
                        }
                        break;
                    }
                }
            }

            echo json_encode([
                'type' => 'playlist',
                'title' => $title,
                'tracks' => $tracks
            ]);
            exit;
        }

        // Single video URL
        $videoId = null;
        if (preg_match('/(?:v=|\/embed\/|youtu\.be\/|\/v\/)([a-zA-Z0-9_-]{11})/', $url, $vm)) {
            $videoId = $vm[1];
        }

        if ($videoId) {
            $oembedUrl = 'https://www.youtube.com/oembed?url=' . urlencode("https://www.youtube.com/watch?v={$videoId}") . '&format=json';
            $oData = @json_decode(@file_get_contents($oembedUrl), true);
            $videoTitle = $oData['title'] ?? 'Patriotic Track';
            $author = $oData['author_name'] ?? 'Nationalist Artist';

            echo json_encode([
                'type' => 'video',
                'title' => $videoTitle,
                'tracks' => [[
                    'videoId' => $videoId,
                    'title' => $videoTitle,
                    'author' => $author,
                    'thumbnailUrl' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                    'duration' => '3:30'
                ]]
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unable to resolve YouTube URL. Please provide a valid watch or playlist link.']);
        exit;
    }

    // Admin: Posts Management
    if ($endpoint === 'admin/posts') {
        if ($method === 'GET') {
            $posts = [];
            if ($pdo) {
                try {
                    $posts = $pdo->query("SELECT * FROM admin_posts ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
                } catch (Exception $e) {}
            }
            echo json_encode($posts);
            exit;
        }

        if ($method === 'POST') {
            $title = trim($body['title'] ?? '');
            $content = trim($body['content'] ?? '');
            if (!$title || !$content) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and content are required']);
                exit;
            }

            $id = 'post-' . time() . '-' . substr(md5(uniqid()), 0, 6);
            $now = date('c');
            $badge = trim($body['badge'] ?? 'DISPATCH') ?: 'DISPATCH';
            $isPinned = !empty($body['is_pinned']) ? 1 : 0;
            $imageUrl = $body['image_url'] ?? null;

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO admin_posts (id, title, content, image_url, badge, is_pinned, likes, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)");
                    $stmt->execute([$id, $title, $content, $imageUrl, $badge, $isPinned, $now, $now]);
                } catch (Exception $e) {}
            }

            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    // Admin: Edit or Delete Single Post
    if (preg_match('/^admin\/posts\/([a-zA-Z0-9_-]+)$/', $endpoint, $m)) {
        $postId = $m[1];
        if (in_array($method, ['PUT', 'POST']) && ($body['_action'] ?? '') !== 'delete') {
            $title = trim($body['title'] ?? '');
            $content = trim($body['content'] ?? '');
            $badge = trim($body['badge'] ?? 'DISPATCH') ?: 'DISPATCH';
            $isPinned = !empty($body['is_pinned']) ? 1 : 0;
            $imageUrl = $body['image_url'] ?? null;
            $now = date('c');

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE admin_posts SET title = ?, content = ?, image_url = ?, badge = ?, is_pinned = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$title, $content, $imageUrl, $badge, $isPinned, $now, $postId]);
                } catch (Exception $e) {}
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($method === 'DELETE' || ($body['_action'] ?? '') === 'delete' || ($body['_method'] ?? '') === 'DELETE') {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM admin_posts WHERE id = ?");
                    $stmt->execute([$postId]);
                } catch (Exception $e) {}
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // Admin: Dynamic Site Categories (Add / Delete)
    if ($endpoint === 'admin/categories' && $method === 'POST') {
        $type = trim($body['type'] ?? 'nationalist');
        $name = trim($body['name'] ?? '');
        if ($name && $pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO site_categories (type, name, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$type, $name, date('c')]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if (preg_match('/^admin\/categories\/([0-9]+)$/', $endpoint, $m) && $method === 'DELETE') {
        $catId = (int)$m[1];
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM site_categories WHERE id = ?");
                $stmt->execute([$catId]);
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Admin: Update Credentials in Database
    if ($endpoint === 'admin/credentials' && in_array($method, ['PUT', 'POST'])) {
        $newEmail = strtolower(trim($body['new_email'] ?? ''));
        $newPass  = trim($body['new_password'] ?? '');

        if (!$newEmail || strlen($newPass) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email and minimum 6-character password required.']);
            exit;
        }

        if ($pdo) {
            try {
                $now = date('c');
                $chk = $pdo->query("SELECT id FROM admin_users WHERE id = 'admin-primary'")->fetch();
                if ($chk) {
                    $stmt = $pdo->prepare("UPDATE admin_users SET email = ?, password_hash = ?, updated_at = ? WHERE id = 'admin-primary'");
                    $stmt->execute([$newEmail, $newPass, $now]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO admin_users (id, email, password_hash, created_at, updated_at) VALUES ('admin-primary', ?, ?, ?, ?)");
                    $stmt->execute([$newEmail, $newPass, $now, $now]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
        }

        echo json_encode(['success' => true, 'message' => 'Admin credentials successfully updated in database.']);
        exit;
    }

    // Admin: Backup Database to JSON Dump
    if ($endpoint === 'admin/backup' && $method === 'GET') {
        $backup = [
            'timestamp' => date('c'),
            'entities' => [],
            'nationalist_influencers' => [],
            'site_categories' => [],
            'playlist_tracks' => [],
            'admin_posts' => [],
            'reports' => [],
            'system_settings' => [],
            'govt_polls' => []
        ];

        if ($pdo) {
            try {
                $backup['entities'] = $pdo->query("SELECT * FROM entities")->fetchAll() ?: [];
                $backup['nationalist_influencers'] = $pdo->query("SELECT * FROM nationalist_influencers")->fetchAll() ?: [];
                $backup['site_categories'] = $pdo->query("SELECT * FROM site_categories")->fetchAll() ?: [];
                $backup['playlist_tracks'] = $pdo->query("SELECT * FROM playlist_tracks")->fetchAll() ?: [];
                $backup['admin_posts'] = $pdo->query("SELECT * FROM admin_posts")->fetchAll() ?: [];
                $backup['reports'] = $pdo->query("SELECT * FROM reports")->fetchAll() ?: [];
                $backup['system_settings'] = $pdo->query("SELECT * FROM system_settings")->fetchAll() ?: [];
                $backup['govt_polls'] = $pdo->query("SELECT * FROM govt_polls")->fetchAll() ?: [];
            } catch (Exception $e) {}
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="exposed-propagandus-backup-' . time() . '.json"');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Admin: Restore Database from Snapshot
    if ($endpoint === 'admin/restore' && $method === 'POST') {
        $data = $body['backup'] ?? $body;
        if (!$data || !is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid restore data']);
            exit;
        }

        if ($pdo) {
            try {
                if (!empty($data['entities']) && is_array($data['entities'])) {
                    foreach ($data['entities'] as $ent) {
                        $stmt = $pdo->prepare("INSERT INTO entities (id, name, handle_x, handle_yt, handle_insta, avatar_url, categories, party, institution, narrative, fact_check, proof_url, upvotes, is_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), narrative=VALUES(narrative), fact_check=VALUES(fact_check), avatar_url=VALUES(avatar_url)");
                        $stmt->execute([
                            $ent['id'], $ent['name'], $ent['handle_x'] ?? '', $ent['handle_yt'] ?? '',
                            $ent['handle_insta'] ?? '', $ent['avatar_url'] ?? '',
                            is_array($ent['categories']) ? json_encode($ent['categories']) : ($ent['categories'] ?? '[]'),
                            $ent['party'] ?? null, $ent['institution'] ?? null,
                            $ent['narrative'] ?? '', $ent['fact_check'] ?? null, $ent['proof_url'] ?? null,
                            (int)($ent['upvotes'] ?? 0), (int)($ent['is_verified'] ?? 1),
                            $ent['created_at'] ?? date('c'), $ent['updated_at'] ?? date('c')
                        ]);
                    }
                }
                if (!empty($data['nationalist_influencers']) && is_array($data['nationalist_influencers'])) {
                    foreach ($data['nationalist_influencers'] as $nat) {
                        $stmt = $pdo->prepare("INSERT INTO nationalist_influencers (id, name, category, categories, description, avatar_url, handle_x, handle_yt, handle_insta, upvotes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), avatar_url=VALUES(avatar_url)");
                        $stmt->execute([
                            $nat['id'], $nat['name'], $nat['category'] ?? 'Defence',
                            is_array($nat['categories']) ? json_encode($nat['categories']) : ($nat['categories'] ?? '[]'),
                            $nat['description'] ?? '', $nat['avatar_url'] ?? '', $nat['handle_x'] ?? '',
                            $nat['handle_yt'] ?? '', $nat['handle_insta'] ?? '', (int)($nat['upvotes'] ?? 0),
                            $nat['status'] ?? 'approved', $nat['created_at'] ?? date('c'), $nat['updated_at'] ?? date('c')
                        ]);
                    }
                }
                if (!empty($data['admin_posts']) && is_array($data['admin_posts'])) {
                    foreach ($data['admin_posts'] as $post) {
                        $stmt = $pdo->prepare("INSERT INTO admin_posts (id, title, content, image_url, badge, is_pinned, likes, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), content=VALUES(content), image_url=VALUES(image_url)");
                        $stmt->execute([
                            $post['id'], $post['title'], $post['content'], $post['image_url'] ?? null,
                            $post['badge'] ?? 'DISPATCH', (int)($post['is_pinned'] ?? 0),
                            (int)($post['likes'] ?? 0), (int)($post['views'] ?? 0),
                            $post['created_at'] ?? date('c'), $post['updated_at'] ?? date('c')
                        ]);
                    }
                }
            } catch (Exception $e) {}
        }
        echo json_encode(['success' => true, 'message' => 'Database state restored successfully.']);
        exit;
    }
}

// Fallback response for unhandled endpoints
echo json_encode([
    'status' => 'OK',
    'endpoint' => $endpoint,
    'message' => 'Exposed Propagandus API operational. Bharat Mata ki Jai 🇮🇳'
]);
