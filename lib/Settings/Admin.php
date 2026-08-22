<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Settings;

use OCA\UserAirliny\AppInfo\Application;
use OCA\UserAirliny\Db\Binding;
use OCA\UserAirliny\Db\BindingMapper;
use OCA\UserAirliny\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	private ConfigService $config;
	private BindingMapper $bindingMapper;
	private IRequest $request;
	private IURLGenerator $urlGenerator;

	public function __construct(ConfigService $config,
		BindingMapper $bindingMapper,
		IRequest $request,
		IURLGenerator $urlGenerator) {
		$this->config = $config;
		$this->bindingMapper = $bindingMapper;
		$this->request = $request;
		$this->urlGenerator = $urlGenerator;
	}

	public function getForm(): TemplateResponse {
		$bindings = [];
		foreach ($this->bindingMapper->findAll() as $binding) {
			/** @var Binding $binding */
			$bindings[] = [
				'uid' => $binding->getUid(),
				'sub' => $binding->getSub(),
				'boundAt' => $binding->getBoundAt()?->format('Y-m-d H:i') ?? '—',
			];
		}

		$params = [
			'baseUrl' => $this->config->getBaseUrl(),
			'clientId' => $this->config->getClientId(),
			'hasClientSecret' => $this->config->hasClientSecret(),
			'scopes' => $this->config->getScopeString(),
			'matchStrategy' => $this->config->getMatchStrategy(),
			'autoRedirect' => $this->config->isAutoRedirectEnabled(),
			'hideLocalPassword' => $this->config->isLocalPasswordHidden(),
			'syncDisplayName' => $this->config->isDisplayNameSyncEnabled(),
			'callbackUrl' => $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->linkToRoute(Application::APP_ID . '.login.callback')
			),
			'issues' => $this->config->validate(),
			'bindings' => $bindings,
			'saved' => $this->request->getParam('saved') === '1',
			'unboundUid' => (string)$this->request->getParam('unbound', ''),
			'unbindError' => $this->request->getParam('unbind_error') === '1',
		];
		return new TemplateResponse(Application::APP_ID, 'admin', $params);
	}

	public function getSection(): string {
		return 'user_airliny';
	}

	public function getPriority(): int {
		return 70;
	}
}
