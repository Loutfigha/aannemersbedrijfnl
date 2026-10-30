<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/view.php';
ab_boot();

if (!users_load()) {
    header('Location: setup.php');
    exit;
}
if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_form_security();
    $email = strtolower(clean_text($_POST['email'] ?? '', 120));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $result = login_try($email, $password);
    if ($result === 'ok') {
        header('Location: index.php');
        exit;
    }
    if ($result === 'blocked') {
        http_response_code(429);
        $error = 'Te veel mislukte pogingen. Probeer het over 15 minuten opnieuw.';
    } else {
        http_response_code(401);
        $error = 'Het e-mailadres of wachtwoord is onjuist.';
    }
}

$csrf = form_token();
view_head('Inloggen', 'auth');
?>
<main class="auth-card">
  <?php view_auth_logo(); ?>
  <h1>Inloggen</h1>
  <p class="muted">Beheer van de website van Aannemersbedrijf NL.</p>
  <?php if ($error !== ''): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form method="post" action="login.php" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label for="email">E-mailadres</label>
    <input id="email" name="email" type="email" value="<?= h($email) ?>" autocomplete="username" required autofocus>
    <label for="password">Wachtwoord</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit" class="btn primary">Inloggen</button>
  </form>
</main>
<?php view_foot();
