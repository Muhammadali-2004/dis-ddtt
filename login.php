<?php
require_once __DIR__ . '/includes/auth.php';
if (logged_in()) { header('Location: '.url('index.php')); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email']??''); $pass = $_POST['pass']??'';
    if (!$email||!$pass) $err = 'Ҳамаи майдонҳоро пур кунед';
    else {
        $st = db()->prepare("SELECT * FROM users WHERE email=:e AND is_active=TRUE");
        $st->execute([':e'=>$email]);
        $user = $st->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['uid']=$user['id']; $_SESSION['uname']=$user['full_name'];
            $_SESSION['role']=$user['role']; $_SESSION['email']=$user['email'];
            log_activity($user['id'], 'login', 'Дохил шуд');
            header('Location: '.url('index.php')); exit;
        }
        $err = 'Email ё парол нодуруст';
    }
}
?>
<!DOCTYPE html>
<html lang="tg">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Дохил шудан — Архиви Корҳои Илмӣ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-bg">
  <div class="auth-box">
    <div class="auth-h">
      <div class="logo-icon">А</div>
      <h2>Дохил шудан</h2>
      <p>Системаи Иттилоотии ДИС ДДТТ</p>
    </div>
    <div class="auth-b">
      <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
      <?php if(isset($_GET['reg'])): ?><div class="alert alert-ok">Қайд муваффақ! Ҳозир дохил шавед.</div><?php endif; ?>
      <form method="POST">
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" class="fc" value="<?= e($_POST['email']??'') ?>" required>
        </div>
        <div class="fg">
          <label>Парол</label>
          <input type="password" name="pass" class="fc" required>
        </div>
        <button class="btn btn-pri btn-block btn-lg">Дохил шудан</button>
      </form>
      <div style="margin-top:18px;padding:14px;background:var(--gray-50);border-left:3px solid var(--blue);font-size:12px;color:var(--gray-700)">
        <b>Ҳисоби тестӣ:</b><br>
        admin@dis.tj / password (Admin)<br>
        rahimov@dis.tj / password (Teacher)<br>
        aliev@dis.tj / password (Student)
      </div>
    </div>
    <div class="auth-foot">
      Ҳисоб надоред? <a href="<?= url('register.php') ?>"><strong>Қайд шавед</strong></a>
    </div>
  </div>
</div>
</body></html>
