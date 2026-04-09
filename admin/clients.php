<?php
	$page_title = 'العملاء';
	$token = 'mojeer';
	include 'header.php';
	require_permission('clients', 'view');
	require_once __DIR__ . '/../includes/migrations/006_add_external_accounts.php';

	$ar_labels = [
		'id' => 'رقم',
		'account' => 'الشركة',
		'name' => 'الاسم',
		'contracts' => 'العقود',
		'sell_date' => 'تاريخ البيع',
		'national_id' => 'الرقم الوطني',
		'work' => 'جهة العمل',
		'home_address' => 'عنوان السكن',
		'work_address' => 'عنوان العمل',
		'phone' => 'الهاتف',
		'status' => 'الحالة',
		'court_status' => 'حالة المحكمة',
		'user' => 'المستخدم',
		'created_on' => 'تاريخ الإنشاء',
		'added_by' => 'أضيف بواسطة',
		'image' => 'الصورة',
	];

	$en_labels = [
		'id' => '#',
		'account' => 'Company',
		'name' => 'Name',
		'contracts' => 'Contracts',
		'sell_date' => 'Sell Date',
		'national_id' => 'National ID',
		'work' => 'Workplace',
		'home_address' => 'Home Address',
		'work_address' => 'Work Address',
		'phone' => 'Phone',
		'status' => 'Status',
		'court_status' => 'Court Status',
		'user' => 'User',
		'created_on' => 'Created On',
		'added_by' => 'Added By',
		'image' => 'Image',
	];

	$labels = ($lang == 'ar') ? $ar_labels : $en_labels;
	$searchTerm = trim($_GET['search'] ?? '');
?>

<style>
/* ── Page Shell ──────────────────────────────────────── */
.clients-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.clients-page .container { max-width: 1200px; }

.clients-header {
    text-align: center;
    margin-bottom: 24px;
}
.clients-header h1 {
    color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
}
.clients-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

