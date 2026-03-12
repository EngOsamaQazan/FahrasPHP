<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$token = 'mojeer';
$page_title = 'أداة الاستيراد';
include 'header.php';

require_permission('import', 'execute');

require_once __DIR__ . '/../includes/violation_engine.php';

$result_count = 0;

if (!empty($_FILES['file']['name'])) {
	$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
	$file_name = uniqid(). '.' .$ext;
	move_uploaded_file($_FILES['file']['tmp_name'], 'tmp/' . $file_name);

	$inputFileName = 'tmp/' . $file_name;
	$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);

	$worksheet = $spreadsheet->getActiveSheet();
	$rows = [];
	foreach ($worksheet->getRowIterator() AS $row) {
	    $cellIterator = $row->getCellIterator();
	    $cellIterator->setIterateOnlyExistingCells(FALSE);
	    $cells = [];
	    foreach ($cellIterator as $cell) {
	        $cells[] = $cell->getFormattedValue();
	    }
	    $rows[] = $cells;
	}

	unlink($inputFileName);

	$count = count($rows);

	$result_count = 0;

	for ($i = 1; $i < $count; ++$i) {

		$result_count += 1;
			
		$clientData = [
			'account'      => (int)$_POST['account'],
			'name'         => $rows[$i][0] ?? '',
			'contracts'    => $rows[$i][1] ?? '',
			'national_id'  => $rows[$i][2] ?? '',
			'sell_date'    => $rows[$i][3] ?? '',
			'work'         => $rows[$i][4] ?? '',
			'home_address' => $rows[$i][5] ?? '',
			'work_address' => $rows[$i][6] ?? '',
			'phone'        => $rows[$i][7] ?? '',
			'status'       => $rows[$i][8] ?? '',
			'court_status' => $rows[$i][9] ?? '',
			'added_by'     => $_SESSION['user_id'] ?? null,
		];

		$db->insert('clients', $clientData);

		silentViolationCheck($clientData, (int)$_POST['account']);
	}

	log_activity('client_import', 'clients', null, "Imported $result_count clients to account " . (int)$_POST['account']);
}

?>

<style>
.import-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.import-header {
    text-align: center;
    margin-bottom: 28px;
}
.import-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.import-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }
.import-header .template-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #63b3ed;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.2s;
}
.import-header .template-link:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
}

.import-alert-success {
    background: rgba(72,187,120,0.1);
    border: 1px solid rgba(72,187,120,0.2);
    border-radius: 10px;
    padding: 14px 18px;
    color: #68d391;
    font-size: 14px;
    margin-bottom: 20px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}
.import-alert-danger {
    background: rgba(229,62,62,0.1);
    border: 1px solid rgba(229,62,62,0.2);
    border-radius: 10px;
    padding: 14px 18px;
    color: #fc8181;
    font-size: 14px;
    margin-bottom: 20px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}
.import-alert-info {
    background: rgba(99,179,237,0.08);
    border: 1px solid rgba(99,179,237,0.15);
    border-radius: 10px;
    padding: 14px 18px;
    color: #93c5fd;
    font-size: 13px;
    margin-bottom: 24px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.import-form-card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 28px;
    max-width: 700px;
    margin: 0 auto;
}
.import-form-card label {
    color: rgba(255,255,255,0.7);
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
    display: block;
}
.import-form-card .form-control {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    border-radius: 8px !important;
    color: #e2e8f0 !important;
    padding: 12px 14px !important;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
}
.import-form-card .form-control:focus {
    border-color: rgba(99,179,237,0.5) !important;
}
.import-form-card select.form-control option {
    background: #1a1a2e;
    color: #e2e8f0;
}
.import-form-card small {
    color: rgba(255,255,255,0.35);
    font-size: 11px;
}
.import-form-card .btn-submit {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    border: none;
    color: #fff;
    padding: 14px 32px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 12px;
}
.import-form-card .btn-submit:hover {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    transform: translateY(-1px);
}

.import-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 16px;
}

.import-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.import-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.import-footer a:hover { color: rgba(255,255,255,0.6); }
.import-footer .fa-heart { color: #e53e3e; }
.import-page ~ footer.footer { display: none !important; }

@media (max-width: 768px) {
    .import-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .import-form-row { grid-template-columns: 1fr; }
}
</style>

<div class="import-page">
    <div class="container">
        <div class="import-header">
            <h1><i class="fad fa-upload"></i> <?=_e($page_title)?></h1>
            <p><?=_e('استيراد العملاء من ملفات إكسل')?></p>
            <a href="template.xlsx?ver=<?=time()?>" target="_blank" class="template-link">
                <i class="fa fa-download"></i> <?=_e('ملف القالب')?>
            </a>
        </div>

        <?php if ($result_count > 0 && $_POST) { ?>
        <div class="import-alert-success"><i class="fa fa-check-circle"></i> <?=$result_count?> <?=_e('تمت إضافة العملاء بنجاح')?>.</div>
        <?php } ?>

        <?php if ($result_count == 0 && $_POST) { ?>
        <div class="import-alert-danger"><i class="fa fa-times-circle"></i> <?=_e('لم تتم إضافة أي عملاء')?>.</div>
        <?php } ?>

        <div class="import-alert-info"><i class="fa fa-info-circle"></i> <?=_e('يرجى استخدام ملف القالب لاستيراد البيانات')?> - <?=_e('الرقم الوطني فريد، سيتم تخطي المكرر')?>.</div>

        <div class="import-form-card">
            <form method="post" action="" enctype="multipart/form-data">
                <div class="import-form-row">
                    <div>
                        <label><?=_e('يرجى رفع الملف')?></label>
                        <input accept=".xls, .xlsx, .csv" type="file" name="file" class="form-control" required>
                        <small><?=_e('الملفات المقبولة')?>: xlsx, xls, csv</small>
                    </div>
                    <div>
                        <label><?=_e('يرجى اختيار الشركة')?></label>
                        <select class="form-control" name="account" required>
                            <option value="">- <?=_e('اختيار')?> -</option>
                            <?php
                                if (user_can('accounts', 'view')) {
                                    $accounts = $db->get_all( 'accounts' );
                                } else {
                                    $accounts = $db->get_all( 'accounts', array( 'id'=>$user['account'] ) );
                                }
                                foreach ($accounts as $key) {
                                    echo '<option value="'.$key['id'].'">'.$key['name'].'</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <input class="btn-submit" type="submit" value="<?=_e('معالجة')?>">
            </form>
        </div>

        <div class="import-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?=_e('صُنع بـ')?> <i class="fa fa-heart"></i> <?=_e('بواسطة MÜJEER')?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
