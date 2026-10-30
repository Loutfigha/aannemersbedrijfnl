<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$body = 'window.__live = {};';
try {
    require __DIR__ . '/../admin/lib/bootstrap.php';
    $saved = private_read('content', false);
    if (is_array($saved)) {
        $json = json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (is_string($json)) {
            $body = 'window.__live = ' . str_replace(["\u{2028}", "\u{2029}"], [' ', ' '], $json) . ';';
        }
    }
} catch (Throwable $e) {
    error_log('live.php: ' . $e->getMessage());
}

$etag = '"' . md5($body) . '"';
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $body;
