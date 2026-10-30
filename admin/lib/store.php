<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

const AB_GUARD = "<?php http_response_code(404); exit; ?>\n";
const AB_DENY_HTACCESS = "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n";

function ensure_dir(string $dir): bool
{
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    return @mkdir($dir, 0750, true) && is_writable($dir);
}

function harden_dir(string $dir): void
{
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, AB_DENY_HTACCESS);
    }
    $idx = $dir . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }
}

function private_dir(bool $create = true): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $candidates = [dirname(AB_ROOT) . '/ab-private', AB_ADMIN_DIR . '/data'];
    foreach ($candidates as $candidate) {
        $usable = $create ? ensure_dir($candidate) : is_dir($candidate);
        if ($usable) {
            if ($create) {
                harden_dir($candidate);
            }
            $dir = rtrim($candidate, '/');
            return $dir;
        }
    }
    throw new RuntimeException('Geen schrijfbare map voor beheergegevens gevonden.');
}

function private_path(string $name, bool $create = true): string
{
    if (!preg_match('/^[a-z0-9._-]+(\/[a-z0-9._-]+)?$/i', $name) || strpos($name, '..') !== false) {
        throw new InvalidArgumentException('Ongeldige bestandsnaam.');
    }
    return private_dir($create) . '/' . $name . '.php';
}

function private_read(string $name, bool $create = true): ?array
{
    $path = private_path($name, $create);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $pos = strpos($raw, "\n");
    $json = $pos === false ? '' : substr($raw, $pos + 1);
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function atomic_write(string $path, string $contents): void
{
    $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Schrijven mislukt.');
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Opslaan mislukt.');
    }
}

function private_write(string $name, array $data): void
{
    $path = private_path($name);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        ensure_dir($dir);
    }
    atomic_write($path, AB_GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function with_lock(string $name, callable $fn)
{
    $lock = fopen(private_dir() . '/' . $name . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('Vergrendelen mislukt.');
    }
    try {
        flock($lock, LOCK_EX);
        return $fn();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function audit(string $event, array $context = []): void
{
    try {
        $path = private_dir() . '/audit.log.php';
        $line = json_encode(['t' => date('c'), 'ip' => client_ip(), 'event' => $event] + $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (is_file($path) && filesize($path) > 1000000) {
            @rename($path, private_dir() . '/audit.old.log.php');
        }
        if (!is_file($path)) {
            @file_put_contents($path, AB_GUARD, LOCK_EX);
        }
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

function audit_recent(int $limit = 25): array
{
    $path = private_dir() . '/audit.log.php';
    if (!is_file($path)) {
        return [];
    }
    $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], 1);
    $out = [];
    foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $out[] = $row;
        }
    }
    return $out;
}

function throttle_key(string $bucket): string
{
    return hash('sha256', $bucket);
}

function throttle_take(string $bucket, int $max, int $window): bool
{
    return (bool) with_lock('throttle', static function () use ($bucket, $max, $window) {
        $data = private_read('throttle') ?? [];
        $now = time();
        foreach ($data as $k => $hits) {
            $data[$k] = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - 3600));
            if (!$data[$k]) {
                unset($data[$k]);
            }
        }
        $key = throttle_key($bucket);
        $recent = array_filter($data[$key] ?? [], static fn($t) => $t > $now - $window);
        if (count($recent) >= $max) {
            return false;
        }
        $data[$key][] = $now;
        private_write('throttle', $data);
        return true;
    });
}

function throttle_refund(string $bucket): void
{
    with_lock('throttle', static function () use ($bucket) {
        $data = private_read('throttle') ?? [];
        $key = throttle_key($bucket);
        if (!empty($data[$key])) {
            array_pop($data[$key]);
            if (!$data[$key]) {
                unset($data[$key]);
            }
            private_write('throttle', $data);
        }
    });
}

function content_defaults(): array
{
    $raw = @file_get_contents(AB_CONTENT_DIR . '/default.js');
    if ($raw === false || !preg_match('/^\s*window\.__defaults\s*=\s*(.*);\s*$/s', $raw, $m)) {
        throw new RuntimeException('content/default.js ontbreekt of is ongeldig.');
    }
    $data = json_decode($m[1], true);
    if (!is_array($data)) {
        throw new RuntimeException('content/default.js bevat ongeldige gegevens.');
    }
    return $data;
}

function content_merge(array $defaults, ?array $saved): array
{
    $saved = $saved ?? [];
    $out = [];
    foreach ($defaults as $key => $value) {
        if (!array_key_exists($key, $saved)) {
            $out[$key] = $value;
        } elseif ($key === 'teksten' || $key === 'cijfers' || $key === 'fotos') {
            $out[$key] = array_merge($value, is_array($saved[$key]) ? $saved[$key] : []);
        } else {
            $out[$key] = $saved[$key];
        }
    }
    return $out;
}

function content_effective(): array
{
    return content_merge(content_defaults(), private_read('content'));
}

function content_backups(): array
{
    $dir = private_dir() . '/backups';
    $ids = [];
    foreach (glob($dir . '/content-*.php') ?: [] as $file) {
        if (preg_match('/content-(\d{8}-\d{6})\.php$/', $file, $m)) {
            $ids[] = $m[1];
        }
    }
    rsort($ids);
    return $ids;
}

function content_save(array $content): void
{
    with_lock('content', static function () use ($content) {
        $old = private_read('content');
        if ($old !== null) {
            $dir = private_dir() . '/backups';
            ensure_dir($dir);
            $id = date('Ymd-His');
            if (!is_file($dir . '/content-' . $id . '.php')) {
                private_write('backups/content-' . $id, $old);
            }
            foreach (array_slice(content_backups(), 40) as $stale) {
                @unlink($dir . '/content-' . $stale . '.php');
            }
        }
        private_write('content', $content);
    });
}

function content_restore(string $id): bool
{
    if (!preg_match('/^\d{8}-\d{6}$/', $id)) {
        return false;
    }
    $backup = private_read('backups/content-' . $id);
    if ($backup === null) {
        return false;
    }
    content_save($backup);
    return true;
}
