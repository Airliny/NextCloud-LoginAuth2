<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Db;

use OCP\AppFramework\Db\Entity;

/**
 * SSO 身份 (sub) 与本地账号 (uid) 的绑定关系。
 *
 * 一经绑定即锁定：防止认证中心改邮箱后顶替他人账号，也防同一 sub 漂移到多个账号。
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getSub()
 * @method void setSub(string $sub)
 * @method \DateTime getBoundAt()
 * @method void setBoundAt(\DateTime $boundAt)
 */
class Binding extends Entity {

	/** @var string */
	protected $uid = '';

	/** @var string */
	protected $sub = '';

	/** @var \DateTime */
	protected $boundAt;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('sub', 'string');
		$this->addType('boundAt', 'datetime');
	}
}
