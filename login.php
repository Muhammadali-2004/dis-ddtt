<?php
require_once __DIR__ . '/includes/auth.php';
if (logged_in()) { header('Location: '.url('index.php')); exit; }
$_lang = current_lang();
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email']??''); $pass = $_POST['pass']??'';
    if (!$email||!$pass) $err = t('login.empty');
    else {
        $st = db()->prepare("SELECT * FROM users WHERE email=:e AND is_active=TRUE");
        $st->execute([':e'=>$email]);
        $user = $st->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['uid']=$user['id']; $_SESSION['uname']=$user['full_name'];
            $_SESSION['role']=$user['role']; $_SESSION['email']=$user['email'];
            log_activity($user['id'], 'login', 'Login');
            header('Location: '.url('index.php')); exit;
        }
        $err = t('login.bad_credentials');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($_lang) ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(t('login.title')) ?> — <?= e(t('site.name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= @filemtime(__DIR__.'/assets/css/style.css') ?: time() ?>">
<style>
.auth-lang{position:absolute;top:20px;right:20px;display:inline-flex;gap:2px;border:1px solid #e2e8f0;border-radius:4px;overflow:hidden;background:#fff;z-index:10}
.auth-lang a{padding:5px 11px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;text-transform:uppercase;letter-spacing:.5px}
.auth-lang a.on{background:#1e40af;color:#fff}
</style>
</head>
<body>
<div class="auth-bg" style="position:relative">
  <div class="auth-lang">
    <a href="<?= e(lang_url('tg')) ?>" class="<?= $_lang==='tg'?'on':'' ?>">TJ</a>
    <a href="<?= e(lang_url('ru')) ?>" class="<?= $_lang==='ru'?'on':'' ?>">RU</a>
    <a href="<?= e(lang_url('en')) ?>" class="<?= $_lang==='en'?'on':'' ?>">EN</a>
  </div>
  <div class="auth-box">
    <div class="auth-h">
      <div class="logo-icon">А</div>
      <h2><?= e(t('login.title')) ?></h2>
      <p><?= e(t('login.subtitle')) ?></p>
    </div>
    <div class="auth-b">
      <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
      <?php if(isset($_GET['reg'])): ?><div class="alert alert-ok"><?= e(t('login.after_register')) ?></div><?php endif; ?>
      <form method="POST">
        <div class="fg">
          <label><?= e(t('login.email')) ?></label>
          <input type="email" name="email" class="fc" value="<?= e($_POST['email']??'') ?>" required>
        </div>
        <div class="fg">
          <label><?= e(t('login.password')) ?></label>
          <input type="password" name="pass" class="fc" required>
        </div>
        <button class="btn btn-pri btn-block btn-lg"><?= e(t('login.submit')) ?></button>
      </form>
      <div style="margin-top:18px;padding:14px;background:var(--gray-50);border-left:3px solid var(--blue);font-size:12px;color:var(--gray-700)">
        <b><?= e(t('login.test_accounts')) ?></b><br>
        admin@dis.tj / password (<?= e(t('role.admin')) ?>)<br>
        rahimov@dis.tj / password (<?= e(t('role.teacher')) ?>)<br>
        aliev@dis.tj / password (<?= e(t('role.student')) ?>)
      </div>
    </div>
    <div class="auth-foot">
      <?= e(t('login.no_account')) ?> <a href="<?= url('register.php') ?>"><strong><?= e(t('login.register_link')) ?></strong></a>
    </div>
  </div>
</div>
</body></html>
