<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Exception;

/**
 * SSO 身份与本地账号的绑定关系冲突：
 * 同一账号已绑定其他 sub，或同一 sub 已绑定其他账号 —— 拒绝登录以防顶替。
 */
class AccountBindingConflictException extends AirlinySsoException {

	private string $reason;

	public function __construct(string $reason) {
		parent::__construct('account binding conflict: ' . $reason);
		$this->reason = $reason;
	}

	public function getReason(): string {
		return $this->reason;
	}
}
