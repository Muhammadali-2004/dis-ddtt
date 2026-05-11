<?php
require_once __DIR__ . '/auth.php';
$cur = basename($_SERVER['PHP_SELF'], '.php');
$u = current_user();
$_notif_count = 0;
if (logged_in()) {
    try {
        $nc = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:u AND is_read=FALSE");
        $nc->execute([':u'=>$_SESSION['uid']]);
        $_notif_count = (int)$nc->fetchColumn();
    } catch (Exception $e) {}
}
$_lang = current_lang();
function nav_a($href, $label, $cur, $id) {
    $a = $cur === $id ? ' class="on"' : '';
    return '<a href="'.url($href).'"'.$a.'>'.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="<?= e($_lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($page_title) ? e($page_title).' — ' : '' ?><?= e(t('site.name')) ?> · ДИС ДДТТ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= @filemtime(__DIR__.'/../assets/css/style.css') ?: time() ?>">
<style>
.lang-switch{display:inline-flex;gap:2px;margin-left:10px;border:1px solid var(--gray-200,#e2e8f0);border-radius:4px;overflow:hidden}
.lang-switch a{padding:4px 10px;font-size:12px;font-weight:600;color:var(--gray-700,#374151);text-decoration:none;background:#fff;text-transform:uppercase;letter-spacing:.5px}
.lang-switch a.on{background:var(--blue-dark,#1e40af);color:#fff}
.lang-switch a:hover:not(.on){background:var(--gray-50,#f8f9fa)}
</style>
</head>
<body>

<header class="site-header">
  <div class="hdr-top">
    <?= e(t('site.institute')) ?>
  </div>
  <div class="hdr-row">
    <a href="<?= url('index.php') ?>" class="logo">
      <div class="logo-icon">А</div>
      <div class="logo-text">
        <b><?= e(t('site.name')) ?></b>
        <small><?= e(t('site.logo_sub')) ?></small>
      </div>
    </a>

    <nav class="site-nav">
      <?= nav_a('index.php', e(t('nav.home')), $cur, 'index') ?>
      <?= nav_a('works.php', e(t('nav.archive')), $cur, 'works') ?>
      <?php if (logged_in()): ?>
        <?= nav_a('upload.php', e(t('nav.upload')), $cur, 'upload') ?>
        <?php if (can_review()): ?>
          <?= nav_a('admin.php', e(t('nav.admin')), $cur, 'admin') ?>
        <?php endif; ?>
        <span class="user-chip">
          <?= e($u['name']) ?>
          <span class="badge b-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span>
        </span>
        <?= nav_a('profile.php', e(t('nav.profile')).($_notif_count>0?' <span class="notif-badge">'.$_notif_count.'</span>':''), $cur, 'profile') ?>
        <a href="<?= url('logout.php') ?>" class="btn btn-ghost btn-sm"><?= e(t('nav.logout')) ?></a>
      <?php else: ?>
        <?= nav_a('register.php', e(t('nav.register')), $cur, 'register') ?>
        <a href="<?= url('login.php') ?>" class="nav-cta"><?= e(t('nav.login')) ?></a>
      <?php endif; ?>
      <span class="lang-switch" title="Забон / Язык / Language">
        <a href="<?= e(lang_url('tg')) ?>" class="<?= $_lang==='tg'?'on':'' ?>">TJ</a>
        <a href="<?= e(lang_url('ru')) ?>" class="<?= $_lang==='ru'?'on':'' ?>">RU</a>
        <a href="<?= e(lang_url('en')) ?>" class="<?= $_lang==='en'?'on':'' ?>">EN</a>
      </span>
    </nav>
  </div>
</header>

<main class="fade-in">
