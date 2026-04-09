<?php
	$page_title = 'الشركات';
	$token = 'mojeer';
	include 'header.php';
	require_permission('accounts', 'view');

	// Run migrations if needed
	require_once __DIR__ . '/../includes/migrations/004_enrich_accounts.php';
	require_once __DIR__ . '/../includes/migrations/005_seed_accounts.php';
	require_once __DIR__ . '/../includes/migrations/006_add_external_accounts.php';

	// Stats
	$totalCompanies = $db->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
	$activeCompanies = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'active' OR status IS NULL")->fetchColumn();
	$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE account IS NOT NULL")->fetchColumn();
	$totalClients = $db->query("SELECT COUNT(*) FROM clients WHERE account IS NOT NULL")->fetchColumn();
?>

<style>
.companies-page {
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    min-height: calc(100vh - 50px);
}
.companies-page .container { max-width: 1200px; }
.companies-header {
    text-align: center;
    margin-bottom: 24px;
}
.companies-header h1 { font-size: 26px; font-weight: 800; margin: 0 0 6px; }
.companies-header p { opacity: 0.5; font-size: 13px; margin: 0; }
.companies-header .fa-building { margin-left: 8px; }

.stats-row {
    display: flex; gap: 16px; flex-wrap: wrap;
    margin-bottom: 28px;
}
.stat-card {
    flex: 1; min-width: 140px;
    border-radius: 12px; padding: 20px 18px;
    text-align: center; position: relative; overflow: hidden;
    transition: transform .2s;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-card .stat-icon {
    font-size: 28px; margin-bottom: 8px; opacity: .7;
}
.stat-card .stat-val {
    font-size: 32px; font-weight: 800; line-height: 1.1;
}
.stat-card .stat-label {
    font-size: 12px; opacity: .6; margin-top: 4px;
}

/* Dark theme stats */
.dark-theme .stat-card:nth-child(1) { background: linear-gradient(135deg, #1e3a5f, #0f3460); }
.dark-theme .stat-card:nth-child(2) { background: linear-gradient(135deg, #1a4731, #0f6b3f); }
.dark-theme .stat-card:nth-child(3) { background: linear-gradient(135deg, #3b1f5e, #5b2d8e); }
.dark-theme .stat-card:nth-child(4) { background: linear-gradient(135deg, #5e3a1f, #8e5b2d); }
.dark-theme .stat-card { color: #e0e6ed; }

/* Light theme stats */
.light-theme .stat-card:nth-child(1) { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e3a5f; }
.light-theme .stat-card:nth-child(2) { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
.light-theme .stat-card:nth-child(3) { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: #5b21b6; }
.light-theme .stat-card:nth-child(4) { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }

.companies-page ~ footer.footer { display: none !important; }
.companies-footer {
    padding: 16px 0; margin-top: 40px;
    text-align: center; font-size: 12px; opacity: .35;
}
.companies-footer a { text-decoration: none; }
.companies-footer .fa-heart { color: #e53e3e; }
</style>

<div class="companies-page">
    <div class="container">
        <div class="companies-header">
            <h1><i class="fad fa-building"></i> <?=_e($page_title)?></h1>
            <p><?=_e('إدارة الشركات والمنشآت المشتركة بالنظام')?></p>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-building"></i></div>
                <div class="stat-val"><?= $totalCompanies ?></div>
                <div class="stat-label"><?=_e('إجمالي الشركات')?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-check-circle"></i></div>
                <div class="stat-val"><?= $activeCompanies ?></div>
                <div class="stat-label"><?=_e('الشركات النشطة')?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-users"></i></div>
                <div class="stat-val"><?= $totalUsers ?></div>
                <div class="stat-label"><?=_e('المستخدمين المرتبطين')?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-user-tie"></i></div>
                <div class="stat-val"><?= $totalClients ?></div>
                <div class="stat-label"><?=_e('العملاء المسجّلين')?></div>
            </div>
        </div>

	<?php
		if ($lang == 'en') {
			Xcrud_config::$is_rtl = 0;
		}
		$xcrud = Xcrud::get_instance();
		$xcrud->table('accounts');
		$xcrud->order_by('id','desc');
		$xcrud->language($user['language']);

		if (!empty($user['account'])) {
			$xcrud->where('id =', $user['account']);
		}

		$typeLabels = [
			'company'     => _e('شركة'),
			'individual'  => _e('فردي'),
			'institution' => _e('مؤسسة'),
		];
		$statusLabels = [
			'active'    => _e('نشط'),
			'suspended' => _e('معلّق'),
			'expired'   => _e('منتهي'),
		];

		$xcrud->label([
			'name'               => _e('اسم الشركة'),
			'phone'              => _e('الهاتف'),
			'mobile'             => _e('الجوال'),
			'address'            => _e('العنوان'),
			'email'              => _e('البريد الإلكتروني'),
			'website'            => _e('الموقع الإلكتروني'),
			'tax_number'         => _e('السجل التجاري / الضريبي'),
			'type'               => _e('نوع المنشأة'),
			'status'             => _e('الحالة'),
			'subscription_start' => _e('بداية الاشتراك'),
			'subscription_end'   => _e('نهاية الاشتراك'),
			'notes'              => _e('ملاحظات'),
			'contact_person'     => _e('شخص التواصل'),
			'city'               => _e('المدينة'),
			'created_at'         => _e('تاريخ التسجيل'),
		]);

		$xcrud->columns('name,phone,city,type,status');
		$xcrud->fields('created_at', true);

		$xcrud->change_type('type', 'select', 'company', $typeLabels);
		$xcrud->change_type('status', 'select', 'active', $statusLabels);
		$xcrud->change_type('notes', 'textarea');
		$xcrud->change_type('subscription_start', 'date');
		$xcrud->change_type('subscription_end', 'date');

		$xcrud->column_pattern('status', '<span class="label {value}">{value}</span>');

		$xcrud->unset_view();

		if (!user_can('accounts', 'create')) $xcrud->unset_add();
		if (!user_can('accounts', 'edit'))   $xcrud->unset_edit();
		if (!user_can('accounts', 'delete')) $xcrud->unset_remove();

		echo $xcrud->render();
	?>

        <div class="companies-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?=_e('صُنع بـ')?> <i class="fa fa-heart"></i> <?=_e('بواسطة MÜJEER')?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
