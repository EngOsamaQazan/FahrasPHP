<?php
	$page_title = 'الترجمة';
	$token = 'mojeer';
	include 'header.php';
	require_permission('translate', 'view');
?>

<style>
.xcrud-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.xcrud-page-header {
    text-align: center;
    margin-bottom: 28px;
}
.xcrud-page-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.xcrud-page-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }
.xcrud-page .container { max-width: 1100px; }
.xcrud-page-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.xcrud-page-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.xcrud-page-footer a:hover { color: rgba(255,255,255,0.6); }
.xcrud-page-footer .fa-heart { color: #e53e3e; }
.xcrud-page ~ footer.footer { display: none !important; }
</style>

<div class="xcrud-page">
    <div class="container">
        <div class="xcrud-page-header">
            <h1><i class="fad fa-language"></i> <?=_e($page_title)?></h1>
            <p><?=_e('إدارة الترجمات')?></p>
        </div>

	<?php
		if ($lang == 'en') {
			Xcrud_config::$is_rtl = 0;
		}		
		$xcrud = Xcrud::get_instance();
		$xcrud->language($user['language']);
		$xcrud->table('translate');
		$xcrud->order_by('id','desc');

		$xcrud->columns('text,en,ar');
		$xcrud->no_editor('text,en,ar');

		$xcrud->set_attr('text',array('rows'=>'10'));
		$xcrud->set_attr('en',array('rows'=>'10'));
		$xcrud->set_attr('ar',array('rows'=>'10'));

		$xcrud->label(array(	
								'text' => _e('النص'),
								'en' => _e('الإنجليزية'),
								'ar' => _e('العربية'),
		));

		$xcrud->column_pattern('ar','<input class="form-control" value="{value}" field-name="ar" onchange="changeTranslateData(this, {id})" />');
		$xcrud->column_pattern('en','<input class="form-control" value="{value}" field-name="en" onchange="changeTranslateData(this, {id})" />');

		$xcrud->unset_view();

		if (!user_can('translate', 'edit')) {
			$xcrud->unset_add();
			$xcrud->unset_edit();
			$xcrud->unset_remove();
		}

		echo $xcrud->render();
	?>

        <div class="xcrud-page-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script type="text/javascript">
	function changeTranslateData(obj, item) {

		var new_val = $(obj).val();
		var field_name = $(obj).attr('field-name');

		$.post('/admin/actions/translate.php', {id: item, field: field_name, value: new_val}, function(result){
		});

	}	
</script>
