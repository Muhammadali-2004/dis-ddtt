<?php
require_once __DIR__ . '/includes/auth.php';
if (logged_in()) { header('Location: '.url('index.php')); exit; }
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
    
    if (!$name||!$email||!$pass) $err = 'Майдонҳои ҳатмиро пур кунед';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $err = 'Email нодуруст';
    elseif (strlen($pass)<6) $err = 'Парол аз 6 ҳарф кам';
    elseif ($pass!==$conf) $err = 'Паролҳо мувофиқ нестанд';
    else {
        $check = $db->prepare("SELECT id FROM users WHERE email=:e");
        $check->execute([':e'=>$email]);
        if ($check->fetchColumn()) $err = 'Email аллакай қайд аст';
        else {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(:n,:e,:h,'student')")
                   ->execute([':n'=>$name,':e'=>$email,':h'=>password_hash($pass,PASSWORD_DEFAULT)]);
                $uid = $db->lastInsertId('users_id_seq');
                $db->prepare("INSERT INTO students(user_id,student_code,group_name,faculty_id,specialty_id,course,enrolled_year) VALUES(:u,:c,:g,:f,:s,:co,:y)")
                   ->execute([':u'=>$uid, ':c'=>$code, ':g'=>$group, ':f'=>$fac?:null, ':s'=>$spec?:null, ':co'=>$course, ':y'=>date('Y')]);
                $db->commit();
                log_activity($uid, 'register', 'Корбари нав');
                header('Location: '.url('login.php?reg=1')); exit;
            } catch (Exception $e) { $db->rollBack(); $err = 'Хатогии қайд'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tg">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Қайд шудан — Архиви Корҳои Илмӣ</title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-bg">
  <div class="auth-box" style="max-width:560px">
    <div class="auth-h">
      <div class="logo-icon">А</div>
      <h2>Қайд шудан</h2>
      <p>Корбари нав — донишҷӯ</p>
    </div>
    <div class="auth-b">
      <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
      <form method="POST">
        <div class="fgrid">
          <div class="fg fcol">
            <label>Ном ва насаб <span class="req">*</span></label>
            <input name="name" class="fc" value="<?= e($_POST['name']??'') ?>" required>
          </div>
          <div class="fg fcol">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" class="fc" value="<?= e($_POST['email']??'') ?>" required>
          </div>
          <div class="fg">
            <label>Рамзи донишҷӯ</label>
            <input name="code" class="fc" placeholder="СТ-2024-001">
          </div>
          <div class="fg">
            <label>Гурӯҳ</label>
            <input name="group" class="fc" placeholder="ТИ-401">
          </div>
          <div class="fg">
            <label>Факулта</label>
            <select name="fac" class="fc">
              <option value="">Интихоб</option>
              <?php foreach($faculties as $f): ?>
              <option value="<?= $f['id'] ?>"><?= e($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label>Ихтисос</label>
            <select name="spec" class="fc">
              <option value="">Интихоб</option>
              <?php foreach($specialties as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label>Курс</label>
            <select name="course" class="fc">
              <?php for($c=1;$c<=6;$c++): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="fg">
            <label>Парол <span class="req">*</span></label>
            <input type="password" name="pass" class="fc" required>
          </div>
          <div class="fg">
            <label>Тасдиқи парол <span class="req">*</span></label>
            <input type="password" name="conf" class="fc" required>
          </div>
        </div>
        <button class="btn btn-pri btn-block btn-lg" style="margin-top:10px">Қайд шудан</button>
      </form>
    </div>
    <div class="auth-foot">
      Аллакай ҳисоб доред? <a href="<?= url('login.php') ?>"><strong>Дохил шавед</strong></a>
    </div>
  </div>
</div>
</body></html>