/* ── Search ──────────────────────────────────────────── */
.clients-search {
    max-width: 640px;
    margin: 0 auto 24px;
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    overflow: hidden;
    transition: all 0.3s;
    backdrop-filter: blur(12px);
}
.clients-search:focus-within {
    border-color: rgba(99,179,237,0.5);
    box-shadow: 0 8px 40px rgba(31,98,185,0.35), 0 0 0 1px rgba(99,179,237,0.2);
}
.clients-search .si {
    color: rgba(255,255,255,0.3); font-size: 18px; padding: 0 16px; flex-shrink: 0;
}
.clients-search input[type="text"] {
    flex: 1; border: none !important; padding: 14px 0 !important; font-size: 14px !important;
    font-family: 'Almarai', sans-serif !important; background: transparent !important;
    color: #e2e8f0 !important; outline: none !important; min-width: 0;
    box-shadow: none !important; height: auto !important;
}
.clients-search input::placeholder { color: rgba(255,255,255,0.3); font-size: 13px; }
.clients-search .btn-search {
    background: linear-gradient(135deg, #1f62b9 0%, #2980b9 100%);
    border: none; color: #fff; padding: 14px 24px; font-size: 14px;
    font-family: 'Almarai', sans-serif; font-weight: 700; cursor: pointer;
    transition: all 0.2s; white-space: nowrap; border-radius: 0;
}
.clients-search .btn-search:hover { background: linear-gradient(135deg, #2980b9, #3498db); }
.search-active-bar {
    max-width: 640px; margin: -12px auto 20px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; border-radius: 0 0 12px 12px;
    background: rgba(31,98,185,0.15); border: 1px solid rgba(31,98,185,0.2);
    border-top: none; font-size: 12px; color: rgba(255,255,255,0.5);
}
.search-active-bar a { color: #63b3ed; font-weight: 700; }

/* ── Glass Card (wraps XCRUD) ────────────────────────── */
.clients-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    backdrop-filter: blur(12px);
    overflow: hidden;
}

/* ── XCRUD Overrides inside .clients-card ────────────── */
.clients-card .xcrud-container { background: transparent !important; }
.clients-card .xcrud { color: #e0e6ed; }
.clients-card .xcrud-header {
    border-bottom: 1px solid rgba(255,255,255,0.06) !important;
    padding: 0 !important; margin: 0 !important;
}

/* Top actions bar */
.clients-card .xcrud-top-actions {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.clients-card .xcrud-top-actions .btn-group { order: 2; }
.clients-card .xcrud-top-actions .btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%) !important;
    border: none !important; border-radius: 10px !important; padding: 8px 18px !important;
    font-family: 'Almarai' !important; font-weight: 700 !important; font-size: 13px !important;
}
.clients-card .xcrud-top-actions .btn-default {
    background: rgba(255,255,255,0.08) !important; color: #e0e6ed !important;
    border: 1px solid rgba(255,255,255,0.1) !important; border-radius: 10px !important;
    padding: 8px 16px !important; font-family: 'Almarai' !important; font-size: 13px !important;
}

/* Table */
.clients-card .xcrud-list-container {
    border-radius: 0; overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.clients-card .xcrud-list.table {
    background: transparent !important; margin-bottom: 0 !important;
    border-collapse: separate !important; border-spacing: 0 !important;
}
.clients-card .xcrud-list.table > thead > tr > th,
.clients-card .xcrud-th th {
    background: rgba(31,98,185,0.12) !important;
    color: #93c5fd !important;
    border-bottom: 2px solid rgba(31,98,185,0.2) !important;
    border-top: none !important;
    padding: 12px 14px !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    white-space: nowrap !important;
    font-family: 'Almarai' !important;
    position: sticky;
    top: 0;
    z-index: 2;
}
.clients-card .xcrud-list > tbody > tr > td {
    border-top: 1px solid rgba(255,255,255,0.04) !important;
    padding: 11px 14px !important;
    font-size: 13px !important;
    color: #e0e6ed !important;
    vertical-align: middle !important;
    font-family: 'Almarai' !important;
}
.clients-card .xcrud-list > tbody > tr:nth-of-type(odd) > td {
    background: rgba(255,255,255,0.02) !important;
}
.clients-card .xcrud-list > tbody > tr:nth-of-type(even) > td {
    background: transparent !important;
}
.clients-card .xcrud-list > tbody > tr:hover > td {
    background: rgba(31,98,185,0.1) !important;
}

/* Row number */
.clients-card .xcrud-num { color: rgba(255,255,255,0.25) !important; font-size: 11px !important; }

/* Action buttons in rows */
.clients-card .xcrud-list td .btn,
.clients-card .xcrud-actions a {
    border-radius: 8px !important;
    padding: 5px 12px !important;
    font-size: 12px !important;
    font-family: 'Almarai' !important;
    transition: all 0.2s !important;
}
.clients-card .xcrud-list td .btn-primary,
.clients-card .xcrud-list td a.btn-primary {
    background: rgba(31,98,185,0.2) !important;
    border: 1px solid rgba(31,98,185,0.3) !important;
    color: #93c5fd !important;
}
.clients-card .xcrud-list td .btn-primary:hover {
    background: rgba(31,98,185,0.4) !important;
}
.clients-card .xcrud-list td .btn-danger,
.clients-card .xcrud-list td a.btn-danger {
    background: rgba(229,62,62,0.15) !important;
    border: 1px solid rgba(229,62,62,0.25) !important;
    color: #fc8181 !important;
}
.clients-card .xcrud-list td .btn-danger:hover {
    background: rgba(229,62,62,0.3) !important;
}

/* Nav bar (pagination + limit + search) */
.clients-card .xcrud-nav {
    background: rgba(0,0,0,0.15) !important;
    border-top: 1px solid rgba(255,255,255,0.06) !important;
    padding: 14px 20px 10px !important;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.clients-card .xcrud-nav .pagination {
    margin: 0 !important;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}
.clients-card .xcrud-nav .pagination > li > a,
.clients-card .xcrud-nav .pagination > li > span {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.08) !important;
    color: #94a3b8 !important;
    border-radius: 8px !important;
    padding: 6px 11px !important;
    font-size: 12px !important;
    font-family: 'Almarai' !important;
    min-width: 32px;
    text-align: center;
    line-height: 1.4 !important;
}
.clients-card .xcrud-nav .pagination > li > a:hover {
    background: rgba(255,255,255,0.12) !important;
    color: #fff !important;
}
.clients-card .xcrud-nav .pagination > .active > a,
.clients-card .xcrud-nav .pagination > .active > span {
    background: linear-gradient(135deg, #1f62b9 0%, #2980b9 100%) !important;
    border-color: #1f62b9 !important;
    color: #fff !important;
}

/* Limit selector */
.clients-card .xcrud-nav .btn-group .btn {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.08) !important;
    color: #94a3b8 !important;
    font-size: 11px !important;
    padding: 5px 10px !important;
    font-family: 'Almarai' !important;
    border-radius: 6px !important;
    margin: 0 1px !important;
}
.clients-card .xcrud-nav .btn-group .btn.active,
.clients-card .xcrud-nav .btn-group .btn:hover {
    background: rgba(31,98,185,0.25) !important;
    color: #93c5fd !important;
}

/* XCRUD built-in search */
.clients-card .xcrud-search {
    margin-right: auto;
}
.clients-card .xcrud-search input,
.clients-card .xcrud-search select {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #e2e8f0 !important;
    border-radius: 8px !important;
    font-family: 'Almarai' !important;
    font-size: 12px !important;
    padding: 6px 10px !important;
}
.clients-card .xcrud-search input:focus { border-color: rgba(99,179,237,0.5) !important; }

/* Benchmark */
.clients-card .xcrud-benchmark { color: rgba(255,255,255,0.2) !important; font-size: 10px !important; }

/* Overlay */
.clients-card .xcrud-overlay { background-color: rgba(26,26,46,0.6) !important; }

/* Tabs & Forms (edit/add modal) */
.clients-card .xcrud .tab-content {
    border-color: rgba(255,255,255,0.08) !important;
    background: rgba(255,255,255,0.03) !important;
}
.clients-card .xcrud .nav-tabs > li > a {
    color: #94a3b8 !important; border-color: rgba(255,255,255,0.08) !important;
    font-family: 'Almarai' !important;
}
.clients-card .xcrud .nav-tabs > li.active > a {
    background: rgba(255,255,255,0.06) !important;
    border-bottom-color: transparent !important; color: #e2e8f0 !important;
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 992px) {
    .clients-card .xcrud-list-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .clients-card .xcrud-list.table {
        min-width: 700px;
    }
}

@media (max-width: 768px) {
    .clients-page { padding: 16px 10px 60px; }
    .clients-header h1 { font-size: 20px; }
    .clients-search { margin: 0 auto 16px; border-radius: 12px; }
    .clients-search input[type="text"] { font-size: 13px !important; padding: 12px 0 !important; }
    .clients-search .btn-search { padding: 12px 16px; font-size: 13px; }
    .clients-card { border-radius: 12px; }

    /* Card layout on mobile */
    .clients-card .xcrud-list-container { overflow-x: visible !important; }
    .clients-card .xcrud-list.table { min-width: 0; }

    .clients-card .xcrud-list.table > thead { position: absolute !important; left: -9999px !important; }

    .clients-card .xcrud-list.table > tbody > tr {
        display: block !important;
        padding: 14px 16px !important;
        border-bottom: 1px solid rgba(255,255,255,0.06) !important;
        margin: 0 !important;
    }
    .clients-card .xcrud-list > tbody > tr:nth-of-type(odd),
    .clients-card .xcrud-list > tbody > tr:nth-of-type(even) { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:nth-of-type(odd) > td,
    .clients-card .xcrud-list > tbody > tr:nth-of-type(even) > td { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:hover > td { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:hover { background: rgba(31,98,185,0.06) !important; }

    .clients-card .xcrud-list > tbody > tr > td {
        display: flex !important;
        align-items: flex-start !important;
        gap: 10px !important;
        padding: 4px 0 !important;
        border: none !important;
        width: 100% !important;
        font-size: 13px !important;
    }
    .clients-card .xcrud-list > tbody > tr > td:before {
        content: attr(data-title);
        font-weight: 700;
        color: #93c5fd;
        font-size: 11px;
        min-width: 80px;
        flex-shrink: 0;
        padding-top: 2px;
    }
    .clients-card .xcrud-list > tbody > tr > td[data-title=""]:before,
    .clients-card .xcrud-list > tbody > tr > td.xcrud-num:before { display: none; }

    .clients-card .xcrud-list > tbody > tr > td.xcrud-num { display: none !important; }

    .clients-card .xcrud-list > tbody > tr > td.xcrud-actions {
        padding-top: 10px !important;
        justify-content: flex-end !important;
        gap: 6px !important;
    }
    .clients-card .xcrud-list > tbody > tr > td.xcrud-actions:before { display: none !important; }

    .clients-card .xcrud-nav {
        padding: 12px !important;
        justify-content: center;
        flex-direction: column;
        align-items: center;
    }
    .clients-card .xcrud-nav .pagination { justify-content: center; }
}

@media (max-width: 480px) {
    .clients-search input[type="text"] { font-size: 12px !important; }
    .clients-search .si { padding: 0 12px; font-size: 16px; }
    .clients-search .btn-search { padding: 12px 14px; font-size: 0; }
    .clients-search .btn-search .fa { font-size: 16px; }
    .search-active-bar { font-size: 11px; padding: 6px 12px; }
}

/* ── Footer ──────────────────────────────────────────── */
.clients-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.clients-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.clients-footer a:hover { color: rgba(255,255,255,0.6); }
.clients-footer .fa-heart { color: #e53e3e; }
.clients-page ~ footer.footer { display: none !important; }
</style>

<div class="clients-page">
    <div class="container">
        <div class="clients-header">
            <h1><i class="fad fa-users"></i> <?= ($lang == 'ar') ? 'العملاء' : 'Clients' ?></h1>
            <p><?= ($lang == 'ar') ? 'إدارة سجلات العملاء والعقود' : 'Manage client records and contracts' ?></p>
        </div>

        <form action="" method="get" class="clients-search" id="clients-search-form">
            <i class="fa fa-search si"></i>
            <input type="text" id="clients-search-input" name="search"
                   value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="<?= ($lang == 'ar') ? 'ابحث بالاسم، الرقم الوطني، الهاتف، جهة العمل...' : 'Search by name, national ID, phone, workplace...' ?>"
                   autocomplete="off" />
            <button type="submit" class="btn-search"><i class="fa fa-search"></i> <?= ($lang == 'ar') ? 'بحث' : 'Search' ?></button>
        </form>

        <?php if (!empty($searchTerm)): ?>
        <div class="search-active-bar">
            <span><i class="fa fa-filter"></i> <?= ($lang == 'ar') ? 'نتائج البحث عن' : 'Results for' ?>: <b><?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?></b></span>
            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>"><i class="fa fa-times"></i> <?= ($lang == 'ar') ? 'مسح' : 'Clear' ?></a>
        </div>
        <?php endif; ?>

        <div class="clients-card">
	<?php
		if ($lang == 'en') {
			Xcrud_config::$is_rtl = 0;
		}
		$xcrud = Xcrud::get_instance();
		$xcrud->table('clients');
		$xcrud->order_by('id','desc');
		$xcrud->language($user['language']);

		if (!empty($user['account'])) {
			$xcrud->where('account =', $user['account']);
		}

		if (!empty($searchTerm)) {
			$safe = $db->quote('%' . $searchTerm . '%');
			$xcrud->where("(clients.name LIKE {$safe} OR clients.national_id LIKE {$safe} OR clients.phone LIKE {$safe} OR clients.work LIKE {$safe} OR clients.contracts LIKE {$safe} OR clients.home_address LIKE {$safe})");
		}

		$xcrud->label($labels);

		$xcrud->columns('account,name,national_id,phone,contracts,status,sell_date');

		$xcrud->fields('created_on', true);

		$xcrud->relation('account','accounts','id','name');

		$attachments = $xcrud->nested_table('attachments','id','attachments','client');
		$attachments->order_by('id','desc');
		$attachments->language($user['language']);

		$attachments->columns('client', true);
		$attachments->fields('client', true);

		$attachments->label(array(
								'image' => $labels['image'],
		));

		$attachments->change_type('image','image','',array('width'=>1000));

		$xcrud->unset_view();

		if (!user_can('clients', 'create')) $xcrud->unset_add();
		if (!user_can('clients', 'edit'))   $xcrud->unset_edit();
		if (!user_can('clients', 'delete')) $xcrud->unset_remove();

		echo $xcrud->render();
	?>
        </div>

        <div class="clients-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?=_e('صُنع بـ')?> <i class="fa fa-heart"></i> <?=_e('بواسطة MÜJEER')?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('clients-search-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                if (window.location.search.includes('search=')) {
                    window.location.href = window.location.pathname;
                }
            }
        });
    }

    function addDataTitles() {
        var table = document.querySelector('.clients-card .xcrud-list');
        if (!table) return;
        var ths = table.querySelectorAll('thead th');
        var headers = [];
        ths.forEach(function(th) { headers.push(th.textContent.trim()); });
        table.querySelectorAll('tbody tr').forEach(function(row) {
            var tds = row.querySelectorAll('td');
            tds.forEach(function(td, i) {
                if (i < headers.length) td.setAttribute('data-title', headers[i]);
            });
        });
    }

    addDataTitles();

    var observer = new MutationObserver(function() {
        setTimeout(addDataTitles, 100);
    });
    var card = document.querySelector('.clients-card .xcrud-ajax');
    if (card) observer.observe(card, { childList: true, subtree: true });
})();
</script>

<?php include 'footer.php'; ?>
