<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Controller;

use OCA\UserAirliny\AppInfo\Application;
use OCA\UserAirliny\Exception\AccountBindingConflictException;
use OCA\UserAirliny\Exception\AccountDisabledException;
use OCA\UserAirliny\Exception\AmbiguousIdentityException;
use OCA\UserAirliny\Service\AccountBinder;
use OCA\UserAirliny\Service\AirlinyOAuthClient;
use OCA\UserAirliny\Service\ConfigService;
use OCA\UserAirliny\Service\LoginCompleter;
use OCA\UserAirliny\Service\SecurityUtil;
use OCA\UserAirliny\Service\UserMatcher;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class LoginController extends Controller {

	private const SESSION_KEY_STATE = 'user_airliny.state';
	private const SESSION_KEY_ISSUED_AT = 'user_airliny.state_issued_at';
	private const SESSION_KEY_VERIFIER = 'user_airliny.code_verifier';
	private const SESSION_KEY_REDIRECT = 'user_airliny.redirect_url';

	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigService $config,
		private AirlinyOAuthClient $oauthClient,
		private UserMatcher $matcher,
		private AccountBinder $binder,
		private LoginCompleter $loginCompleter,
		private ISession $session,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * 发起 SSO 登录：生成 state + PKCE 并重定向到统一认证中心。
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function ssoLogin(string $redirect_url = ''): Response {
		// 已登录用户直接跳转目标页
		if ($this->userSession->isLoggedIn()) {
			return new RedirectResponse($this->sanitizeRedirect($redirect_url) ?: $this->urlGenerator->linkToDefaultPageUrl());
		}

		try {
			$this->config->assertValid();
		} catch (\OCA\UserAirliny\Exception\ConfigurationException $e) {
			$this->logger->error('[user_airliny] 配置不完整，无法发起 SSO 登录', ['issues' => $e->getIssues()]);
			return $this->errorPage(
				$this->l10n->t('SSO 登录尚未配置完成'),
				implode('；', $e->getIssues()),
				503
			);
		}

		$safeRedirect = $this->sanitizeRedirect($redirect_url);

		// 生成并暂存一次性凭据（5 分钟有效期）
		$state = SecurityUtil::generateState();
		$verifier = SecurityUtil::generateCodeVerifier();
		$this->session->set(self::SESSION_KEY_STATE, $state);
		$this->session->set(self::SESSION_KEY_ISSUED_AT, time());
		$this->session->set(self::SESSION_KEY_VERIFIER, $verifier);
		$this->session->set(self::SESSION_KEY_REDIRECT, $safeRedirect);

		$callbackUri = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute(Application::APP_ID . '.login.callback')
		);
		$authorizeUrl = $this->oauthClient->buildAuthorizeUrl(
			$callbackUri, $state, SecurityUtil::challengeFromVerifier($verifier)
		);

		return new RedirectResponse($authorizeUrl);
	}

	/**
	 * OAuth 回调：校验 state → 换令牌 → 取身份 → 匹配本地账号 → 建立会话。
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function callback(string $code = '', string $state = '', string $error = '', string $error_description = ''): Response {
		try {
			$this->config->assertValid();
		} catch (\OCA\UserAirliny\Exception\ConfigurationException $e) {
			return $this->errorPage(
				$this->l10n->t('SSO 登录尚未配置完成'),
				implode('；', $e->getIssues()),
				503
			);
		}

		// 1. 认证中心显式报错（用户拒绝授权等）
		if ($error !== '') {
			$this->logger->notice('[user_airliny] 授权端点返回错误', ['error' => $error]);
			return $this->errorPage(
				$this->l10n->t('统一认证中心未完成授权'),
				$error_description !== '' ? $error_description : $error,
				403
			);
		}

		// 2. 校验 state（防 CSRF）与有效期
		$expectedState = $this->session->get(self::SESSION_KEY_STATE);
		$issuedAt = (int)$this->session->get(self::SESSION_KEY_ISSUED_AT);
		$verifier = (string)$this->session->get(self::SESSION_KEY_VERIFIER);
		$targetUrl = $this->sanitizeRedirect((string)$this->session->get(self::SESSION_KEY_REDIRECT));

		// 无论成功失败都立即清除一次性凭据，防止重放
		foreach ([self::SESSION_KEY_STATE, self::SESSION_KEY_ISSUED_AT, self::SESSION_KEY_VERIFIER, self::SESSION_KEY_REDIRECT] as $key) {
			$this->session->remove($key);
		}

		if (!is_string($expectedState) || $expectedState === ''
			|| !hash_equals($expectedState, $state)
			|| $verifier === ''
			|| SecurityUtil::isStateExpired($issuedAt)) {
			$this->logger->notice('[user_airliny] 回调 state 校验失败');
			return $this->errorPage(
				$this->l10n->t('登录请求已过期或状态校验失败'),
				$this->l10n->t('请返回登录页重新发起 SSO 登录。'),
				403
			);
		}

		if ($code === '') {
			return $this->errorPage(
				$this->l10n->t('认证中心未返回授权码'),
				$this->l10n->t('请返回登录页重新发起 SSO 登录。'),
				400
			);
		}

		// 3. 授权码换令牌
		$callbackUri = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute(Application::APP_ID . '.login.callback')
		);
		try {
			$tokens = $this->oauthClient->requestTokensWithAuthorizationCode($code, $callbackUri, $verifier);
			$userInfo = $this->oauthClient->fetchUserInfo((string)$tokens['access_token']);
		} catch (\Throwable $e) {
			$message = $e instanceof \Exception ? $e->getMessage() : $this->l10n->t('与统一认证中心通信失败，请稍后重试或联系管理员。');
			return $this->errorPage(
				$this->l10n->t('SSO 登录失败'),
				$message,
				502
			);
		}

		$sub = (string)$userInfo['sub'];

		// 4. 仅匹配已有账号（无 JIT）
		try {
			$user = $this->matcher->match($userInfo);
		} catch (AmbiguousIdentityException) {
			$this->loginCompleter->registerFailedAttempt();
			return $this->errorPage(
				$this->l10n->t('无法确定唯一匹配的账号'),
				$this->l10n->t('存在多个使用相同邮箱/用户名的 Nextcloud 账号，请联系管理员处理后再登录。'),
				403
			);
		} catch (AccountDisabledException) {
			$this->loginCompleter->registerFailedAttempt();
			return $this->errorPage(
				$this->l10n->t('账号已被禁用'),
				$this->l10n->t('该账号已被管理员禁用，无法登录。如有疑问请联系管理员。'),
				403
			);
		}

		if ($user === null) {
			$this->loginCompleter->registerFailedAttempt();
			return $this->errorPage(
				$this->l10n->t('该账号尚未注册 ALN Cloud'),
				$this->l10n->t('此统一认证中心账号还没有对应本站账号。为保障安全，ALN Cloud 不支持自动注册，请先联系管理员创建账号（用户名或邮箱与认证中心一致）后再登录。'),
				404
			);
		}

		// 5. 身份绑定一致性校验（防顶替）
		try {
			$this->binder->verifyAndBind($user, $userInfo);
		} catch (AccountBindingConflictException $e) {
			$this->logger->error('[user_airliny] 拒绝登录：绑定冲突', [
				'uid' => $user->getUID(),
				'reason' => $e->getReason(),
				'sub_prefix' => substr($sub, 0, 4) . '***',
			]);
			$this->loginCompleter->registerFailedAttempt();
			return $this->errorPage(
				$this->l10n->t('SSO 身份与该账号的绑定关系异常'),
				$this->l10n->t('为防止账号被冒用，本次登录已被拒绝。请联系管理员检查 SSO 绑定记录。'),
				403
			);
		}

		// 6. 建立完整会话
		$this->loginCompleter->syncDisplayName($user, (string)($userInfo['display_name'] ?? ''), $this->config);
		$this->loginCompleter->complete($user);

		// 7. 跳转目标页
		return new RedirectResponse($targetUrl ?: $this->urlGenerator->linkToDefaultPageUrl());
	}

	/**
	 * 开放重定向防护：仅允许本站相对路径。
	 */
	private function sanitizeRedirect(?string $redirect): string {
		if ($redirect === null || $redirect === '') {
			return '';
		}
		$redirect = trim($redirect);
		if ($redirect[0] !== '/') {
			return '';
		}
		if (str_starts_with($redirect, '//')) {
			return '';
		}
		if (str_contains($redirect, '\\') || preg_match('/[\r\n]/', $redirect)) {
			return '';
		}
		if (str_contains($redirect, '://')) {
			return '';
		}
		return $redirect;
	}

	/**
	 * 渲染 guest 布局的错误页。
	 *
	 * 统一在此处做 HTML 转义（detail 中可能包含来自认证中心 URL 参数的不可信内容）。
	 */
	private function errorPage(string $title, string $detail, int $statusCode): TemplateResponse {
		$params = [
			'title' => $title,
			'detail' => nl2br(htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')),
			// noredir=1 防止自动跳转把用户又送回 SSO 造成循环
			'backUrl' => $this->urlGenerator->getAbsoluteURL('/login?noredir=1&from_user_airliny=1'),
			'backLabel' => $this->l10n->t('返回登录页'),
		];
		$response = new TemplateResponse(Application::APP_ID, 'error', $params, 'guest');
		$response->setStatus($statusCode);
		return $response;
	}
}
