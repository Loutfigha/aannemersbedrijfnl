<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/view.php';
ab_boot();

if (users_load()) {
    header('Location: login.php');
    exit;
}

$hashFile = AB_ADMIN_DIR . '/setup.hash';
$setupHash = is_file($hashFile) ? strtolower(trim((string) file_get_contents($hashFile))) : '';
$error = '';
$email = '';

if ($setupHash === '' || !preg_match('/^[a-f0-9]{64}$/', $setupHash)) {
    http_response_code(404);
    view_head('Niet beschikbaar', 'auth');
    echo '<main class="auth-card">'; view_auth_logo(); echo '<h1>Niet beschikbaar</h1><p class="muted">De eerste installatie is niet ingeschakeld.</p></main>';
    view_foot();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_form_security();
    $token = is_string($_POST['token'] ?? null) ? trim($_POST['token']) : '';
    $email = strtolower(clean_text($_POST['email'] ?? '', 120));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';

    if (!throttle_take('setup-ip:' . client_ip_key(), 10, 900)) {
        http_response_code(429);
        $error = 'Te veel pogingen. Probeer het over 15 minuten opnieuw.';
    } elseif (strlen($token) > 200 || !hash_equals($setupHash, hash('sha256', $token))) {
        audit('setup_bad_token');
        http_response_code(403);
        $error = 'De installatiecode klopt niet.';
    } elseif (!valid_email($email)) {
        $error = 'Vul een geldig e-mailadres in.';
    } elseif (($problem = password_problem($password, $email)) !== null) {
        $error = $problem;
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'De twee wachtwoorden zijn niet gelijk.';
    } else {
        try {
            with_lock('users', static function () use ($email, $password) {
                if (users_load()) {
                    throw new RuntimeException('Er bestaat al een account.');
                }
                users_save([['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'created' => time()]]);
            });
            audit('setup_done', ['email' => $email]);
            if (login_try($email, $password) === 'ok') {
                header('Location: index.php');
                exit;
            }
            header('Location: login.php');
            exit;
        } catch (RuntimeException $e) {
            $error = 'Er bestaat al een account. Log in via de inlogpagina.';
        }
    }
}

$csrf = form_token();
view_head('Eerste installatie', 'auth');
?>
<main class="auth-card">
  <?php view_auth_logo(); ?>
  <h1>Eerste installatie</h1>
  <p class="muted">Maak het eerste account aan. U heeft hiervoor de installatiecode nodig.</p>
  <?php if ($error !== ''): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form method="post" action="setup.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label for="token">Installatiecode</label>
    <input id="token" name="token" type="password" autocomplete="off" required autofocus>
    <label for="email">E-mailadres voor dit account</label>
    <input id="email" name="email" type="email" value="<?= h($email) ?>" autocomplete="username" required>
    <label for="password">Wachtwoord (minimaal <?= AB_MIN_PASSWORD ?> tekens)</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="<?= AB_MIN_PASSWORD ?>" required>
    <label for="confirm">Herhaal wachtwoord</label>
    <input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="<?= AB_MIN_PASSWORD ?>" required>
    <button type="submit" class="btn primary">Account aanmaken</button>
  </form>
</main>
<?php view_foot();
