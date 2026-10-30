<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function str_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

function str_cut(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_local_dev(): bool
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $ip = client_ip();
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) && in_array($ip, ['127.0.0.1', '::1'], true);
}

function normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    return (string) preg_replace('/:(80|443)$/', '', $host);
}

function enforce_https(): void
{
    if (is_https() || is_local_dev()) {
        return;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && preg_match('/^[a-z0-9.-]+(:\d+)?$/i', $host)) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/');
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
    http_response_code(403);
    exit('Beheer is alleen beschikbaar via HTTPS.');
}

function send_security_headers(): void
{
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): void
{
    json_response(['ok' => false, 'error' => $message] + $extra, $status);
}

function request_is_same_origin(): bool
{
    $host = normalize_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $parts = parse_url($origin);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $originHost = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return normalize_host($originHost) === $host;
    }
    $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin'));
    return $site === 'same-origin' || $site === 'none';
}

function clean_text($value, int $max, bool $multiline = false): string
{
    $s = is_string($value) ? $value : '';
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        return '';
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    if (!is_string($s)) {
        return '';
    }
    if (!$multiline) {
        $s = (string) preg_replace('/\s+/u', ' ', $s);
    } else {
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);
    }
    return str_cut(trim($s), $max);
}

function valid_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
