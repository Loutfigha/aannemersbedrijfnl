<?php
declare(strict_types=1);

if (!defined('AB_ADMIN_DIR')) {
    http_response_code(404);
    exit;
}

function view_head(string $title, string $bodyClass = '', string $csrf = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow, noarchive">';
    echo '<title>' . h($title) . ' · Beheer Aannemersbedrijf NL</title>';
    if ($csrf !== '') {
        echo '<meta name="csrf-token" content="' . h($csrf) . '">';
    }
    echo '<link rel="icon" href="../favicon.ico" sizes="48x48">';
    echo '<link rel="stylesheet" href="assets/admin.css">';
    echo '</head><body class="' . h($bodyClass) . '">';
}

function view_foot(string $script = ''): void
{
    if ($script !== '') {
        echo '<script src="' . h($script) . '" defer></script>';
    }
    echo '</body></html>';
}

function view_topbar(string $user, string $csrf, string $active = ''): void
{
    echo '<header class="topbar"><div class="topbar-inner">';
    echo '<a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">NL</span><span>Beheer<small>Aannemersbedrijf NL</small></span></a>';
    echo '<nav class="topnav" aria-label="Hoofdmenu">';
    echo '<a href="index.php"' . ($active === 'editor' ? ' aria-current="page"' : '') . '>Website bewerken</a>';
    echo '<a href="account.php"' . ($active === 'account' ? ' aria-current="page"' : '') . '>Account</a>';
    echo '<a href="../" target="_blank" rel="noopener">Website bekijken</a>';
    echo '<form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf" value="' . h($csrf) . '"><button type="submit" class="linkbtn">Uitloggen</button></form>';
    echo '</nav></div></header>';
}
