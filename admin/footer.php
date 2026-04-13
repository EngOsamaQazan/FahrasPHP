<?php
if (!isset($token)) {
  http_response_code(403);
  exit('');
}
?>
  <footer class="footer hidden-print">
    <div class="container">
      <p class="text-muted">
        <a href="https://fb.com/mujeer.world" target="_blank"><?=_e('صُنع بـ')?> <i class="red fa fa-heart"></i> <?=_e('بواسطة MÜJEER')?></a>
          <span class="pull-right">&copy; <?=_e('فهرس')?> <?=date('Y')?></span>
        </p>
    </div>
  </footer>
  
	<?=Xcrud::load_js()?>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.full.min.js"></script>
	<script type="text/javascript">

		jQuery(document).on("xcrudbeforerequest", function(event, container) {
		    if (container) {
		        jQuery(container).find("select").select2("destroy");
		    } else {
		        jQuery(".xcrud").find("select").select2("destroy");
		    }
		});
		jQuery(document).on("ready xcrudafterrequest", function(event, container) {

		    if (container) {
		        jQuery(container).find("select").select2({theme: "bootstrap", dir: "<?=_e('rtl')?>"});
		    } else {
		        jQuery(".xcrud").find("select").select2({theme: "bootstrap", dir: "<?=_e('rtl')?>"});
		    }
		});
		jQuery(document).on("xcrudbeforedepend", function(event, container, data) {
		    jQuery(container).find('select[name="' + data.name + '"]').select2("destroy");
		});
		jQuery(document).on("xcrudafterdepend", function(event, container, data) {
		    jQuery(container).find('select[name="' + data.name + '"]').select2({theme: "bootstrap", dir: "<?=_e('rtl')?>"});
		});

		function toggleFahrasTheme(e) {
		    if (e) e.preventDefault();
		    var current = localStorage.getItem('fahras_theme') || 'light';
		    var next = (current === 'dark') ? 'light' : 'dark';
		    localStorage.setItem('fahras_theme', next);
		    document.cookie = 'fahras_theme=' + next + ';path=/;max-age=' + (86400 * 365);
		    var link = document.getElementById('dark-theme-link');
		    var icon = document.getElementById('themeToggleIcon');
		    if (next === 'light') {
		        if (link) link.disabled = true;
		        document.body.className = document.body.className.replace('dark-theme', 'light-theme');
		        document.documentElement.classList.add('light-theme');
		        document.documentElement.classList.remove('dark-theme');
		        if (icon) { icon.className = 'fal fa-moon'; }
		    } else {
		        if (link) link.disabled = false;
		        document.body.className = document.body.className.replace('light-theme', 'dark-theme');
		        document.documentElement.classList.add('dark-theme');
		        document.documentElement.classList.remove('light-theme');
		        if (icon) { icon.className = 'fal fa-sun'; }
		    }
		}
	</script>    
  </body>
</html>