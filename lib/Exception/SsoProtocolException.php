<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Exception;

/**
 * 认证中心返回协议层错误（token 端点报错、userinfo 响应不合法等）。
 */
class SsoProtocolException extends AirlinySsoException {

	private ?string $errorCode;

	public function __construct(string $message, ?string $errorCode = null) {
		parent::__construct($message);
		$this->errorCode = $errorCode;
	}

	public function getErrorCode(): ?string {
		return $this->errorCode;
	}
}
