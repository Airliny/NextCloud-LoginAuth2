<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Airliny
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\UserAirliny\Exception;

/**
 * 与统一认证中心通信失败（网络 / DNS / 超时等传输层错误）。
 */
class SsoTransportException extends AirlinySsoException {
}
