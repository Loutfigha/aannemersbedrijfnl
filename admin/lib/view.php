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
    echo '<meta name="theme-color" content="#0B0B0A">';
    echo '<title>' . h($title) . ' · Beheer Aannemersbedrijf NL</title>';
    if ($csrf !== '') {
        echo '<meta name="csrf-token" content="' . h($csrf) . '">';
    }
    echo '<link rel="icon" href="../favicon.ico" sizes="48x48">';
    echo '<link rel="apple-touch-icon" href="../assets/icons/apple-touch-icon.png">';
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
    echo '<a class="brand" href="index.php" aria-label="Aannemersbedrijf NL, beheer"><img src="../assets/img/logo-groot.png" width="90" height="54" alt=""><span class="brand-label">Beheer</span></a>';
    echo '<nav class="topnav" aria-label="Hoofdmenu">';
    echo '<a href="index.php"' . ($active === 'editor' ? ' aria-current="page"' : '') . '>Website bewerken</a>';
    echo '<a href="account.php"' . ($active === 'account' ? ' aria-current="page"' : '') . '>Account</a>';
    echo '<a href="../" target="_blank" rel="noopener">Website bekijken</a>';
    echo '<form method="post" action="logout.php" class="inline"><input type="hidden" name="csrf" value="' . h($csrf) . '"><button type="submit" class="linkbtn">Uitloggen</button></form>';
    echo '</nav></div></header>';
}

function view_auth_logo(): void
{
    echo '<img class="auth-logo" src="../assets/img/logo-groot.png" width="160" height="96" alt="Aannemersbedrijf NL">';
}
