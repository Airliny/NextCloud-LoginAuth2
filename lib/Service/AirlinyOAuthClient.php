<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use OCA\UserAirliny\Exception\SsoProtocolException;
use OCA\UserAirliny\Exception\SsoTransportException;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * 与 Airliny 统一认证中心通信的 OAuth 2.0 客户端。
 *
 * 流程遵循 account.airliny.com 开发者文档：
 *   授权码 + PKCE(S256) → POST /oauth/token → GET /oauth/userinfo
 */
class AirlinyOAuthClient {

	private const HTTP_TIMEOUT = 20;
	private const CONNECT_TIMEOUT = 10;

	private ConfigService $config;
	private IClientService $clientService;
	private LoggerInterface $logger;
	private IL10N $l10n;

	public function __construct(ConfigService $config,
		IClientService $clientService,
		LoggerInterface $logger,
		IL10N $l10n) {
		$this->config = $config;
		$this->clientService = $clientService;
		$this->logger = $logger;
		$this->l10n = $l10n;
	}

	/**
	 * 构造授权端点 URL。
	 */
	public function buildAuthorizeUrl(string $redirectUri, string $state, string $codeChallenge): string {
		$params = [
			'client_id' => $this->config->getClientId(),
			'redirect_uri' => $redirectUri,
			'response_type' => 'code',
			'scope' => $this->config->getScopeString(),
			'state' => $state,
			'code_challenge' => $codeChallenge,
			'code_challenge_method' => 'S256',
		];
		return $this->config->getEndpoints()['authorize'] . '?' . http_build_query($params);
	}

	/**
	 * 用授权码换取令牌。
	 *
	 * @return array<string, mixed> 形如 {access_token, token_type, expires_in, refresh_token?, scope}
	 *
	 * @throws SsoTransportException 网络传输失败
	 * @throws SsoProtocolException 认证中心返回协议错误
	 */
	public function requestTokensWithAuthorizationCode(string $code, string $redirectUri, string $codeVerifier): array {
		$body = [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->config->getClientId(),
			'client_secret' => $this->config->getClientSecret(),
			'redirect_uri' => $redirectUri,
			'code_verifier' => $codeVerifier,
		];

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($this->config->getEndpoints()['token'], [
				'body' => $body,
				'timeout' => self::HTTP_TIMEOUT,
				'connect_timeout' => self::CONNECT_TIMEOUT,
			]);
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			$this->logger->error('[user_airliny] 无法连接认证中心 token 端点', ['exception' => $e]);
			throw new SsoTransportException($this->l10n->t('无法连接统一认证中心，请稍后重试或联系管理员。'));
		} catch (\Throwable $e) {
			// Guzzle 的 BadResponseException 等：尝试解析响应体中的错误信息
			$resp = method_exists($e, 'getResponse') ? $e->getResponse() : null;
			$parsed = null;
			if ($resp !== null) {
				$parsed = json_decode((string)$resp->getBody(), true);
			}
			if (is_array($parsed)) {
				$err = (string)($parsed['error'] ?? 'unknown_error');
				$desc = (string)($parsed['error_description'] ?? '');
				$this->logger->warning('[user_airliny] token 端点返回错误', ['error' => $err, 'description' => $desc]);
				throw new SsoProtocolException(
					$this->l10n->t('认证中心拒绝了本次授权：%s', [$desc !== '' ? $desc : $err]),
					$err
				);
			}
			$this->logger->error('[user_airliny] token 请求失败', ['exception' => $e]);
			throw new SsoTransportException($this->l10n->t('与统一认证中心通信失败，请稍后重试或联系管理员。'));
		}

		$data = json_decode($response->getBody(), true);
		if (!is_array($data) || !isset($data['access_token']) || !is_string($data['access_token'])) {
			$this->logger->warning('[user_airliny] token 响应缺少 access_token');
			throw new SsoProtocolException($this->l10n->t('认证中心返回的令牌响应无效。'));
		}
		return $data;
	}

	/**
	 * 使用 access_token 获取用户身份信息。
	 *
	 * @return array<string, mixed> 至少包含 sub；按 scope 可能含 username/display_name/email/email_verified/avatar_url/role
	 *
	 * @throws SsoTransportException
	 * @throws SsoProtocolException 缺少 sub 等不合法响应
	 */
	public function fetchUserInfo(string $accessToken): array {
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($this->config->getEndpoints()['userinfo'], [
				'headers' => ['Authorization' => 'Bearer ' . $accessToken],
				'timeout' => self::HTTP_TIMEOUT,
				'connect_timeout' => self::CONNECT_TIMEOUT,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('[user_airliny] userinfo 请求失败', ['exception' => $e]);
			throw new SsoTransportException($this->l10n->t('获取用户身份信息失败，请稍后重试或联系管理员。'));
		}

		$userInfo = json_decode($response->getBody(), true);
		if (!is_array($userInfo)
			|| !isset($userInfo['sub'])
			|| !is_scalar($userInfo['sub'])
			|| ((string)$userInfo['sub']) === '') {
			$this->logger->warning('[user_airliny] userinfo 响应无效（缺少 sub）',
				['keys' => is_array($userInfo) ? array_keys($userInfo) : null]);
			throw new SsoProtocolException($this->l10n->t('认证中心返回的用户身份信息无效。'));
		}
		// 统一为字符串键值
		foreach (['sub', 'username', 'display_name', 'email', 'avatar_url', 'role'] as $k) {
			if (isset($userInfo[$k]) && is_scalar($userInfo[$k])) {
				$userInfo[$k] = (string)$userInfo[$k];
			}
		}
		return $userInfo;
	}
}
