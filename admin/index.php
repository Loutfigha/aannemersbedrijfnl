<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/view.php';
ab_boot();
$me = require_login_page();
$csrf = csrf_token();

view_head('Website bewerken', 'page', $csrf);
view_topbar($me, $csrf, 'editor');
?>
<div class="layout">
  <nav id="sections" class="sidenav" aria-label="Onderdelen van de website"></nav>
  <main id="editor" class="editor" tabindex="-1">
    <p class="muted" id="loading">Gegevens laden…</p>
    <noscript><p class="alert">Voor het beheer is JavaScript nodig.</p></noscript>
  </main>
</div>
<div class="savebar" role="region" aria-label="Opslaan">
  <div class="savebar-inner">
    <p id="status" class="status" role="status" aria-live="polite">Alles is opgeslagen.</p>
    <button type="button" id="save" class="btn primary" disabled>Opslaan</button>
  </div>
</div>
<div id="errors" class="errorbox" role="alert" hidden></div>
<dialog id="media" class="dialog" aria-labelledby="media-title">
  <div class="dialog-head">
    <h2 id="media-title">Kies een foto</h2>
    <button type="button" class="btn small" id="media-close">Sluiten</button>
  </div>
  <div class="dialog-tools">
    <label class="btn" for="media-file">Nieuwe foto uploaden</label>
    <input id="media-file" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
    <span id="media-msg" class="muted" role="status"></span>
  </div>
  <div id="media-grid" class="media-grid"></div>
</dialog>
<?php view_foot('assets/admin.js');
