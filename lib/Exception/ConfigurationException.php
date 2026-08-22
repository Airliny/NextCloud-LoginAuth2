<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Exception;

/**
 * 应用配置不完整或不合法（如缺少 client_id / client_secret）。
 */
class ConfigurationException extends AirlinySsoException {

	/** @var string[] */
	private array $issues;

	/**
	 * @param string[] $issues
	 */
	public function __construct(array $issues) {
		parent::__construct('invalid configuration: ' . implode('; ', $issues));
		$this->issues = $issues;
	}

	/**
	 * @return string[]
	 */
	public function getIssues(): array {
		return $this->issues;
	}
}
