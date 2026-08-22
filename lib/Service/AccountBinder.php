<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use OCA\UserAirliny\Db\Binding;
use OCA\UserAirliny\Db\BindingMapper;
use OCA\UserAirliny\Exception\AccountBindingConflictException;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * 维护「SSO 身份 (sub) ↔ 本地账号 (uid)」的首次绑定与一致性校验。
 *
 * 策略（安全优先）：
 *   1. 账号已绑定其他 sub          → 拒绝（本地账号被另一个 SSO 身份认领）
 *   2. sub 已绑定其他账号          → 拒绝（SSO 身份漂移到了别的账号）
 *   3. 双向一致                    → 放行
 *   4. 均未绑定                    → 建立绑定
 * 绑定写入失败时仅告警不阻断登录（绑定是纵深防御，不应造成全站不可登录）。
 */
class AccountBinder {

	private BindingMapper $bindingMapper;
	private LoggerInterface $logger;

	public function __construct(BindingMapper $bindingMapper, LoggerInterface $logger) {
		$this->bindingMapper = $bindingMapper;
		$this->logger = $logger;
	}

	/**
	 * 校验并（首次时）建立绑定。
	 *
	 * @param array<string, mixed> $userInfo
	 *
	 * @throws AccountBindingConflictException 绑定关系冲突
	 */
	public function verifyAndBind(IUser $user, array $userInfo): void {
		$uid = $user->getUID();
		$sub = (string)$userInfo['sub'];

		$byUid = $this->bindingMapper->findByUid($uid);
		$bySub = $this->bindingMapper->findBySub($sub);

		if ($byUid instanceof Binding && $byUid->getSub() !== $sub) {
			$this->logger->error('[user_airliny] 绑定冲突：本地账号已绑定其他 SSO 身份', [
				'uid' => $uid,
				'bound_sub_prefix' => substr($byUid->getSub(), 0, 4) . '***',
				'attempted_sub_prefix' => substr($sub, 0, 4) . '***',
			]);
			throw new AccountBindingConflictException('uid_bound_to_other_sub');
		}

		if ($bySub instanceof Binding && $bySub->getUid() !== $uid) {
			$this->logger->error('[user_airliny] 绑定冲突：SSO 身份已绑定其他本地账号', [
				'sub' => substr($sub, 0, 4) . '***',
				'bound_uid' => $bySub->getUid(),
				'attempted_uid' => $uid,
			]);
			throw new AccountBindingConflictException('sub_bound_to_other_uid');
		}

		if ($byUid instanceof Binding && $bySub instanceof Binding) {
			// 双向一致，放行
			return;
		}

		try {
			if ($byUid === null && $bySub === null) {
				$binding = new Binding();
				$binding->setUid($uid);
				$binding->setSub($sub);
				$binding->setBoundAt(new \DateTimeImmutable('@' . time()));
				$this->bindingMapper->insert($binding);
				$this->logger->info('[user_airliny] 已建立 SSO 身份绑定', [
					'uid' => $uid,
					'sub_prefix' => substr($sub, 0, 4) . '***',
				]);
			}
		} catch (\Throwable $e) {
			// 唯一索引并发冲突等：记录告警，不阻断本次登录
			$this->logger->warning('[user_airliny] 绑定写入失败（不阻断登录）', [
				'uid' => $uid,
				'exception' => $e::class . ': ' . $e->getMessage(),
			]);
		}
	}
}
