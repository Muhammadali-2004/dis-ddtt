<?php
// =====================================================
// АРХИВИ КОРҲОИ ИЛМӢ — ДИС ДДТТ
// Танзимот: PostgreSQL Cloud (Neon)
// =====================================================

if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
    
    // ─── Маълумоти Neon PostgreSQL (env vars барои деплой) ───
    define('DB_HOST', getenv('DB_HOST') ?: 'ep-holy-moon-abf98mmg.eu-west-2.aws.neon.tech');
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'neondb');
    define('DB_USER', getenv('DB_USER') ?: 'neondb_owner');
    define('DB_PASS', getenv('DB_PASS') ?: 'npg_j7JhlFuB3Dot');
    
    // ─── Танзимоти асосӣ ───
    define('BASE_URL', '');
    define('UPLOAD_DIR', __DIR__ . '/../uploads/');
    define('MAX_SIZE', 20 * 1024 * 1024);
    define('SITE_NAME', 'Архиви Корҳои Илмӣ');
    define('SITE_FULL', 'Системаи Иттилоотии Архиви Корҳои Илмии Донишҷӯён');
}

// ─── Пайвасти PostgreSQL ───
if (!function_exists('db')) {
    function db(): PDO {
        static $pdo;
        if (!$pdo) {
            try {
                $dsn = "pgsql:host=".DB_HOST.";port=".DB_PORT.
                       ";dbname=".DB_NAME.";sslmode=require";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec("SET NAMES 'UTF8'");
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id SERIAL PRIMARY KEY,
                    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    work_id INT REFERENCES works(id) ON DELETE SET NULL,
                    type VARCHAR(20) NOT NULL,
                    message TEXT DEFAULT '',
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT NOW()
                )");
                $pdo->exec("ALTER TABLE works ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64)");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_works_file_hash ON works(file_hash)");
            } catch (PDOException $e) {
                die('<div style="font-family:Georgia,serif;padding:40px;background:#f8f9fa;color:#000;border:2px solid #1e40af;border-radius:4px;margin:40px auto;max-width:600px">
                    <h2 style="color:#1e40af;border-bottom:2px solid #1e40af;padding-bottom:10px">Хатои пайваст</h2>
                    <p>'.htmlspecialchars($e->getMessage()).'</p>
                </div>');
            }
        }
        return $pdo;
    }
}

// ─── Ёрдамчиҳо ───
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('filesize_human')) {
    function filesize_human(int $b): string {
        if ($b >= 1048576) return round($b/1048576, 1).' MB';
        if ($b >= 1024)    return round($b/1024, 0).' KB';
        return $b.' B';
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $dt): string {
        $d = time() - strtotime($dt);
        if ($d < 60)    return 'Ҳозир';
        if ($d < 3600)  return floor($d/60).' дақ. пеш';
        if ($d < 86400) return floor($d/3600).' соат пеш';
        if ($d < 2592000) return floor($d/86400).' рӯз пеш';
        return date('d.m.Y', strtotime($dt));
    }
}

if (!function_exists('work_type_label')) {
    function work_type_label(?string $type): string {
        return match($type) {
            'курсовая' => t('work.type.course'),
            'дипломная' => t('work.type.diploma'),
            'магистр' => t('work.type.master'),
            'мақола' => t('work.type.article'),
            'реферат' => t('work.type.referat'),
            default => '—'
        };
    }
}

if (!function_exists('status_label')) {
    function status_label(?string $status): string {
        return match($status) {
            'draft' => t('status.draft'),
            'pending' => t('status.pending'),
            'approved' => t('status.approved'),
            'rejected' => t('status.rejected'),
            default => '—'
        };
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role): string {
        return match($role) {
            'admin' => t('role.admin'),
            'teacher' => t('role.teacher'),
            'student' => t('role.student'),
            default => '—'
        };
    }
}

if (!function_exists('current_lang')) {
    function current_lang(): string {
        static $resolved = null;
        if ($resolved !== null) return $resolved;

        $supported = ['tg','ru','en'];
        if (session_status() === PHP_SESSION_NONE) @session_start();

        if (!empty($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
            $_SESSION['lang'] = $_GET['lang'];
            if (!headers_sent()) {
                @setcookie('lang', $_GET['lang'], time()+86400*365, '/');
            }
            return $resolved = $_GET['lang'];
        }
        if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $supported, true)) {
            return $resolved = $_SESSION['lang'];
        }
        if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], $supported, true)) {
            $_SESSION['lang'] = $_COOKIE['lang'];
            return $resolved = $_COOKIE['lang'];
        }
        return $resolved = 'tg';
    }
}

if (!function_exists('lang_strings')) {
    function lang_strings(?string $lang = null): array {
        static $cache = [];
        $lang = $lang ?: current_lang();
        if (!isset($cache[$lang])) {
            $f = __DIR__ . '/../lang/' . $lang . '.php';
            $cache[$lang] = is_file($f) ? (require $f) : [];
        }
        return $cache[$lang];
    }
}

if (!function_exists('t')) {
    function t(string $key, array $vars = []): string {
        $s = lang_strings()[$key] ?? lang_strings('tg')[$key] ?? $key;
        if ($vars) {
            foreach ($vars as $k => $v) $s = str_replace('{'.$k.'}', (string)$v, $s);
        }
        return $s;
    }
}

if (!function_exists('lang_url')) {
    function lang_url(string $lang): string {
        $q = $_GET;
        unset($q['lang']);
        $q['lang'] = $lang;
        $self = basename($_SERVER['PHP_SELF']);
        $qs = http_build_query($q);
        return url($self . ($qs !== '' ? '?' . $qs : ''));
    }
}

if (!function_exists('log_activity')) {
    function log_activity(?int $uid, string $action, string $desc = ''): void {
        try {
            db()->prepare("INSERT INTO activity_log(user_id, action, description) VALUES(:u,:a,:d)")
                ->execute([':u'=>$uid, ':a'=>$action, ':d'=>$desc]);
        } catch (Exception $e) { /* silent */ }
    }
}

if (!function_exists('add_notification')) {
    function add_notification(int $user_id, int $work_id, string $type, string $message): void {
        try {
            db()->prepare("INSERT INTO notifications(user_id,work_id,type,message) VALUES(:u,:w,:t,:m)")
               ->execute([':u'=>$user_id, ':w'=>$work_id, ':t'=>$type, ':m'=>$message]);
        } catch (Exception $e) { /* silent */ }
    }
}
