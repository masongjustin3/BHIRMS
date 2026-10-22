<?php
// XAMPP default configuration. Change these to match your MySQL credentials.
declare(strict_types=1);
const DB_HOST = 'localhost';
const DB_NAME = 'bhirms';
const DB_USER = 'root';
const DB_PASS = '';
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}
function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Invalid security token. Refresh and try again.'); }
}
function redirect(string $path): never { header('Location: '.$path); exit; }
function flash(string $message, string $type='success'): void { $_SESSION['flash'] = [$message,$type]; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!user()) redirect('login.php'); }
function admin_only(): void { if ((user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Administrator access required.'); } }
function log_action(string $action): void {
    if (!user()) return;
    $q = db()->prepare('INSERT INTO activity_logs (user_id,action) VALUES (?,?)');
    $q->execute([user()['user_id'], mb_substr($action,0,255)]);
}
function rows(string $sql, array $params=[]): array { $q=db()->prepare($sql);$q->execute($params);return $q->fetchAll(); }
function one(string $sql,array $params=[]): array|false { $q=db()->prepare($sql);$q->execute($params);return $q->fetch(); }
