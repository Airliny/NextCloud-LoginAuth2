<?php
/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * SSO 登录错误页（guest 布局）
 *
 * @var array $_
 * @var \OCP\IL10N $l
 */
style('user_airliny', 'login');
?>

<div class="airliny-error-wrap">
	<div class="airliny-error-card">
		<div class="airliny-error-icon">⚠️</div>
		<h1><?php p($_['title']); ?></h1>
		<div class="airliny-error-detail">
			<?php print_unescaped($_['detail']); ?>
		</div>
		<a class="button primary airliny-error-back" href="<?php p($_['backUrl']); ?>">
			<?php p($_['backLabel']); ?>
		</a>
	</div>
</div>
