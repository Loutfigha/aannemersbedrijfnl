<?php
declare(strict_types=1);

const MAIL_TO = 'info@aannemersbedrijf-nl.nl';
const MAIL_FROM = 'info@aannemersbedrijf-nl.nl';
const SITE_HOST = 'aannemersbedrijf-nl.nl';
const MAX_PER_HOUR = 5;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $ok, string $error = ''): void
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean(mixed $value, int $max): string
{
    $s = is_string($value) ? $value : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function oneLine(string $s): string
{
    return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Methode niet toegestaan.');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    if ($originHost !== SITE_HOST && $originHost !== 'www.' . SITE_HOST) {
        respond(403, false, 'Verzenden is niet toegestaan vanaf deze website.');
    }
}

$raw = file_get_contents('php://input', false, null, 0, 20000);
$data = json_decode($raw === false ? '' : $raw, true);
if (!is_array($data)) {
    respond(400, false, 'Ongeldig verzoek.');
}

$naam = oneLine(clean($data['naam'] ?? '', 100));
$telefoon = oneLine(clean($data['telefoon'] ?? '', 30));
$email = oneLine(clean($data['email'] ?? '', 120));
$postcode = oneLine(clean($data['postcode'] ?? '', 10));
$omschrijving = clean($data['omschrijving'] ?? '', 3000);
$werk = [];
if (isset($data['werk']) && is_array($data['werk'])) {
    foreach (array_slice($data['werk'], 0, 10) as $w) {
        $w = oneLine(clean($w, 60));
        if ($w !== '') {
            $werk[] = $w;
        }
    }
}

$elapsedMs = isset($data['t']) && is_numeric($data['t']) ? (int) $data['t'] : 0;
if ($elapsedMs < 2500) {
    respond(429, false, 'Het formulier is te snel verzonden. Probeer het opnieuw.');
}

if ($naam === '') {
    respond(422, false, 'Vul uw naam in.');
}
if (!preg_match('/^[0-9+()\s-]{8,}$/', $telefoon)) {
    respond(422, false, 'Vul een geldig telefoonnummer in.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Vul een geldig e-mailadres in.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
$rlFile = sys_get_temp_dir() . '/ab_contact_' . md5($ip) . '.json';
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $stored = json_decode((string) file_get_contents($rlFile), true);
    if (is_array($stored)) {
        $hits = array_values(array_filter($stored, static fn($t) => is_int($t) && $t > $now - 3600));
    }
}
if (count($hits) >= MAX_PER_HOUR) {
    respond(429, false, 'U heeft te vaak een bericht verzonden. Probeer het later opnieuw of bel ons.');
}

date_default_timezone_set('Europe/Amsterdam');
$lines = [
    'Nieuwe aanvraag via aannemersbedrijf-nl.nl',
    '',
    'Naam: ' . $naam,
    'Telefoon: ' . $telefoon,
    'E-mail: ' . $email,
    'Postcode: ' . ($postcode !== '' ? $postcode : '-'),
    'Soort werkzaamheden: ' . ($werk ? implode(', ', $werk) : '-'),
    '',
    'Omschrijving:',
    $omschrijving !== '' ? $omschrijving : '-',
    '',
    'Verzonden op: ' . date('d-m-Y H:i'),
];
$body = implode("\r\n", array_map(static fn($l) => str_replace(["\r\n", "\r", "\n"], "\r\n", $l), $lines));

$subject = 'Nieuwe aanvraag van ' . mb_substr($naam, 0, 60);
$headers = [
    'From: Aannemersbedrijf NL website <' . MAIL_FROM . '>',
    'Reply-To: ' . $naam . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: aannemersbedrijf-nl contactformulier',
];
$headers[1] = 'Reply-To: =?UTF-8?B?' . base64_encode($naam) . '?= <' . $email . '>';

$sent = mail(MAIL_TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
if (!$sent) {
    error_log('contact.php: mail() mislukt voor aanvraag van ' . $email);
    respond(500, false, 'Verzenden is mislukt. Probeer het later opnieuw of bel ons op +31 6 14 95 93 63.');
}

$hits[] = $now;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

respond(200, true);
