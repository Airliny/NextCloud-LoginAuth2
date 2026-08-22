<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

/**
 * 创建 SSO 身份绑定表。
 *
 * oc_airliny_sso_bindings:
 *   id        自增主键
 *   uid       本地账号 ID（唯一）
 *   sub       认证中心身份标识（唯一）
 *   bound_at  绑定时间
 */
class Version1000Date20260201000000 implements IMigrationStep {

	public function name(): string {
		return 'Create airliny SSO binding table';
	}

	public function description(): string {
		return '创建 user_airliny 应用的 SSO 身份绑定表 (airliny_sso_bindings)';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('airliny_sso_bindings')) {
			$table = $schema->createTable('airliny_sso_bindings');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('sub', 'string', [
				'notnull' => true,
				'length' => 191,
			]);
			$table->addColumn('bound_at', 'datetime', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['uid'], 'airliny_bind_uid');
			$table->addUniqueIndex(['sub'], 'airliny_bind_sub');
			return $schema;
		}
		return null;
	}
}
