<?php
require_once __DIR__ . '/includes/auth.php';
if (logged_in()) { header('Location: '.url('index.php')); exit; }
$_lang = current_lang();
$err = '';
$db = db();
$faculties = $db->query("SELECT * FROM faculties ORDER BY name")->fetchAll();
$specialties = $db->query("SELECT * FROM specialties ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['name']??''); $email = trim($_POST['email']??'');
    $pass = $_POST['pass']??''; $conf = $_POST['conf']??'';
    $group = trim($_POST['group']??''); $code = trim($_POST['code']??'');
    $fac = intval($_POST['fac']??0); $spec = intval($_POST['spec']??0);
    $course = intval($_POST['course']??1);

    if (!$name||!$email||!$pass) $err = t('register.required');
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $err = t('register.bad_email');
    elseif (strlen($pass)<6) $err = t('register.short_pass');
    elseif ($pass!==$conf) $err = t('register.pass_mismatch');
    else {
        $check = $db->prepare("SELECT id FROM users WHERE email=:e");
        $check->execute([':e'=>$email]);
        if ($check->fetchColumn()) $err = t('register.email_exists');
        else {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(:n,:e,:h,'student')")
                   ->execute([':n'=>$name,':e'=>$email,':h'=>password_hash($pass,PASSWORD_DEFAULT)]);
                $uid = $db->lastInsertId('users_id_seq');
                $db->prepare("INSERT INTO students(user_id,student_code,group_name,faculty_id,specialty_id,course,enrolled_year) VALUES(:u,:c,:g,:f,:s,:co,:y)")
                   ->execute([':u'=>$uid, ':c'=>$code, ':g'=>$group, ':f'=>$fac?:null, ':s'=>$spec?:null, ':co'=>$course, ':y'=>date('Y')]);
                $db->commit();
                log_activity($uid, 'register', 'New user');
                header('Location: '.url('login.php?reg=1')); exit;
            } catch (Exception $e) { $db->rollBack(); $err = t('register.save_error'); }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($_lang) ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(t('register.title')) ?> — <?= e(t('site.name')) ?></title>
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
  <div class="auth-box" style="max-width:560px">
    <div class="auth-h">
      <div class="logo-icon">А</div>
      <h2><?= e(t('register.title')) ?></h2>
      <p><?= e(t('register.subtitle')) ?></p>
    </div>
    <div class="auth-b">
      <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
      <form method="POST">
        <div class="fgrid">
          <div class="fg fcol">
            <label><?= e(t('register.name')) ?> <span class="req">*</span></label>
            <input name="name" class="fc" value="<?= e($_POST['name']??'') ?>" required>
          </div>
          <div class="fg fcol">
            <label><?= e(t('register.email')) ?> <span class="req">*</span></label>
            <input type="email" name="email" class="fc" value="<?= e($_POST['email']??'') ?>" required>
          </div>
          <div class="fg">
            <label><?= e(t('register.code')) ?></label>
            <input name="code" class="fc" placeholder="СТ-2024-001">
          </div>
          <div class="fg">
            <label><?= e(t('register.group')) ?></label>
            <input name="group" class="fc" placeholder="ТИ-401">
          </div>
          <div class="fg">
            <label><?= e(t('register.faculty')) ?></label>
            <select name="fac" class="fc">
              <option value=""><?= e(t('register.specialty.choose')) ?></option>
              <?php foreach($faculties as $f): ?>
              <option value="<?= $f['id'] ?>"><?= e($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label><?= e(t('register.specialty')) ?></label>
            <select name="spec" class="fc">
              <option value=""><?= e(t('register.specialty.choose')) ?></option>
              <?php foreach($specialties as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label><?= e(t('register.course')) ?></label>
            <select name="course" class="fc">
              <?php for($c=1;$c<=6;$c++): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="fg">
            <label><?= e(t('register.password')) ?> <span class="req">*</span></label>
            <input type="password" name="pass" class="fc" required>
          </div>
          <div class="fg">
            <label><?= e(t('register.confirm')) ?> <span class="req">*</span></label>
            <input type="password" name="conf" class="fc" required>
          </div>
        </div>
        <button class="btn btn-pri btn-block btn-lg" style="margin-top:10px"><?= e(t('register.submit')) ?></button>
      </form>
    </div>
    <div class="auth-foot">
      <?= e(t('register.have_account')) ?> <a href="<?= url('login.php') ?>"><strong><?= e(t('register.login_link')) ?></strong></a>
    </div>
  </div>
</div>
</body></html>
