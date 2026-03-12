<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (auth_check()) {
    header('Location: /admin');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    if (!csrf_verify()) {
        $error = '<div class="alert alert-danger" role="alert">' . _e('طلب غير صالح، يرجى المحاولة مرة أخرى') . '</div>';
    } else {
        $result = auth_login($_POST['username'], $_POST['password']);
        if ($result) {
            log_activity('login', 'user', $result['id']);
            header('Location: /admin');
            exit;
        } else {
            $error = '<div class="alert alert-danger" role="alert">' . _e('اسم المستخدم أو كلمة المرور غير صحيحة') . '!</div>';
        }
    }
}

$lang = $_COOKIE['language'] ?? 'ar';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=_e('فهرس')?> - <?=_e('Login')?></title>
    <?php if ($lang == 'ar') { ?>
    <link rel="stylesheet" href="https://cdn.rtlcss.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-cSfiDrYfMj9eYCidq//oGXEkMc0vuTxHXizrMOFAaPsLt1zoCUVnSsURN+nef1lj"
          crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="/admin/css/rtl.css?ver=<?=time()?>">
    <?php } else { ?>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u"
          crossorigin="anonymous">
    <?php } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap" rel="stylesheet">
    <link href="https://iweb.ps/fs/css/all.css" rel="stylesheet">
    <link href="/admin/css/login.css?ver=<?=time()?>" rel="stylesheet">
  </head>
  <body>
    <div class="container">
      <form class="form-signin" action="" method="post">
        <?= csrf_field() ?>
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:56px;height:56px;margin:0 auto 12px;background:rgba(31,98,185,0.15);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                <img src="img/fahras-logo.png" alt="Fahras" style="width:42px;height:42px;border-radius:8px;" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'fa fa-fingerprint\' style=\'font-size:28px;color:#63b3ed;\'></i>';">
            </div>
        </div>
        <h2 class="form-signin-heading"><?=_e('يرجى تسجيل الدخول')?></h2>
        <?= $error ?>
        <label for="inputusername" class="sr-only"><?=_e('اسم المستخدم')?></label>
        <input name="username" type="text" id="inputusername" class="form-control" placeholder="<?=_e('اسم المستخدم')?>" required autofocus>
        <label for="inputPassword" class="sr-only"><?=_e('كلمة المرور')?></label>
        <input name="password" type="password" id="inputPassword" class="form-control" placeholder="<?=_e('كلمة المرور')?>" required>
        <div class="checkbox">
          <label>
            <input type="checkbox" value="remember-me"> <?=_e('تذكرني')?>
          </label>
        </div>
        <button class="btn btn-lg btn-primary btn-block" type="submit"><?=_e('Login')?></button>
      </form>
    </div>
  </body>
</html>
