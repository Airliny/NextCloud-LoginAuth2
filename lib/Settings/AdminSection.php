<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Settings;

use OCP\IL10N;

class AdminSection implements \OCP\Settings\ISection {

	private IL10N $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
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
}
