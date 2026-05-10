<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Бор кардани кор';
$db = db();
$err=''; $ok='';

$teachers = $db->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = trim($_POST['title']??'');
    $type = $_POST['type']??'';
    $teacher_id = intval($_POST['teacher_id']??0);
    $subject = trim($_POST['subject']??'');
    $year = intval($_POST['year']??date('Y'));
    $kw = trim($_POST['keywords']??'');
    $desc = trim($_POST['description']??'');
    
    if (!$title||!$type) $err='Майдонҳои ҳатмиро пур кунед';
    elseif (empty($_FILES['file']['name'])) $err='Файлро интихоб кунед';
    else {
        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        $allowed_mime = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_mime = $finfo->file($f['tmp_name']);
        if (!in_array($ext, ['pdf','doc','docx'])) $err='Танҳо PDF, DOC, DOCX';
        elseif (!in_array($real_mime, $allowed_mime)) $err='Намуди файл нодуруст';
        elseif ($f['size']>MAX_SIZE) $err='Файл аз 20МБ зиёд';
        else {
            // Find student record
            $sid_st = $db->prepare("SELECT id FROM students WHERE user_id=:u");
            $sid_st->execute([':u'=>$_SESSION['uid']]);
            $sid = $sid_st->fetchColumn();
            
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $fname = uniqid('w_').time().'.'.$ext;
            if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$fname)) {
                $db->prepare("INSERT INTO works(title,student_id,teacher_id,type,subject,year,keywords,description,file_path,file_name,file_size,status) VALUES(:t,:sid,:tid,:tp,:s,:y,:k,:d,:fp,:fn,:fs,'pending')")
                   ->execute([':t'=>$title,':sid'=>$sid?:null,':tid'=>$teacher_id?:null,':tp'=>$type,':s'=>$subject,':y'=>$year,':k'=>$kw,':d'=>$desc,':fp'=>$fname,':fn'=>$f['name'],':fs'=>$f['size']]);
                log_activity($_SESSION['uid'], 'upload', "Бор кард: $title");
                $ok = 'Кор муваффақона бор шуд! Дар интизори тасдиқ.';
            } else $err='Хатогии нигоҳдорӣ';
        }
    }
}
include 'includes/header.php';
?>

<div class="wrap" style="max-width:840px">
  <div class="page-title">
    <div><h2>Бор кардани кори илмӣ</h2><p>PDF, DOC ё DOCX то 20 МБ</p></div>
  </div>
  
  <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <?php if($ok): ?>
  <div class="alert alert-ok"><?= e($ok) ?> <a href="<?= url('profile.php') ?>" style="margin-left:10px">→ Профил</a></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-h">Маълумоти кор</div>
    <div class="card-b">
      <form method="POST" enctype="multipart/form-data">
        <div class="drop-zone" id="dz" onclick="document.getElementById('fi').click()">
          <div class="ic">↑</div>
          <h4>Файлро ин ҷо кашед ё клик кунед</h4>
          <p>PDF · DOC · DOCX — то 20 МБ</p>
        </div>
        <input type="file" id="fi" name="file" accept=".pdf,.doc,.docx" style="display:none">
        <div class="fp" id="fp"><span id="fname"></span></div>
        
        <div style="margin-top:24px"></div>
        
        <div class="fg">
          <label>Унвони кор <span class="req">*</span></label>
          <input name="title" class="fc" value="<?= e($_POST['title']??'') ?>" required>
        </div>
        
        <div class="fgrid">
          <div class="fg">
            <label>Намуди кор <span class="req">*</span></label>
            <select name="type" class="fc" required>
              <option value="">Интихоб</option>
              <option value="курсовая">Кори курсӣ</option>
              <option value="дипломная">Кори дипломӣ</option>
              <option value="магистр">Магистрӣ</option>
              <option value="мақола">Мақола</option>
              <option value="реферат">Реферат</option>
            </select>
          </div>
          <div class="fg">
            <label>Сол <span class="req">*</span></label>
            <select name="year" class="fc" required>
              <?php for($y=date('Y');$y>=2015;$y--): ?>
              <option value="<?= $y ?>" <?= ($_POST['year']??date('Y'))==$y?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="fg fcol">
            <label>Роҳбари илмӣ</label>
            <select name="teacher_id" class="fc">
              <option value="">Интихоб (ихтиёрӣ)</option>
              <?php foreach($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg fcol">
            <label>Мавзӯи фанӣ</label>
            <input name="subject" class="fc" value="<?= e($_POST['subject']??'') ?>" placeholder="Барномасозӣ, иқтисод, молия...">
          </div>
          <div class="fg fcol">
            <label>Калидвожаҳо (бо вергул ҷудо кунед)</label>
            <input name="keywords" class="fc" value="<?= e($_POST['keywords']??'') ?>" placeholder="PHP, PostgreSQL, архив">
          </div>
          <div class="fg fcol">
            <label>Аннотатсия / Тавсиф</label>
            <textarea name="description" class="fc" rows="5"><?= e($_POST['description']??'') ?></textarea>
          </div>
        </div>
        
        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="submit" class="btn btn-pri btn-lg" style="flex:1;justify-content:center">Бор кардан</button>
          <a href="<?= url('works.php') ?>" class="btn btn-ghost btn-lg">Бекор</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const dz=document.getElementById('dz'),fi=document.getElementById('fi'),fp=document.getElementById('fp'),fn=document.getElementById('fname');
fi.addEventListener('change',show);
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over')});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');fi.files=e.dataTransfer.files;show()});
function show(){if(!fi.files[0])return;fn.textContent='✓ '+fi.files[0].name+' ('+(fi.files[0].size/1048576).toFixed(1)+' MB)';fp.style.display='block'}
</script>

<?php include 'includes/footer.php'; ?>
