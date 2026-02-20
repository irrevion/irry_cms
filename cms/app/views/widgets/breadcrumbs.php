<?php

use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

$active_subdomain = CMS::sess('active_subdomain');
$subdomain = ($active_subdomain? CMS::sess('active_subdomain_info'): []);

?>

<?php if ($active_subdomain) { ?>
<ol class="breadcrumb">
	<li><a href="?controller=subdomains&amp;action=reset" id="aResetContext"><i class="fa fa-sitemap"></i> <?= utils::safeEcho(CMS::$settings['domain'], 1); ?></a></li>
	<li><a href="?controller=subdomains&amp;action=list"><i class="fa fa-server"></i> <?= CMS::t('menu_item_subdomains_list'); ?></a></li>
	<li><i class="fa fa-star"></i> <?= utils::safeEcho($subdomain['url'], 1); ?></li>
	<li class="active"><i class="fa fa-<?= utils::safeEcho($icon, 1); ?>"></i> <?= utils::safeEcho($name, 1); ?></li>
</ol>


<!-- Subdomain reset hidden form -->
<form action="?controller=subdomains&amp;action=reset" method="post" id="formResetSubdomain">
	<input type="hidden" name="CSRF_token" value="<?= $CSRF_token; ?>" />
	<input type="hidden" name="reset" value="default_context" />
</form>


<script type="text/javascript">
utils.setConfirmation('click', '#aResetContext', '<?= CMS::t('reset_subdomain_confirm'); ?>', function($el) {
	$('#formResetSubdomain').submit();
});
</script>
<?php } ?>