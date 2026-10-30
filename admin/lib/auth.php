<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

const AB_IDLE_TIMEOUT = 3600;
const AB_ABSOLUTE_TIMEOUT = 43200;
const AB_MIN_PASSWORD = 12;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = is_https();
    $dir = private_dir() . '/sessions';
    ensure_dir($dir);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) (AB_ABSOLUTE_TIMEOUT + 3600));
    ini_set('session.sid_length', '48');
    session_name($secure ? '__Host-abadmin' : 'abadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_save_path($dir);
    session_start();
    if (random_int(1, 50) === 1) {
        foreach (glob($dir . '/sess_*') ?: [] as $file) {
            if (@filemtime($file) < time() - (AB_ABSOLUTE_TIMEOUT + 3600)) {
                @unlink($file);
            }
        }
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(string $token): bool
{
    start_session();
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function request_csrf_token(): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    $field = $_POST['csrf'] ?? '';
    return is_string($field) ? $field : '';
}

function users_load(): array
{
    $data = private_read('users');
    return is_array($data['users'] ?? null) ? $data['users'] : [];
}

function users_save(array $users): void
{
    private_write('users', ['users' => array_values($users)]);
}

function user_find(string $email): ?array
{
    $email = strtolower(trim($email));
    foreach (users_load() as $user) {
        if (isset($user['email']) && strtolower((string) $user['email']) === $email) {
            return $user;
        }
    }
    return null;
}

function password_problem(string $password, string $email = ''): ?string
{
    if (str_length($password) < AB_MIN_PASSWORD) {
        return 'Kies een wachtwoord van minimaal ' . AB_MIN_PASSWORD . ' tekens.';
    }
    if (strlen($password) > 200) {
        return 'Het wachtwoord is te lang (maximaal 200 tekens).';
    }
    if ($email !== '' && strtolower($password) === strtolower($email)) {
        return 'Het wachtwoord mag niet gelijk zijn aan het e-mailadres.';
    }
    return null;
}

function user_create(string $email, string $password): void
{
    with_lock('users', static function () use ($email, $password) {
        $users = users_load();
        foreach ($users as $user) {
            if (strtolower((string) $user['email']) === $email) {
                throw new RuntimeException('Dit e-mailadres bestaat al.');
            }
        }
        $users[] = ['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'created' => time()];
        users_save($users);
    });
}

function user_set_password(string $email, string $password): void
{
    with_lock('users', static function () use ($email, $password) {
        $users = users_load();
        foreach ($users as &$user) {
            if (strtolower((string) $user['email']) === $email) {
                $user['hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
        unset($user);
        users_save($users);
    });
}

function user_delete(string $email): void
{
    with_lock('users', static function () use ($email) {
        $users = array_filter(users_load(), static fn($u) => strtolower((string) $u['email']) !== $email);
        users_save($users);
    });
}

function dummy_password_hash(): string
{
    static $hash = null;
    if ($hash === null) {
        $hash = password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
    }
    return $hash;
}

function login_blocked(string $email): bool
{
    return throttle_blocked('login-ip:' . client_ip(), 10, 900)
        || throttle_blocked('login-acct:' . $email, 20, 3600);
}

function login_attempt(string $email, string $password): bool
{
    $user = user_find($email);
    $hash = $user['hash'] ?? dummy_password_hash();
    $ok = password_verify($password, (string) $hash) && $user !== null;
    if (!$ok) {
        throttle_hit('login-ip:' . client_ip());
        throttle_hit('login-acct:' . $email);
        audit('login_failed', ['email' => $email]);
        return false;
    }
    throttle_clear('login-acct:' . $email);
    if (password_needs_rehash((string) $user['hash'], PASSWORD_DEFAULT)) {
        user_set_password($email, $password);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = $email;
    $_SESSION['ua'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $_SESSION['login_at'] = time();
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    audit('login_ok', ['email' => $email]);
    return true;
}

function current_user(): ?string
{
    start_session();
    $uid = $_SESSION['uid'] ?? null;
    if (!is_string($uid) || $uid === '') {
        return null;
    }
    $now = time();
    $ua = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $expired = $now - (int) ($_SESSION['last'] ?? 0) > AB_IDLE_TIMEOUT
        || $now - (int) ($_SESSION['login_at'] ?? 0) > AB_ABSOLUTE_TIMEOUT
        || !hash_equals((string) ($_SESSION['ua'] ?? ''), $ua)
        || user_find($uid) === null;
    if ($expired) {
        logout_session();
        return null;
    }
    $_SESSION['last'] = $now;
    return $uid;
}

function logout_session(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function require_login_page(): string
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function require_login_api(): string
{
    $user = current_user();
    if ($user === null) {
        fail('Uw sessie is verlopen. Log opnieuw in.', 401);
    }
    return $user;
}

function require_post_security(bool $json = true): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        if ($json) {
            fail('Methode niet toegestaan.', 405);
        }
        http_response_code(405);
        exit;
    }
    if (!request_is_same_origin()) {
        if ($json) {
            fail('Verzoek geweigerd.', 403);
        }
        http_response_code(403);
        exit('Verzoek geweigerd.');
    }
    if (!csrf_valid(request_csrf_token())) {
        if ($json) {
            fail('Uw sessie is verlopen. Ververs de pagina en probeer het opnieuw.', 403);
        }
        http_response_code(403);
        exit('Uw sessie is verlopen. Ga terug, ververs de pagina en probeer het opnieuw.');
    }
}
