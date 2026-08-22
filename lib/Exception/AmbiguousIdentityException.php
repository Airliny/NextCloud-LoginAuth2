<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Exception;

/**
 * 同一匹配键（邮箱 / 用户名）对应多个本地账号，无法唯一确定登录身份。
 */
class AmbiguousIdentityException extends AirlinySsoException {

	private string $matchKey;

	public function __construct(string $matchKey) {
		parent::__construct('ambiguous identity for match key: ' . $matchKey);
		$this->matchKey = $matchKey;
	}

	public function getMatchKey(): string {
		return $this->matchKey;
	}
}
