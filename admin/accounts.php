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
.dark-theme .companies-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    color: #e0e6ed;
}
.companies-page .container { max-width: 1200px; }
.companies-header {
    text-align: center;
    margin-bottom: 24px;
}
.dark-theme .companies-header h1 { color: #fff; }
.companies-header h1 { font-size: 26px; font-weight: 800; margin: 0 0 6px; }
.companies-header p { font-size: 13px; margin: 0; line-height: 1.5; }
.dark-theme .companies-header p { color: rgba(255, 255, 255, 0.55); opacity: 1; }
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

/* ── بطاقة XCRUD (قائمة + نموذج) ── */
.companies-card {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 0;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
    backdrop-filter: blur(12px);
    overflow: hidden;
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}
.companies-card .xcrud-container { background: transparent !important; }
.companies-card .xcrud { color: #e0e6ed; }

/* فصل أزرار الحفظ/الرجوع (كانت ملتصقة بسبب btn-group) */
.companies-card .xcrud-top-actions.btn-group {
    display: flex !important;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    width: 100%;
    float: none !important;
    padding: 16px 20px;
    margin-bottom: 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.companies-card .xcrud-top-actions.btn-group > .btn {
    float: none !important;
    margin: 0 !important;
    border-radius: 10px !important;
    position: relative;
    flex: 0 0 auto;
}

.companies-card .xcrud-top-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    margin-bottom: 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.companies-card .xcrud-top-actions .clearfix { display: none; }

/* شريط إضافة / تصدير (قائمة) */
.dark-theme .companies-card .xcrud-top-actions {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.dark-theme .companies-card .xcrud-top-actions .btn-success {
    background: linear-gradient(135deg, #15803d 0%, #22c55e 100%) !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 10px 22px !important;
    font-family: 'Almarai', sans-serif !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    box-shadow: 0 2px 12px rgba(34, 197, 94, 0.28);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.dark-theme .companies-card .xcrud-top-actions .btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 18px rgba(34, 197, 94, 0.38);
}

.dark-theme .companies-card .xcrud-top-actions .btn-default {
    background: rgba(255, 255, 255, 0.09) !important;
    color: #f1f5f9 !important;
    border: 1px solid rgba(255, 255, 255, 0.14) !important;
    border-radius: 10px !important;
    padding: 10px 20px !important;
    font-family: 'Almarai', sans-serif !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}
.dark-theme .companies-card .xcrud-top-actions .btn-default:hover,
.dark-theme .companies-card .xcrud-top-actions .btn-default:focus {
    background: rgba(255, 255, 255, 0.16) !important;
    border-color: rgba(147, 197, 253, 0.35) !important;
    color: #fff !important;
}

.dark-theme .companies-card .xcrud-list td .btn,
.dark-theme .companies-card .xcrud-actions a.btn {
    border-radius: 8px !important;
    padding: 6px 14px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    font-family: 'Almarai', sans-serif !important;
    transition: background 0.15s, border-color 0.15s;
}
.dark-theme .companies-card .xcrud-list td .btn-primary,
.dark-theme .companies-card .xcrud-actions a.btn-primary {
    background: rgba(37, 99, 235, 0.25) !important;
    border: 1px solid rgba(99, 179, 237, 0.45) !important;
    color: #bfdbfe !important;
}
.dark-theme .companies-card .xcrud-list td .btn-primary:hover,
.dark-theme .companies-card .xcrud-actions a.btn-primary:hover {
    background: rgba(37, 99, 235, 0.4) !important;
    border-color: rgba(147, 197, 253, 0.55) !important;
    color: #fff !important;
}
.dark-theme .companies-card .xcrud-list td .btn-danger,
.dark-theme .companies-card .xcrud-actions a.btn-danger {
    background: rgba(220, 38, 38, 0.12) !important;
    border: 1px solid rgba(248, 113, 113, 0.35) !important;
    color: #fca5a5 !important;
}
.dark-theme .companies-card .xcrud-list td .btn-danger:hover,
.dark-theme .companies-card .xcrud-actions a.btn-danger:hover {
    background: rgba(220, 38, 38, 0.22) !important;
    color: #fecaca !important;
}

.dark-theme .companies-card .xcrud-nav {
    padding-top: 14px !important;
    margin-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    gap: 10px;
}
.dark-theme .companies-card .xcrud-nav .pagination > li > a,
.dark-theme .companies-card .xcrud-nav .pagination > li > span {
    border-radius: 8px !important;
    padding: 7px 12px !important;
    font-weight: 600;
}
.dark-theme .companies-card .xcrud-nav .btn-group .btn {
    border-radius: 8px !important;
    font-weight: 600;
    padding: 6px 12px !important;
}

.dark-theme .companies-card .xcrud-nav .pagination > li > a,
.dark-theme .companies-card .xcrud-nav .pagination > li > span {
    background: rgba(255, 255, 255, 0.06) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #94a3b8 !important;
}
.dark-theme .companies-card .xcrud-nav .pagination > li > a:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
}
.dark-theme .companies-card .xcrud-nav .pagination > .active > span {
    background: linear-gradient(135deg, #1f62b9, #2980b9) !important;
    border-color: #1f62b9 !important;
    color: #fff !important;
}
.dark-theme .companies-card .xcrud-search input,
.dark-theme .companies-card .xcrud-search select {
    background: rgba(255, 255, 255, 0.07) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #e2e8f0 !important;
    border-radius: 8px !important;
}

.dark-theme .companies-card .xcrud-list.table > thead > tr > th,
.dark-theme .companies-card .xcrud-th th {
    background: rgba(31, 98, 185, 0.14) !important;
    color: #93c5fd !important;
    border-bottom: 2px solid rgba(31, 98, 185, 0.28) !important;
    padding: 12px 14px !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    font-family: 'Almarai', sans-serif !important;
}
.dark-theme .companies-card .xcrud-list > tbody > tr > td {
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
    padding: 11px 14px !important;
    color: #e0e6ed !important;
    vertical-align: middle !important;
}
.dark-theme .companies-card .xcrud-list > tbody > tr:nth-of-type(odd) > td {
    background: rgba(255, 255, 255, 0.025) !important;
}
.dark-theme .companies-card .xcrud-list > tbody > tr:hover > td {
    background: rgba(31, 98, 185, 0.11) !important;
}
.dark-theme .companies-card .xcrud-num {
    color: rgba(255, 255, 255, 0.28) !important;
    font-size: 11px !important;
}

/* أزرار شريط النموذج (حفظ / رجوع) */
.dark-theme .companies-card .xcrud-top-actions .btn-primary {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%) !important;
    border: none !important;
    color: #fff !important;
    box-shadow: 0 2px 10px rgba(37, 99, 235, 0.35);
}
.dark-theme .companies-card .xcrud-top-actions .btn-warning {
    background: rgba(245, 158, 11, 0.2) !important;
    border: 1px solid rgba(251, 191, 36, 0.4) !important;
    color: #fde68a !important;
}

.dark-theme .companies-card .xcrud-view {
    padding: 8px 20px 24px;
}
.dark-theme .companies-card .form-horizontal .control-label {
    color: #cbd5e1 !important;
    font-weight: 600 !important;
    padding-top: 10px !important;
}
.dark-theme .companies-card .form-group { margin-bottom: 16px; }
.dark-theme .companies-card .form-control,
.dark-theme .companies-card textarea.form-control {
    background: rgba(255, 255, 255, 0.07) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    color: #f1f5f9 !important;
    border-radius: 10px !important;
    font-family: 'Almarai', sans-serif !important;
    min-height: 40px;
}
.dark-theme .companies-card .form-control:focus {
    border-color: rgba(147, 197, 253, 0.55) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}
.dark-theme .companies-card select.form-control option {
    background: #1e293b;
    color: #e2e8f0;
}

.dark-theme .companies-card .xcrud-tabs-ui .nav-tabs {
    border-color: rgba(255, 255, 255, 0.1);
    padding: 0 16px;
    margin: 0;
}
.dark-theme .companies-card .xcrud-tabs-ui .nav-tabs > li > a {
    color: #94a3b8 !important;
    border-radius: 8px 8px 0 0 !important;
    margin-left: 4px;
}
.dark-theme .companies-card .xcrud-tabs-ui .nav-tabs > li.active > a {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #e2e8f0 !important;
}
.dark-theme .companies-card .xcrud-tabs-ui .tab-content {
    padding: 16px 20px 20px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-top: none;
    border-radius: 0 0 12px 12px;
    background: rgba(0, 0, 0, 0.08);
}

.dark-theme .companies-card .xcrud-benchmark {
    color: rgba(255, 255, 255, 0.28) !important;
}
.dark-theme .companies-card .xcrud-overlay {
    background-color: rgba(26, 26, 46, 0.55) !important;
}

@media (max-width: 768px) {
    .companies-card .xcrud-list-container { overflow-x: auto; }
    .dark-theme .companies-card .form-horizontal .control-label {
        padding-bottom: 6px;
    }
}
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

        <div class="companies-card">
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
        </div>

        <div class="companies-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
