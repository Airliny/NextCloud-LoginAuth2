<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Settings;

use OCA\UserAirliny\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * 管理设置分区。
 *
 * 注：Nextcloud 33+ 已移除 OCP\Settings\ISection，统一使用 IIconSection。
 */
class AdminSection implements IIconSection {

	private IL10N $l10n;
	private IURLGenerator $urlGenerator;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return 'user_airliny';
	}

	public function getName(): string {
		return $this->l10n->t('Airliny SSO 登录');
	}

	public function getPriority(): int {
		return 75;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
