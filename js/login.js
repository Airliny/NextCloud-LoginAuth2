/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * 登录页行为增强脚本。
 * 「使用统一认证中心登录」按钮由 Nextcloud 原生 alternative-login 机制渲染，
 * 本脚本只负责两件可选的事：
 *   1. autoRedirect —— 访问登录页时直接跳转统一认证中心（?noredir=1 可跳过）
 *   2. hideLocalPassword —— 视觉上隐藏本地密码登录表单
 * 配置来自 LoginPageListener 输出的 <meta name="user-airliny-sso-config"> 标签。
 */
(function () {
	'use strict'

	function readConfig() {
		var meta = document.querySelector('meta[name="user-airliny-sso-config"]')
		if (!meta) {
			return null
		}
		try {
			return JSON.parse(meta.getAttribute('content'))
		} catch (e) {
			return null
		}
	}

	function maybeAutoRedirect(cfg) {
		if (!cfg.autoRedirect) {
			return false
		}
		var params = new URLSearchParams(window.location.search)
		// 逃生通道与循环保护
		if (params.has('noredir') || params.has('from_user_airliny')) {
			return false
		}
		var target = params.get('redirect_url') || window.location.pathname + window.location.search
		window.location.replace(cfg.loginUrl + '?redirect_url=' + encodeURIComponent(target))
		return true
	}

	function hideLocalPassword(cfg) {
		if (!cfg.hideLocalPassword) {
			return
		}
		var css = [
			// 用户名 / 密码 / 记住我 输入行与提交按钮、找回密码链接
			'[data-login-form-input-user]',
			'[data-login-form-input-password]',
			'[data-login-form-input-rememberme]',
			'[data-login-form-submit]',
			'#lost-password',
			'.login-box__lost-password'
		].join(',') + '{display:none !important}'
		var style = document.createElement('style')
		style.id = 'user-airliny-hide-local'
		style.textContent = css
		document.head.appendChild(style)

		// 登录表单由前端异步挂载，样式注入无需等待元素，属性选择器天然命中
	}

	function init() {
		var cfg = readConfig()
		if (!cfg) {
			return
		}
		maybeAutoRedirect(cfg)
		hideLocalPassword(cfg)
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})()
