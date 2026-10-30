<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
ab_boot(true);
$me = require_login_api();
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

function read_json_body(): array
{
    $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($type, 'application/json') !== 0) {
        fail('Ongeldig verzoek.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 1500000);
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data)) {
        fail('Ongeldige gegevens.', 400);
    }
    return $data;
}

switch ($action) {
    case 'load':
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            fail('Methode niet toegestaan.', 405);
        }
        json_response([
            'ok' => true,
            'content' => content_effective(),
            'schema' => ab_schema(),
            'backups' => content_backups(),
            'user' => $me,
        ]);

    case 'save':
        require_post_security();
        $data = read_json_body();
        $result = ab_validate_content($data['content'] ?? null);
        if (!$result['ok']) {
            fail('Controleer de volgende punten.', 422, ['errors' => $result['errors']]);
        }
        content_save($result['content']);
        audit('content_saved', ['email' => $me]);
        json_response(['ok' => true, 'backups' => content_backups()]);

    case 'media':
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            fail('Methode niet toegestaan.', 405);
        }
        json_response(['ok' => true, 'items' => media_list()]);

    case 'upload':
        require_post_security();
        try {
            $item = media_store_upload($_FILES['file'] ?? []);
        } catch (RuntimeException $e) {
            fail($e->getMessage(), 422);
        }
        audit('photo_uploaded', ['email' => $me, 'path' => $item['path']]);
        json_response(['ok' => true, 'item' => $item]);

    case 'media_delete':
        require_post_security();
        $data = read_json_body();
        $problem = media_delete(is_string($data['path'] ?? null) ? $data['path'] : '');
        if ($problem !== '') {
            fail($problem, 422);
        }
        audit('photo_deleted', ['email' => $me, 'path' => (string) $data['path']]);
        json_response(['ok' => true]);

    case 'restore':
        require_post_security();
        $data = read_json_body();
        if (!content_restore(is_string($data['id'] ?? null) ? $data['id'] : '')) {
            fail('Deze back-up bestaat niet.', 404);
        }
        audit('content_restored', ['email' => $me, 'id' => (string) $data['id']]);
        json_response(['ok' => true, 'content' => content_effective(), 'backups' => content_backups()]);

    default:
        fail('Onbekende actie.', 404);
}
