<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Service;

use RuntimeException;
use Throwable;

/**
 * 生成 OAuth state（防 CSRF）与 PKCE S256 verifier/challenge 的静态工具类。
 */
final class SecurityUtil {

	private const STATE_TTL_SECONDS = 300;

	private function __construct() {
		// 静态工具类，禁止实例化
	}

	public static function generateState(): string {
		try {
			return bin2hex(random_bytes(24));
		} catch (Throwable $e) {
			throw new RuntimeException('无法生成安全的随机 state', 0, $e);
		}
	}

	public static function generateCodeVerifier(): string {
		// 64 字节随机数 → base64url 编码（86 字符，符合 RFC 7636 对 verifier 的要求）
		try {
			$bytes = random_bytes(64);
		} catch (Throwable $e) {
			throw new RuntimeException('无法生成安全的 code_verifier', 0, $e);
		}
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}

	public static function challengeFromVerifier(string $verifier): string {
		return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
	}

	public static function isStateExpired(int $issuedAt): bool {
		return ($issuedAt <= 0) || (\time() - $issuedAt > self::STATE_TTL_SECONDS);
	}
}
