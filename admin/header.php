<?php
if (!isset($token)) {
    http_response_code(403);
    exit('');
}

$admin = 1;

require_once __DIR__ . '/../includes/bootstrap.php';

require_auth();

include __DIR__ . '/xcrud/xcrud.php';

$user = auth_user();
setcookie('language', $user['language'] ?? 'ar', time() + (86400 * 30), "/");

$lang = $user['language'] ?? 'ar';
$currentTheme = $_COOKIE['fahras_theme'] ?? 'dark';
$isDark = ($currentTheme !== 'light');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?=_e('فهرس')?> - <?=_e($page_title ?? 'لوحة التحكم')?></title>
    <meta property="og:title" content="فهرس - نظام فهرسة العملاء وكشف المخالفات">
    <meta property="og:description" content="نظام متكامل لفهرسة عملاء شركات التقسيط الأردنية وكشف المخالفات التعاقدية تلقائياً">
    <meta property="og:image" content="https://fahras.aqssat.co/admin/img/social-preview.png">
    <meta property="og:url" content="https://fahras.aqssat.co/admin/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_JO">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="فهرس - نظام فهرسة العملاء وكشف المخالفات">
    <meta name="twitter:image" content="https://fahras.aqssat.co/admin/img/social-preview.png">
    <link rel="icon" href="/admin/img/fahras-logo.png" type="image/png">
    <link href="https://iweb.ps/fs/css/all.css" rel="stylesheet">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" type="text/css" href="/admin/css/select2-bootstrap.min.css">
    <?=Xcrud::load_css()?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.5.1/dist/chart.min.js"></script>
    <link rel="stylesheet" type="text/css" href="/admin/css/custom.css?ver=<?=time()?>">
    <?php if ($lang == 'ar') { ?>
    <link rel="stylesheet" type="text/css" href="/admin/css/rtl.css?ver=<?=time()?>">
    <?php } ?>
    <link id="dark-theme-link" rel="stylesheet" type="text/css" href="/admin/css/dark-theme.css?ver=<?=time()?>"<?= $isDark ? '' : ' disabled' ?>>
    <link rel="stylesheet" type="text/css" href="/admin/css/light-overrides.css?ver=<?=time()?>">
    <script>
    (function(){
      var t = localStorage.getItem('fahras_theme') || '<?= $currentTheme ?>';
      var link = document.getElementById('dark-theme-link');
      if (t === 'light') {
        if (link) link.disabled = true;
        document.documentElement.classList.add('light-theme');
      } else {
        if (link) link.disabled = false;
        document.documentElement.classList.remove('light-theme');
      }
    })();
    </script>
  </head>
  <body class="<?= $isDark ? 'dark-theme' : 'light-theme' ?>">
	<nav class="navbar navbar-inverse">
	  <div class="container">
	    <div class="navbar-header">
	      <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-navbar" aria-expanded="false">
	        <span class="sr-only"><?=_e('القائمة')?></span>
	        <span class="icon-bar"></span>
	        <span class="icon-bar"></span>
	        <span class="icon-bar"></span>
	      </button>
	      <a class="navbar-brand" href="/admin"><b><?=_e('فهرس')?></b></a>
	    </div>

	    <div class="collapse navbar-collapse" id="main-navbar">
	      <ul class="nav navbar-nav">
            <?php if (user_can('dashboard', 'view')) { ?>
            <li><a href="/admin"><i class="fal fa-search"></i> <?=_e('الرئيسية')?></a></li>
            <?php } ?>
            <?php if (user_can('clients', 'view')) { ?>
            <li><a href="clients"><i class="fal fa-users"></i> <?=_e('العملاء')?></a></li>
            <?php } ?>
            <?php if (user_can('jobs', 'view')) { ?>
            <li><a href="jobs"><i class="fal fa-briefcase"></i> <?=_e('الوظائف')?></a></li>
            <?php } ?>
            <?php if (user_can('import', 'execute')) { ?>
            <li><a href="import"><i class="fal fa-upload"></i> <?=_e('الاستيراد')?></a></li>
            <?php } ?>
            <?php if (user_can('violations', 'view')) { ?>
            <li><a href="violations"><i class="fal fa-exclamation-triangle"></i> <?=_e('المخالفات')?></a></li>
            <?php } ?>
            <?php if (user_can('accounts', 'view')) { ?>
            <li><a href="accounts"><i class="fal fa-building"></i> <?=_e('الشركات')?></a></li>
            <?php } ?>
            <?php if (user_can('users', 'view')) { ?>
            <li><a href="users"><i class="fal fa-key"></i> <?=_e('المستخدمين')?></a></li>
            <?php } ?>
            <?php if (user_can('roles', 'view')) { ?>
            <li><a href="roles"><i class="fal fa-shield-alt"></i> <?=_e('الأدوار')?></a></li>
            <?php } ?>
            <?php if (user_can('scan', 'view')) { ?>
            <li><a href="scan"><i class="fal fa-radar"></i> <?=_e('الجرد')?></a></li>
            <?php } ?>
            <?php if (user_can('reports', 'view')) { ?>
            <li><a href="monthly-report"><i class="fal fa-chart-bar"></i> <?=_e('التقارير')?></a></li>
            <?php } ?>
            <?php if (user_can('sales_report', 'view')) { ?>
            <li><a href="sales-report"><i class="fal fa-chart-line"></i> <?=_e('تقرير المبيعات')?></a></li>
            <?php } ?>
            <?php if (user_can('activity_log', 'view')) { ?>
            <li><a href="activity-log"><i class="fal fa-history"></i> <?=_e('سجل النشاطات')?></a></li>
            <?php } ?>
	      </ul>
        <ul class="nav navbar-nav navbar-right">
          <li>
            <a href="#" onclick="toggleFahrasTheme(event)" title="<?= $isDark ? _e('الوضع الفاتح') : _e('الوضع الداكن') ?>">
              <i class="fal <?= $isDark ? 'fa-sun' : 'fa-moon' ?>" id="themeToggleIcon"></i>
            </a>
          </li>
          <?php if ($lang == 'en') { ?>
          <li><a href="?lang=ar"><i class="fal fa-flag"></i> العربية</a></li>
          <?php } else { ?>
          <li><a href="?lang=en"><i class="fal fa-flag"></i> English</a></li>
          <?php } ?>
          <li><a href="/admin/logout"><i class="fal fa-sign-out"></i> <?=_e('تسجيل الخروج')?></a></li>
        </ul>
	    </div>
	  </div>
	</nav>
