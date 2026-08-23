<?php
/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * @var \OC\HintException[]|array $_
 * @var \OCP\IL10N $l
 */
style('user_airliny', 'admin');
?>

<div id="user-airliny-admin" class="section">
	<h2 data-anchor-name="user-airliny">
		<?php p($l->t('Airliny SSO 登录')); ?>
	</h2>
	<p class="settings-hint">
		<?php p($l->t('通过 Airliny 统一认证中心（OAuth 2.0 授权码 + PKCE）登录已有的 Nextcloud 账号。本应用不会自动创建账号。')); ?>
	</p>

	<?php if (!empty($_['saved'])) { ?>
		<div class="msg success"><?php p($l->t('设置已保存。')); ?></div>
	<?php } ?>
	<?php if (!empty($_['unboundUid'])) { ?>
		<div class="msg success">
			<?php p($l->t('已解除账号 %s 的 SSO 绑定。', [$_['unboundUid']])); ?>
		</div>
	<?php } ?>
	<?php if (!empty($_['unbindError'])) { ?>
		<div class="msg error"><?php p($l->t('解除绑定失败，请查看 Nextcloud 日志。')); ?></div>
	<?php } ?>

	<?php if ($_['issues'] !== []) { ?>
		<div class="msg error">
			<strong><?php p($l->t('配置尚未完成，SSO 登录按钮暂不显示：')); ?></strong>
			<ul>
				<?php foreach ($_['issues'] as $issue) { ?>
					<li><?php p($issue); ?></li>
				<?php } ?>
			</ul>
		</div>
	<?php } ?>

	<form method="post"
		  action="<?php p($_['saveUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">

		<div class="field">
			<label for="airliny-base-url"><?php p($l->t('认证中心地址 (Base URL)')); ?></label>
			<input type="url" id="airliny-base-url" name="base_url"
				   value="<?php p($_['baseUrl']); ?>"
				   placeholder="https://account.airliny.com" required>
			<p class="setting-hint"><?php p($l->t('默认为 https://account.airliny.com')); ?></p>
		</div>

		<div class="field">
			<label for="airliny-client-id"><?php p($l->t('Client ID')); ?></label>
			<input type="text" id="airliny-client-id" name="client_id"
				   value="<?php p($_['clientId']); ?>"
				   placeholder="cl_xxxxxxxxxxxx" required>
		</div>

		<div class="field">
			<label for="airliny-client-secret"><?php p($l->t('Client Secret')); ?></label>
			<input type="password" id="airliny-client-secret" name="client_secret"
				   value="" autocomplete="new-password"
				   placeholder="<?php p($_['hasClientSecret'] ? $l->t('已加密保存 —— 留空保持不变，输入新值则替换') : '未设置'); ?>">
			<p class="setting-hint">
				<?php p($l->t('使用实例密钥加密存储，不会以明文形式展示或落库。输入 - 可清除。')); ?>
			</p>
		</div>

		<div class="field">
			<label for="airliny-callback"><?php p($l->t('回调地址（在认证中心开发者控制台填写）')); ?></label>
			<input type="text" id="airliny-callback" readonly
				   value="<?php p($_['callbackUrl']); ?>">
			<p class="setting-hint"><?php p($l->t('必须与认证中心注册的回调地址完全一致。')); ?></p>
		</div>

		<div class="field">
			<label for="airliny-scopes"><?php p($l->t('请求的 Scopes')); ?></label>
			<input type="text" id="airliny-scopes" name="scopes"
				   value="<?php p($_['scopes']); ?>" placeholder="verify userinfo email">
			<p class="setting-hint">
				<?php p($l->t('可用值：%s。匹配账号需要 email 或 username。', ['verify / userinfo / email / profile'])); ?>
			</p>
		</div>

		<div class="field">
			<label for="airliny-match-strategy"><?php p($l->t('账号匹配策略')); ?></label>
			<select id="airliny-match-strategy" name="match_strategy">
				<option value="email_username" <?php if ($_['matchStrategy'] === 'email_username') {
	print('selected');
} ?>>
					<?php p($l->t('先按邮箱，再按用户名（推荐）')); ?>
				</option>
				<option value="username_email" <?php if ($_['matchStrategy'] === 'username_email') {
	print('selected');
} ?>>
					<?php p($l->t('先按用户名，再按邮箱')); ?>
				</option>
				<option value="email_only" <?php if ($_['matchStrategy'] === 'email_only') {
	print('selected');
} ?>>
					<?php p($l->t('仅按邮箱')); ?>
				</option>
				<option value="username_only" <?php if ($_['matchStrategy'] === 'username_only') {
	print('selected');
} ?>>
					<?php p($l->t('仅按用户名')); ?>
				</option>
			</select>
			<p class="setting-hint">
				<?php p($l->t('同一邮箱命中多个账号时将拒绝登录并提示管理员处理；已禁用的账号无法通过 SSO 登录。')); ?>
			</p>
		</div>

		<div class="checkbox-field">
			<label>
				<input type="checkbox" name="auto_redirect" value="1" <?php if (!empty($_['autoRedirect'])) {
	print('checked');
} ?>>
				<?php p($l->t('访问登录页时自动跳转到统一认证中心')); ?>
			</label>
			<p class="setting-hint">
				<?php p($l->t('如需使用本地密码登录，可访问 %s 临时跳过自动跳转。', ['/login?noredir=1'])); ?>
			</p>
		</div>

		<div class="checkbox-field">
			<label>
				<input type="checkbox" name="hide_local_password" value="1" <?php if (!empty($_['hideLocalPassword'])) {
	print('checked');
} ?>>
				<?php p($l->t('隐藏本地密码登录表单（仅前端展示层）')); ?>
			</label>
			<p class="setting-hint">
				<?php p($l->t('注意：这只是视觉上隐藏表单。若需彻底关闭密码认证，请结合其他安全策略。')); ?>
			</p>
		</div>

		<div class="checkbox-field">
			<label>
				<input type="checkbox" name="sync_display_name" value="1" <?php if (!empty($_['syncDisplayName'])) {
	print('checked');
} ?>>
				<?php p($l->t('登录成功后同步 SSO 显示名到本站昵称')); ?>
			</label>
		</div>

		<button type="submit" class="primary"><?php p($l->t('保存')); ?></button>
	</form>

	<h3><?php p($l->t('SSO 身份绑定记录')); ?></h3>
	<p class="settings-hint">
		<?php p($l->t('每个本地账号首次 SSO 登录后会与认证中心身份 (sub) 锁定绑定，防止账号被顶替。若账号归属发生变化，可在此解除绑定。')); ?>
	</p>
	<?php if ($_['bindings'] === []) { ?>
		<em><?php p($l->t('暂无绑定记录。')); ?></em>
	<?php } else { ?>
		<table class="grid airliny-bindings">
			<thead>
			<tr>
				<th><?php p($l->t('Nextcloud 账号')); ?></th>
				<th><?php p($l->t('SSO 身份标识 (sub)')); ?></th>
				<th><?php p($l->t('绑定时间')); ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($_['bindings'] as $binding) { ?>
				<tr>
					<td><strong><?php p($binding['uid']); ?></strong></td>
					<td><code><?php p($binding['sub']); ?></code></td>
					<td><?php p($binding['boundAt']); ?></td>
					<td>
						<form method="post" action="<?php p($_['unbindUrl']); ?>">
							<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
							<input type="hidden" name="uid" value="<?php p($binding['uid']); ?>">
							<button type="submit" class="warning-small">
								<?php p($l->t('解除绑定')); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } ?>
</div>
