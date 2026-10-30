<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/view.php';
ab_boot();
$me = require_login_page();

$messages = [];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post_security(false);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $guard = 'pw:' . $me;
    $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    $user = user_find($me);
    $verified = false;
    if (in_array($action, ['password', 'add_user'], true)) {
        if (!throttle_take($guard, 10, 900)) {
            $errors[] = 'Te veel pogingen. Probeer het over 15 minuten opnieuw.';
        } elseif ($user === null || strlen($current) > 200 || !password_verify($current, (string) $user['hash'])) {
            $errors[] = 'Het huidige wachtwoord klopt niet.';
        } else {
            $verified = true;
            throttle_refund($guard);
        }
    }

    if ($action === 'password' && $verified) {
        $new = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
        $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
        if (($problem = password_problem($new, $me)) !== null) {
            $errors[] = $problem;
        } elseif (!hash_equals($new, $confirm)) {
            $errors[] = 'De twee nieuwe wachtwoorden zijn niet gelijk.';
        } else {
            user_set_password($me, $new);
            session_regenerate_id(true);
            $_SESSION['pwv'] = user_pwv(user_find($me));
            audit('password_changed', ['email' => $me]);
            $messages[] = 'Uw wachtwoord is gewijzigd.';
        }
    } elseif ($action === 'add_user' && $verified) {
        $email = strtolower(clean_text($_POST['email'] ?? '', 120));
        $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!valid_email($email)) {
            $errors[] = 'Vul een geldig e-mailadres in.';
        } elseif (($problem = password_problem($pw, $email)) !== null) {
            $errors[] = $problem;
        } else {
            try {
                user_create($email, $pw);
                audit('user_added', ['by' => $me, 'email' => $email]);
                $messages[] = 'Gebruiker ' . $email . ' is toegevoegd.';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'delete_user') {
        $email = strtolower(clean_text($_POST['email'] ?? '', 120));
        if ($email === $me) {
            $errors[] = 'U kunt uw eigen account niet verwijderen.';
        } elseif (user_find($email) === null) {
            $errors[] = 'Deze gebruiker bestaat niet.';
        } else {
            user_delete($email);
            audit('user_deleted', ['by' => $me, 'email' => $email]);
            $messages[] = 'Gebruiker ' . $email . ' is verwijderd.';
        }
    }
}

$csrf = csrf_token();
$users = users_load();
$log = audit_recent(20);
view_head('Account', 'page', $csrf);
view_topbar($me, $csrf, 'account');
?>
<main class="container narrow">
  <h1>Account</h1>
  <p class="muted">Ingelogd als <strong><?= h($me) ?></strong>.</p>
  <?php foreach ($messages as $m): ?><p class="notice" role="status"><?= h($m) ?></p><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><p class="alert" role="alert"><?= h($e) ?></p><?php endforeach; ?>

  <section class="card">
    <h2>Wachtwoord wijzigen</h2>
    <form method="post" action="account.php" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="password">
      <label for="current">Huidig wachtwoord</label>
      <input id="current" name="current" type="password" autocomplete="current-password" required>
      <label for="new">Nieuw wachtwoord (minimaal <?= AB_MIN_PASSWORD ?> tekens)</label>
      <input id="new" name="new" type="password" autocomplete="new-password" minlength="<?= AB_MIN_PASSWORD ?>" required>
      <label for="confirm">Herhaal nieuw wachtwoord</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" minlength="<?= AB_MIN_PASSWORD ?>" required>
      <button type="submit" class="btn primary">Wachtwoord opslaan</button>
    </form>
  </section>

  <section class="card">
    <h2>Gebruikers</h2>
    <ul class="plain">
      <?php foreach ($users as $u): ?>
        <li class="row">
          <span><?= h((string) $u['email']) ?><?= strtolower((string) $u['email']) === $me ? ' (u)' : '' ?></span>
          <?php if (strtolower((string) $u['email']) !== $me): ?>
            <form method="post" action="account.php" class="inline" data-confirm="Weet u zeker dat u deze gebruiker wilt verwijderen?">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="email" value="<?= h((string) $u['email']) ?>">
              <button type="submit" class="btn danger small">Verwijderen</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <h3>Gebruiker toevoegen</h3>
    <form method="post" action="account.php" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="add_user">
      <label for="nu-current">Uw eigen wachtwoord (ter bevestiging)</label>
      <input id="nu-current" name="current" type="password" autocomplete="current-password" required>
      <label for="nu-email">E-mailadres</label>
      <input id="nu-email" name="email" type="email" required>
      <label for="nu-pw">Wachtwoord (minimaal <?= AB_MIN_PASSWORD ?> tekens)</label>
      <input id="nu-pw" name="password" type="password" autocomplete="new-password" minlength="<?= AB_MIN_PASSWORD ?>" required>
      <button type="submit" class="btn">Gebruiker toevoegen</button>
    </form>
  </section>

  <?php if (strpos(private_dir(), AB_ROOT) === 0): ?>
  <p class="alert" role="note">Let op: de beheergegevens staan binnen de websitemap (<code>admin/data</code>). Vraag uw webbouwer dit te controleren en laat vooral geen Git-opschoning uitvoeren op de server.</p>
  <?php endif; ?>

  <section class="card">
    <h2>Recente activiteit</h2>
    <?php if (!$log): ?><p class="muted">Nog geen activiteit.</p><?php else: ?>
    <table class="log">
      <thead><tr><th>Tijd</th><th>Gebeurtenis</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($log as $row): ?>
        <tr>
          <td><?= h(date('d-m-Y H:i', (int) strtotime((string) ($row['t'] ?? 'now')))) ?></td>
          <td><?= h((string) ($row['event'] ?? '')) ?></td>
          <td><?= h(implode(', ', array_filter([(string) ($row['email'] ?? $row['by'] ?? ''), (string) ($row['ip'] ?? '')]))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</main>
<?php view_foot('assets/page.js');
