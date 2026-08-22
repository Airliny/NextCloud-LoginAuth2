<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Binding>
 */
class BindingMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'airliny_sso_bindings', Binding::class);
	}

	public function findByUid(string $uid): ?Binding {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		try {
			/** @var Binding|null $binding */
			$binding = $this->findEntity($qb);
			return $binding;
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		}
	}

	public function findBySub(string $sub): ?Binding {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('sub', $qb->createNamedParameter($sub, IQueryBuilder::PARAM_STR)));
		try {
			/** @var Binding|null $binding */
			$binding = $this->findEntity($qb);
			return $binding;
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return Binding[]
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('bound_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function deleteByUid(string $uid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		return $qb->executeStatement() > 0;
	}
}
