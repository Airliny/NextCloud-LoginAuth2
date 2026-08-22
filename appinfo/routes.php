<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// 发起 SSO 登录：重定向到统一认证中心授权端点
		['name' => 'login#ssoLogin', 'url' => '/login', 'verb' => 'GET'],
		// OAuth 回调：接收授权码并完成登录
		['name' => 'login#callback', 'url' => '/callback', 'verb' => 'GET'],
		// 管理设置保存
		['name' => 'settings#save', 'url' => '/admin/save', 'verb' => 'POST'],
		// 解除某个本地账号的 SSO 身份绑定
		['name' => 'settings#unbind', 'url' => '/admin/bindings/unbind', 'verb' => 'POST'],
	],
];
