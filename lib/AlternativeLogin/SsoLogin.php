<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\AlternativeLogin;

use OCA\UserAirliny\AppInfo\Application;
use OCP\Authentication\IAlternativeLogin;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * 登录页「使用统一认证中心登录」入口。
 *
 * 通过核心的 registerAlternativeLogin() 注册，
 * 由前端以原生 NcButton 样式渲染在 alternative-logins 区块。
 */
class SsoLogin implements IAlternativeLogin {

	private IL10N $l10n;
	private IURLGenerator $urlGenerator;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	public function getLabel(): string {
		return $this->l10n->t('使用统一认证中心登录');
	}

	public function getLink(): string {
		return $this->urlGenerator->linkToRoute(Application::APP_ID . '.login.ssoLogin');
	}

	public function getClass(): string {
		return 'airliny-sso-login';
	}

	public function load(): void {
	}
}
