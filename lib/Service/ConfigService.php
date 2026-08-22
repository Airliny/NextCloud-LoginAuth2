<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use OCA\UserAirliny\AppInfo\Application;
use OCA\UserAirliny\Exception\ConfigurationException;
use OCP\IAppConfig;
use OCP\Security\ICrypto;

/**
 * 应用配置的统一读写入口。
 *
 * client_secret 不落明文：使用实例密钥（config.php 中 instanceid + secret 派生）加密后存储。
 */
class ConfigService {

	public const DEFAULT_BASE_URL = 'https://account.airliny.com';
	public const DEFAULT_SCOPES = 'verify userinfo email';
	public const MATCH_EMAIL_USERNAME = 'email_username';
	public const MATCH_USERNAME_EMAIL = 'username_email';
	public const MATCH_EMAIL_ONLY = 'email_only';
	public const MATCH_USERNAME_ONLY = 'username_only';

	private IAppConfig $appConfig;
	private ICrypto $crypto;

	public function __construct(IAppConfig $appConfig, ICrypto $crypto) {
		$this->appConfig = $appConfig;
		$this->crypto = $crypto;
	}

	public function getBaseUrl(): string {
		return rtrim($this->getValue('base_url', self::DEFAULT_BASE_URL), '/');
	}

	public function setBaseUrl(string $url): void {
		$this->setValue('base_url', rtrim($url, '/'));
	}

	public function getClientId(): string {
		return $this->getValue('client_id', '');
	}

	public function setClientId(string $clientId): void {
		$this->setValue('client_id', trim($clientId));
	}

	/**
	 * 读取并解密 client_secret；未设置时返回空字符串。
	 */
	public function getClientSecret(): string {
		$encrypted = $this->getValue('client_secret', '');
		if ($encrypted === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($encrypted);
		} catch (\Exception $e) {
			// 解密失败（例如实例密钥变更）：视为未配置，避免崩溃
			return '';
		}
	}

	/**
	 * 加密存储 client_secret；传入空串则清除。
	 */
	public function setClientSecret(string $secret): void {
		if ($secret === '') {
			$this->appConfig->deleteKey(Application::APP_ID, 'client_secret');
			return;
		}
		$this->setValue('client_secret', $this->crypto->encrypt($secret));
	}

	public function hasClientSecret(): bool {
		return $this->getValue('client_secret', '') !== '';
	}

	/**
	 * @return string[] scope 列表
	 */
	public function getScopes(): array {
		$raw = $this->getValue('scopes', self::DEFAULT_SCOPES);
		$allowed = ['verify', 'userinfo', 'email', 'profile'];
		$scopes = array_values(array_intersect(
			array_filter(array_map('trim', explode(' ', $raw))),
			$allowed
		));
		return $scopes === [] ? ['verify'] : array_values(array_unique($scopes));
	}

	public function getScopeString(): string {
		return implode(' ', $this->getScopes());
	}

	public function setScopesString(string $raw): void {
		$this->setValue('scopes', $raw);
	}

	public function getMatchStrategy(): string {
		$strategy = $this->getValue('match_strategy', self::MATCH_EMAIL_USERNAME);
		return in_array($strategy, [
			self::MATCH_EMAIL_USERNAME,
			self::MATCH_USERNAME_EMAIL,
			self::MATCH_EMAIL_ONLY,
			self::MATCH_USERNAME_ONLY,
		], true) ? $strategy : self::MATCH_EMAIL_USERNAME;
	}

	public function setMatchStrategy(string $strategy): void {
		$this->setValue('match_strategy', $strategy);
	}

	public function isAutoRedirectEnabled(): bool {
		return $this->getBool('auto_redirect', false);
	}

	public function setAutoRedirect(bool $enabled): void {
		$this->setBool('auto_redirect', $enabled);
	}

	/** 登录页隐藏本地密码表单（仅前端展示层）。 */
	public function isLocalPasswordHidden(): bool {
		return $this->getBool('hide_local_password', false);
	}

	public function setLocalPasswordHidden(bool $hidden): void {
		$this->setBool('hide_local_password', $hidden);
	}

	/** 登录成功后用 SSO 资料同步本地显示名。 */
	public function isDisplayNameSyncEnabled(): bool {
		return $this->getBool('sync_display_name', false);
	}

	public function setDisplayNameSync(bool $enabled): void {
		$this->setBool('sync_display_name', $enabled);
	}

	/**
	 * 各端点 URL（以 base_url 为准，兼容自定义部署域名）。
	 *
	 * @return array{authorize:string, token:string, userinfo:string, revoke:string, discovery:string}
	 */
	public function getEndpoints(): array {
		$base = $this->getBaseUrl();
		return [
			'authorize' => $base . '/oauth/authorize',
			'token' => $base . '/oauth/token',
			'userinfo' => $base . '/oauth/userinfo',
			'revoke' => $base . '/oauth/revoke',
			'discovery' => $base . '/.well-known/openid-configuration',
		];
	}

	/**
	 * 校验配置完整性，返回问题列表（空数组 = 配置可用）。
	 *
	 * @return string[]
	 */
	public function validate(): array {
		$issues = [];
		if ($this->getClientId() === '') {
			$issues[] = 'client_id 未设置';
		}
		if (!$this->hasClientSecret()) {
			$issues[] = 'client_secret 未设置';
		}
		$base = $this->getBaseUrl();
		if (!preg_match('#^https://[a-z0-9.-]+#i', $base) && !str_starts_with($base, 'http://localhost') && !str_starts_with($base, 'http://127.0.0.1')) {
			$issues[] = 'base_url 必须是有效的 HTTP(S) 地址';
		}
		return $issues;
	}

	/**
	 * 校验并在不完整时抛出异常（登录流程使用）。
	 *
	 * @throws ConfigurationException
	 */
	public function assertValid(): void {
		$issues = $this->validate();
		if ($issues !== []) {
			throw new ConfigurationException($issues);
		}
	}

	private function getValue(string $key, string $default): string {
		return $this->appConfig->getValueString(Application::APP_ID, $key, $default);
	}

	private function setValue(string $key, string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, $key, $value);
	}

	private function getBool(string $key, bool $default): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, $key, $default);
	}

	private function setBool(string $key, bool $value): void {
		$this->appConfig->setValueBool(Application::APP_ID, $key, $value);
	}
}
