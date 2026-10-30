<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

const AB_MAX_UPLOAD_BYTES = 12582912;
const AB_MAX_SIDE = 2000;

function media_hidden_bundled(): array
{
    return ['logo.png', 'logo-groot.png', 'beeldmerk.png', 'og-image.jpg'];
}

function media_list(): array
{
    $used = json_encode(content_effective(), JSON_UNESCAPED_SLASHES) ?: '';
    $items = [];
    foreach (glob(AB_UPLOAD_DIR . '/*') ?: [] as $file) {
        $name = basename($file);
        if (preg_match('/^[a-f0-9]{16}\.(webp|jpg)$/', $name) && is_file($file)) {
            $path = 'uploads/' . $name;
            $items[] = [
                'path' => $path, 'kind' => 'upload', 'size' => filesize($file) ?: 0,
                'time' => filemtime($file) ?: 0, 'used' => strpos($used, '"' . $path . '"') !== false,
            ];
        }
    }
    usort($items, static fn($a, $b) => $b['time'] <=> $a['time']);
    $bundled = [];
    foreach (glob(AB_IMG_DIR . '/*') ?: [] as $file) {
        $name = basename($file);
        if (!preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*\.(jpe?g|png|webp)$/', $name) || in_array($name, media_hidden_bundled(), true)) {
            continue;
        }
        $bundled[] = ['path' => 'assets/img/' . $name, 'kind' => 'bundled', 'size' => filesize($file) ?: 0, 'time' => filemtime($file) ?: 0, 'used' => true];
    }
    return array_merge($items, $bundled);
}

function media_upload_error(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Het bestand is te groot (maximaal 12 MB).';
        case UPLOAD_ERR_PARTIAL:
            return 'Het uploaden is onderbroken. Probeer het opnieuw.';
        case UPLOAD_ERR_NO_FILE:
            return 'Kies eerst een foto.';
        default:
            return 'Het uploaden is mislukt. Probeer het opnieuw.';
    }
}

function media_store_upload(array $file): array
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('De server kan geen foto’s verwerken (PHP GD ontbreekt).');
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(media_upload_error($err));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Ongeldig bestand.');
    }
    if ((int) ($file['size'] ?? 0) > AB_MAX_UPLOAD_BYTES || (int) filesize($tmp) > AB_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Het bestand is te groot (maximaal 12 MB).');
    }
    $info = @getimagesize($tmp);
    if (!is_array($info)) {
        throw new RuntimeException('Dit is geen geldige foto. Gebruik JPG, PNG of WebP.');
    }
    [$w, $h, $type] = $info;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Gebruik een JPG-, PNG- of WebP-bestand.');
    }
    if ($w < 1 || $h < 1 || $w > 8000 || $h > 8000 || $w * $h > 50000000) {
        throw new RuntimeException('De foto heeft te veel pixels. Verklein de foto en probeer het opnieuw.');
    }
    @ini_set('memory_limit', '512M');
    $src = null;
    if ($type === IMAGETYPE_JPEG) {
        $src = @imagecreatefromjpeg($tmp);
    } elseif ($type === IMAGETYPE_PNG) {
        $src = @imagecreatefrompng($tmp);
    } elseif (function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($tmp);
    }
    if (!$src) {
        throw new RuntimeException('De foto kon niet worden gelezen.');
    }
    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($src);
    }
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($src, $angle, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
            }
        }
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = min(1.0, AB_MAX_SIDE / max($sw, $sh));
    $dw = max(1, (int) round($sw * $scale));
    $dh = max(1, (int) round($sh * $scale));
    $dst = imagecreatetruecolor($dw, $dh);
    $useWebp = function_exists('imagewebp');
    if ($useWebp) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    if (!ensure_dir(AB_UPLOAD_DIR)) {
        imagedestroy($dst);
        throw new RuntimeException('De map voor foto’s is niet beschrijfbaar.');
    }
    $ext = $useWebp ? 'webp' : 'jpg';
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = AB_UPLOAD_DIR . '/' . $name;
    $ok = $useWebp ? imagewebp($dst, $target, 82) : imagejpeg($dst, $target, 85);
    imagedestroy($dst);
    if (!$ok || !is_file($target)) {
        throw new RuntimeException('Opslaan van de foto is mislukt.');
    }
    @chmod($target, 0644);
    return ['path' => 'uploads/' . $name, 'size' => filesize($target) ?: 0, 'time' => time(), 'kind' => 'upload', 'used' => false];
}

function media_delete(string $path): string
{
    if (!preg_match('#^uploads/([a-f0-9]{16}\.(webp|jpg))$#', $path, $m)) {
        return 'Alleen eigen geüploade foto’s kunnen worden verwijderd.';
    }
    $used = json_encode([content_effective(), private_read('content')], JSON_UNESCAPED_SLASHES) ?: '';
    if (strpos($used, '"' . $path . '"') !== false) {
        return 'Deze foto wordt nog gebruikt op de website. Kies eerst een andere foto.';
    }
    $file = AB_UPLOAD_DIR . '/' . $m[1];
    if (is_file($file) && !@unlink($file)) {
        return 'Verwijderen is mislukt.';
    }
    return '';
}
