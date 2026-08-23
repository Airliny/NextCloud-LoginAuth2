<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\AppInfo;

use OCA\UserAirliny\AlternativeLogin\SsoLogin;
use OCA\UserAirliny\Listener\LoginPageListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'user_airliny';

	public function __construct(?string $appName = null) {
		parent::__construct($appName ?? self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// 登录页原生「替代登录方式」按钮（官方机制，NcButton 渲染）
		$context->registerAlternativeLogin(SsoLogin::class);
		// 自动跳转 / 隐藏本地密码表单等登录页行为
		$context->registerEventListener(BeforeLoginTemplateRenderedEvent::class, LoginPageListener::class);
	}

	public function boot(IBootContext $context): void {
		// 无需启动期逻辑；所有初始化均通过容器懒加载完成
	}
}
