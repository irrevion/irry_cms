<?php

use irrevion\irry_cms\core\CMS;
use irrevion\irry_cms\core\helpers\utils;
use irrevion\irry_cms\core\helpers\view;

if (!defined("_VALID_PHP")) {die('Direct access to this location is not allowed.');}

?>


<script>
// <![CDATA[
/*
var auth = function(token) {
	var redirect_uri = '<?=utils::safeJsEcho(SITE.CMS_DIR.'?controller=base&action=ulogin&hash='.urlencode(md5('uLogin'.CMS::$sess_hash)), 1);?>';
	//console.log(token);
	$.ajax({
		url: redirect_uri,
		data: {
			token: token,
			CSRF_token: '<?=utils::safeJsEcho($CSRF_token, 1);?>'
		},
		async: true,
		cache: false,
		method: 'post',
		dataType: 'json',
		success: function(response, status, xhr) {
			if (response.success) {
				//location.href = '?controller=statistics&action=dashboard';
				location.href = location.href;
			} else {
				bootbox.alert(response.message);
			}
		},
		error: function(xhr, err, descr) {}
	});
}
*/
// ]]>
</script>


<body class="hold-transition login-page">
	<div class="login-box">
		<div class="login-logo">
			<?=CMS::$site_settings['cms_name_formatted'];?>
		</div>

		<?php if (!empty($response['errors'])) foreach ($response['errors'] as $e) { ?>
		<div class="callout callout-danger">
			<p><?=CMS::t($e);?></p>
		</div>
		<?php } ?>

		<div class="box box-primary login-box-body">
			<p class="login-box-msg"><?=CMS::t('login_form_tip');?></p>

			<form action="" method="post">
				<input type="hidden" name="CSRF_token" value="<?=$CSRF_token;?>" />

				<div class="form-group has-feedback">
					<input type="email" name="ad_login" value="<?= utils::safeEcho(@$_POST['ad_login'], 1); ?>" class="form-control" placeholder="<?= CMS::t('login_email_placeholder'); ?>" />
					<span class="fa fa-envelope form-control-feedback"></span>
				</div>

				<div class="form-group has-feedback">
					<input type="password" name="ad_password" placeholder="<?=CMS::t('login_password_placeholder');?>" class="form-control" />
					<span class="fa fa-lock form-control-feedback"></span>
				</div>

				<div class="row">
					<!-- <div class="col-xs-8">
						<div class="checkbox icheck">
							<label>
								<input type="checkbox" name="remember_me" value="1" /> <?=CMS::t('login_remember_me');?>
							</label>
						</div>
					</div> -->

					<div class="col-xs-4">
						<button type="submit" name="ad_send" value="1" class="btn btn-primary btn-block"><?=CMS::t('login_sign_in');?> <i class="fa fa-sign-in" aria-hidden="true"></i></button>
					</div>
				</div>
			</form>

			<a href="?controller=base&amp;action=password_recovery"><?=CMS::t('login_password_recovery');?></a>
		</div>
	</div>
</body>
