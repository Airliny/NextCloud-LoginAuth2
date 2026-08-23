/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * 登录页注入「使用统一认证中心登录」按钮。
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

	function currentRedirectTarget() {
		var params = new URLSearchParams(window.location.search)
		var target = params.get('redirect_url')
		if (target) {
			return target
		}
		// 保留当前路径（登录页本身），登录后回到原页面
		return window.location.pathname + window.location.search
	}

	function buildButton(cfg, redirectUrl) {
		var link = document.createElement('a')
		link.className = 'airliny-sso-button'
		link.href = cfg.loginUrl + (cfg.loginUrl.indexOf('?') === -1 ? '?' : '&') +
			'redirect_url=' + encodeURIComponent(redirectUrl)

		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
		icon.setAttribute('width', '18')
		icon.setAttribute('height', '18')
		icon.setAttribute('viewBox', '0 0 32 32')
		icon.innerHTML = '<g fill="none" stroke="currentColor" stroke-width="3" '
			+ 'stroke-linecap="round">'
			+ '<circle cx="9.5" cy="16" r="5"/>'
			+ '<path d="M14.8 16H27"/>'
			+ '<path d="M22 16v4.5"/>'
			+ '<path d="M27 16v6"/></g>'

		link.appendChild(icon)
		link.appendChild(document.createTextNode(cfg.label || 'SSO'))
		return link
	}

	function injectButton(cfg) {
		var form = document.querySelector('#login-form') ||
			document.querySelector('form[name="login"]') ||
			document.querySelector('.wrapper form')
		if (!form) {
			return
		}
		// 防止重复注入（例如脚本被加载两次）
		if (document.querySelector('.airliny-sso-button')) {
			return
		}

		var wrapper = document.createElement('div')
		wrapper.className = 'airliny-sso-wrapper'

		var divider = document.createElement('div')
		divider.className = 'airliny-sso-divider'
		divider.textContent = '— 或 —'

		wrapper.appendChild(divider)
		wrapper.appendChild(buildButton(cfg, currentRedirectTarget()))

		var submitRow = form.querySelector('#submit')
		var anchor = null
		while (submitRow && submitRow !== form && anchor === null) {
			var next = submitRow.nextElementSibling
			anchor = next
			submitRow = next
		}
		if (anchor && anchor.parentElement === form) {
			form.insertBefore(wrapper, anchor)
		} else if (submitRow === form) {
			form.appendChild(wrapper)
		} else {
			form.appendChild(wrapper)
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
		window.location.replace(cfg.loginUrl + '?redirect_url=' +
			encodeURIComponent(params.get('redirect_url') || '/apps/dashboard/'))
		return true
	}

	function hideLocalPassword(cfg) {
		if (!cfg.hideLocalPassword) {
			return
		}
		var form = document.querySelector('#login-form') ||
			document.querySelector('form[name="login"]') ||
			document.querySelector('.wrapper form')
		if (!form) {
			return
		}
		// 仅视觉隐藏用户名/密码输入区，保留 SSO 按钮
		var fields = form.querySelectorAll('.grouptop, .groupbottom, #user, #password')
		for (var i = 0; i < fields.length; i++) {
			fields[i].style.display = 'none'
		}
		var divider = document.querySelector('.airliny-sso-divider')
		if (divider) {
			divider.style.display = 'none'
		}
	}

	function init() {
		var cfg = readConfig()
		if (!cfg) {
			return
		}
		if (maybeAutoRedirect(cfg)) {
			return
		}
		injectButton(cfg)
		hideLocalPassword(cfg)
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})()
