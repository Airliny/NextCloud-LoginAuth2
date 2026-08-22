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
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\Util;

/**
 * 登录页渲染前注入 SSO 按钮所需的样式、脚本与配置。
 *
 * 配置通过 <meta> 标签传递（CSP 友好，无需内联脚本）。
 */
class LoginPageListener implements IEventListener {

	private ConfigService $config;
	private IURLGenerator $urlGenerator;
	private IL10N $l10n;
	private IRequest $request;

	public function __construct(ConfigService $config,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
		IRequest $request) {
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeLoginTemplateRenderedEvent)) {
			return;
		}

		// 未配置完成时不显示按钮，避免用户点了报错
		if ($this->config->validate() !== []) {
			return;
		}

		$loginUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.login.ssoLogin');

		Util::addStyle(Application::APP_ID, 'login');
		Util::addScript(Application::APP_ID, 'login');

		// 通过 meta 标签把配置安全地传给前端 JS（值会被框架正确转义）
		Util::addHeader('meta', [
			'name' => 'user-airliny-sso-config',
			'content' => json_encode([
				'label' => $this->l10n->t('使用统一认证中心登录'),
				'autoRedirect' => $this->config->isAutoRedirectEnabled(),
				'hideLocalPassword' => $this->config->isLocalPasswordHidden(),
				'loginUrl' => $loginUrl,
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		]);
	}
}
