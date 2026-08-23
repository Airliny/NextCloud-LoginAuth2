<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use OCA\UserAirliny\Exception\AccountDisabledException;
use OCA\UserAirliny\Exception\AmbiguousIdentityException;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * 将认证中心返回的身份信息匹配到**已存在**的本地 Nextcloud 账号。
 *
 * 本应用不自动创建账号（无 JIT）；匹配不到即拒绝登录。
 */
class UserMatcher {

	private ConfigService $config;
	private IUserManager $userManager;
	private LoggerInterface $logger;

	public function __construct(ConfigService $config,
		IUserManager $userManager,
		LoggerInterface $logger) {
		$this->config = $config;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	/**
	 * 按 admin 配置的策略尝试匹配。
	 *
	 * @param array<string, mixed> $userInfo userinfo 响应
	 * @return IUser|null 匹配到的启用账号；null 表示未找到
	 *
	 * @throws AmbiguousIdentityException 匹配键命中多个账号
	 * @throws AccountDisabledException 命中的账号已被禁用
	 */
	public function match(array $userInfo): ?IUser {
		$email = trim((string)($userInfo['email'] ?? ''));
		// email_verified 明确为 false 时，邮箱不可用于身份匹配（安全考虑）
		$emailUsable = $email !== '' && ($userInfo['email_verified'] ?? true) !== false;
		if ($email !== '' && !$emailUsable) {
			$this->logger->info('[user_airliny] 邮箱未通过验证，忽略邮箱匹配', ['email_domain' => substr(strrchr($email, '@') ?: '', 1)]);
		}

		$username = trim((string)($userInfo['username'] ?? ''));

		switch ($this->config->getMatchStrategy()) {
			case ConfigService::MATCH_EMAIL_ONLY:
				$chain = [['email']];
				break;
			case ConfigService::MATCH_USERNAME_ONLY:
				$chain = [['username']];
				break;
			case ConfigService::MATCH_USERNAME_EMAIL:
				$chain = [['username'], ['email']];
				break;
			case ConfigService::MATCH_EMAIL_USERNAME:
			default:
				$chain = [['email'], ['username']];
				break;
		}

		foreach ($chain as [$method]) {
			$user = null;
			switch ($method) {
				case 'email':
					if ($emailUsable) {
						$user = $this->matchByEmail($email);
					}
					break;
				case 'username':
					if ($username !== '') {
						$user = $this->matchByUsername($username);
					}
					break;
			}
			if ($user instanceof IUser) {
				$this->logger->debug('[user_airliny] 账号匹配成功',
					['uid' => $user->getUID(), 'matched_by' => $method]);
				return $user;
			}
		}

		$this->logger->warning('[user_airliny] 未找到与 SSO 身份匹配的本地账号',
			['sub' => (string)($userInfo['sub'] ?? ''), 'strategy' => $this->config->getMatchStrategy()]);
		return null;
	}

	/**
	 * @throws AmbiguousIdentityException
	 * @throws AccountDisabledException
	 */
	private function matchByEmail(string $email): ?IUser {
		$users = $this->userManager->getByEmail($email);
		// 注意：不能用 static fn —— 闭包内需调用 $this->isBackendAllowed()
		$users = array_values(array_filter($users, fn (IUser $u): bool => $this->isBackendAllowed($u)));

		if (count($users) > 1) {
			throw new AmbiguousIdentityException('email:' . $this->maskEmail($email));
		}
		if (count($users) === 1) {
			return $this->assertEnabled($users[0], 'email:' . $this->maskEmail($email));
		}
		return null;
	}

	/**
	 * @throws AccountDisabledException
	 */
	private function matchByUsername(string $username): ?IUser {
		$user = $this->userManager->get($username);
		if (!$user instanceof IUser || !$this->isBackendAllowed($user)) {
			return null;
		}
		return $this->assertEnabled($user, 'username:' . $username);
	}

	/**
	 * 禁用账号必须显式报错而不是静默跳过 —— 否则用户会误以为「没有账号」。
	 *
	 * @throws AccountDisabledException
	 */
	private function assertEnabled(IUser $user, string $key): IUser {
		if (!$user->isEnabled()) {
			$this->logger->notice('[user_airliny] 命中已禁用账号，拒绝登录', ['match_key' => $key]);
			throw new AccountDisabledException($user->getUID());
		}
		return $user;
	}

	/**
	 * 目前所有用户后端均允许；保留此钩子便于将来按后端过滤。
	 */
	private function isBackendAllowed(IUser $user): bool {
		return $user->getBackendClassName() !== '';
	}

	private function maskEmail(string $email): string {
		[$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
		$maskedLocal = strlen($local) > 2 ? substr($local, 0, 2) . '***' : $local . '***';
		return $maskedLocal . '@' . $domain;
	}
}
