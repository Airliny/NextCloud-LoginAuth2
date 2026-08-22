<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Controller;

use OCA\UserAirliny\Db\BindingMapper;
use OCA\UserAirliny\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AdminRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class SettingsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigService $config,
		private BindingMapper $bindingMapper,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * 保存管理设置。
	 */
	#[AdminRequired]
	public function save(
		string $base_url = '',
		string $client_id = '',
		string $client_secret = '',
		string $scopes = '',
		string $match_strategy = '',
		int $auto_redirect = 0,
		int $hide_local_password = 0,
		int $sync_display_name = 0,
	): Response {
		if (!$this->isAdmin()) {
			return $this->redirectToSettings(['error' => 'forbidden']);
		}

		// client_secret：留空表示保持不变；输入新值则替换；显式传 "-" 表示清除
		if ($client_secret === '-') {
			$this->config->setClientSecret('');
			$this->logger->info('[user_airliny] 已清除 client_secret');
		} elseif (trim($client_secret) !== '') {
			$this->config->setClientSecret(trim($client_secret));
			$this->logger->info('[user_airliny] client_secret 已更新（加密存储）');
		}

		if ($base_url !== '') {
			$this->config->setBaseUrl($base_url);
		}
		if ($client_id !== '') {
			$this->config->setClientId($client_id);
		} else {
			$this->config->setClientId('');
		}
		$this->config->setScopesString($scopes);
		if (in_array($match_strategy, [
			ConfigService::MATCH_EMAIL_USERNAME,
			ConfigService::MATCH_USERNAME_EMAIL,
			ConfigService::MATCH_EMAIL_ONLY,
			ConfigService::MATCH_USERNAME_ONLY,
		], true)) {
			$this->config->setMatchStrategy($match_strategy);
		}
		$this->config->setAutoRedirect($auto_redirect === 1);
		$this->config->setLocalPasswordHidden($hide_local_password === 1);
		$this->config->setDisplayNameSync($sync_display_name === 1);

		$issues = $this->config->validate();
		return $this->redirectToSettings(
			$issues === [] ? ['saved' => '1'] : ['saved' => '1', 'issues' => implode(';', $issues)]
		);
	}

	/**
	 * 解除本地账号的 SSO 身份绑定。
	 */
	#[AdminRequired]
	public function unbind(string $uid = ''): Response {
		if (!$this->isAdmin()) {
			return $this->redirectToSettings(['error' => 'forbidden']);
		}
		if ($uid === '') {
			return $this->redirectToSettings(['unbind' => 'missing']);
		}
		try {
			$deleted = $this->bindingMapper->deleteByUid($uid);
			$this->logger->info('[user_airliny] 管理员操作：解除 SSO 绑定', ['uid' => $uid, 'existed' => $deleted]);
			return $this->redirectToSettings(['unbound' => $uid]);
		} catch (Throwable $e) {
			$this->logger->error('[user_airliny] 解除绑定失败', ['uid' => $uid, 'exception' => $e::class . ':' . $e->getMessage()]);
			return $this->redirectToSettings(['unbind_error' => '1']);
		}
	}

	private function isAdmin(): bool {
		$user = $this->userSession->getUser();
		return $user !== null && $this->groupManager->isAdmin($user->getUID());
	}

	/**
	 * @param array<string, string> $query
	 */
	private function redirectToSettings(array $query = []): RedirectResponse {
		$url = '/settings/admin/user_airliny';
		if ($query !== []) {
			$url .= '?' . http_build_query($query);
		}
		return new RedirectResponse($this->urlGenerator->getAbsoluteURL($url));
	}
}
