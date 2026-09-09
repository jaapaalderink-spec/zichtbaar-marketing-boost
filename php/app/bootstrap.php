<?php
declare(strict_types=1);

const CONTACT_RECIPIENT = 'info@zichtbaar-marketing.nl';
function config(): array {
    static $config;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/config.example.php';
        $local = getenv('ZM_CONFIG_FILE') ?: dirname(__DIR__) . '/config/config.local.php';
        if (is_file($local)) $config = array_replace($config, require $local);
        if (getenv('ZM_DATA_DIR')) $config['data_dir'] = getenv('ZM_DATA_DIR');
    }
    return $config;
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
    static $db;
    if ($db === null) {
        $dir = config()['data_dir'];
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Storage unavailable');
        $db = new PDO('sqlite:' . $dir . '/website.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
        $db->exec('CREATE TABLE IF NOT EXISTS pages (id TEXT PRIMARY KEY, content TEXT NOT NULL, version INTEGER NOT NULL DEFAULT 1, updated_at INTEGER NOT NULL);
          CREATE TABLE IF NOT EXISTS messages (id TEXT PRIMARY KEY, payload TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", created_at INTEGER NOT NULL, sent_at INTEGER, attempts INTEGER NOT NULL DEFAULT 0);
          CREATE TABLE IF NOT EXISTS limits (bucket TEXT PRIMARY KEY, count INTEGER NOT NULL, expires INTEGER NOT NULL);');
        @chmod($dir . '/website.sqlite', 0600);
    }
    return $db;
}
function defaults(): array {
    static $data;
    return $data ??= json_decode(file_get_contents(dirname(__DIR__) . '/config/content.default.json'), true, 512, JSON_THROW_ON_ERROR);
}
function page(string $id): array {
    if (!isset(defaults()[$id])) throw new InvalidArgumentException('Onbekende pagina.');
    $stmt = db()->prepare('SELECT content,version FROM pages WHERE id=?'); $stmt->execute([$id]); $row = $stmt->fetch();
    return ['content' => $row ? json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR) : defaults()[$id], 'version' => $row ? (int)$row['version'] : 0];
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) {
        http_response_code(403); throw new RuntimeException('Je formulier is verlopen. Vernieuw de pagina en probeer opnieuw.');
    }
}
function is_admin(): bool {
    return isset($_SESSION['admin'], $_SESSION['last_active'], $_SESSION['auth_version'])
        && $_SESSION['admin'] === config()['admin_email']
        && hash_equals(hash('sha256', config()['admin_password_hash']), $_SESSION['auth_version'])
        && $_SESSION['last_active'] > time() - 1800;
}
function require_admin(): void {
    if (!is_admin()) { header('Location: /admin/login', true, 303); exit; }
    $_SESSION['last_active'] = time();
}
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function flash(string $type, string $text): void { $_SESSION['flash'] = [$type, $text]; }
function rate_limit(string $key, int $maximum, int $seconds): bool {
    $hash = hash_hmac('sha256', $key, config()['app_key'] ?: 'local-unconfigured');
    $stmt = db()->prepare('INSERT INTO limits(bucket,count,expires) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET count=CASE WHEN expires < ? THEN 1 ELSE count+1 END, expires=CASE WHEN expires < ? THEN excluded.expires ELSE expires END RETURNING count');
    $now = time(); $stmt->execute([$hash, $now+$seconds, $now, $now]);
    $count = (int)$stmt->fetchColumn(); $stmt->closeCursor();
    db()->prepare('DELETE FROM limits WHERE expires < ?')->execute([$now - 86400]);
    return $count <= $maximum;
}
function configured(): bool { return config()['admin_password_hash'] !== '' && strlen(config()['app_key']) >= 32; }
function smtp_configured(): bool {
    $c = config(); return $c['smtp_host'] !== '' && $c['smtp_username'] !== '' && $c['smtp_password'] !== '';
}
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    session_name('zichtbaar_session');
    session_set_cookie_params(['httponly'=>true, 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite'=>'Lax', 'path'=>'/']);
    session_start();
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}
