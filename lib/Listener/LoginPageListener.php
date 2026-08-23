<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Listener;

use OCA\UserAirliny\AppInfo\Application;
use OCA\UserAirliny\Service\ConfigService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * 登录页行为增强：自动跳转 SSO / 隐藏本地密码表单。
 *
 * 「使用统一认证中心登录」按钮本身由核心的
 * registerAlternativeLogin() 机制以原生样式渲染（见 AlternativeLogin\SsoLogin），
 * 此处不再做任何 DOM 注入。
 */
class LoginPageListener implements IEventListener {

	private ConfigService $config;
	private IURLGenerator $urlGenerator;

	public function __construct(ConfigService $config, IURLGenerator $urlGenerator) {
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeLoginTemplateRenderedEvent)) {
			return;
		}
		if (!$this->config->isAutoRedirectEnabled() && !$this->config->isLocalPasswordHidden()) {
			return;
		}

		Util::addScript(Application::APP_ID, 'login');
		Util::addHeader('meta', [
			'name' => 'user-airliny-sso-config',
			'content' => json_encode([
				'autoRedirect' => $this->config->isAutoRedirectEnabled(),
				'hideLocalPassword' => $this->config->isLocalPasswordHidden(),
				'loginUrl' => $this->urlGenerator->linkToRouteAbsolute(
					Application::APP_ID . '.login.ssoLogin'
				),
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		]);
	}
}
