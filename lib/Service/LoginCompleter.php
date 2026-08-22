<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use OCA\UserAirliny\AppInfo\Application;
use OC\User\Session as OCUserSession;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\Bruteforce\IThrottler;
use OCP\User\Events\BeforeUserLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;
use Psr\Log\LoggerInterface;

/**
 * 以编程方式为已匹配的本地账号建立完整的 Nextcloud 会话。
 *
 * 序列对齐官方 user_oidc 应用的成熟实现：
 *   regenerateId → setUser → BeforeUserLoggedInEvent → completeLogin
 *   → createSessionToken → UserLoggedInEvent → last-password-confirm 前移
 */
class LoginCompleter {

	private const BRUTEFORCE_ACTION = 'airliny_sso_login';

	private ISession $session;
	private IUserSession $userSession;
	private IEventDispatcher $eventDispatcher;
	private IRequest $request;
	private IThrottler $throttler;
	private LoggerInterface $logger;

	public function __construct(ISession $session,
		IUserSession $userSession,
		IEventDispatcher $eventDispatcher,
		IRequest $request,
		IThrottler $throttler,
		LoggerInterface $logger) {
		$this->session = $session;
		$this->userSession = $userSession;
		$this->eventDispatcher = $eventDispatcher;
		$this->request = $request;
		$this->throttler = $throttler;
		$this->logger = $logger;
	}

	/**
	 * 登录失败时登记暴力破解计数。
	 */
	public function registerFailedAttempt(): void {
		try {
			$this->throttler->registerAttempt(self::BRUTEFORCE_ACTION, $this->request->getRemoteAddress());
		} catch (\Throwable $e) {
			$this->logger->warning('[user_airliny] bruteforce 计数失败', ['exception' => $e::class]);
		}
	}

	/**
	 * 建立 Web 会话并完成登录事件派发。
	 */
	public function complete(IUser $user): void {
		$uid = $user->getUID();

		// 1. 防会话固定：登录前重建会话 ID
		$this->session->regenerateId();

		// 2. 绑定当前用户
		$this->userSession->setUser($user);

		// 3. 完整会话建立（与核心登录一致的事件序列）
		if ($this->userSession instanceof OCUserSession) {
			// 与 user_oidc 相同：手动补齐核心登录流程中的事件与会话令牌
			$this->eventDispatcher->dispatchTyped(new BeforeUserLoggedInEvent($uid, null));
			$this->userSession->completeLogin($user, ['loginName' => $uid, 'password' => '']);
			$this->userSession->createSessionToken($this->request, $uid, $uid);
			$this->eventDispatcher->dispatchTyped(new UserLoggedInEvent($user, $uid, null, false));
		} else {
			// 兜底：非标准 Session 实现时仅保证基础会话可用
			$this->logger->notice('[user_airliny] 非标准 IUserSession 实现，降级为基础登录');
		}

		// 4. SSO 无密码可确认：将「最近密码确认」前移，避免敏感操作反复要求输密码
		$this->session->set('last-password-confirm', time() + 4 * 365 * 24 * 3600);

		// 5. 成功后清空该来源的暴力破解计数
		try {
			$this->throttler->resetDelay($this->request, self::BRUTEFORCE_ACTION);
		} catch (\Throwable $e) {
			// 忽略：清零失败不影响登录
		}

		$this->logger->info('[user_airliny] SSO 登录完成', ['uid' => $uid]);
	}

	/**
	 * 可选：用 SSO 资料同步本地显示名。
	 */
	public function syncDisplayName(IUser $user, string $displayName, ConfigService $config): void {
		if (!$config->isDisplayNameSyncEnabled() || $displayName === '') {
			return;
		}
		if ($displayName !== $user->getDisplayName()) {
			if ($user->setDisplayName($displayName)) {
				$this->logger->debug('[user_airliny] 已同步显示名', ['uid' => $user->getUID()]);
			}
		}
	}
}
