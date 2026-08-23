# user_airliny — Airliny SSO 登录（Nextcloud 定制应用）

> 仓库：[Airliny/NextCloud-LoginAuth2](https://github.com/Airliny/NextCloud-LoginAuth2)

将 Nextcloud 与你的 **Airliny 统一认证中心**（`https://account.airliny.com`）对接：
用户在登录页点击「使用统一认证中心登录」，经 **OAuth 2.0 授权码 + PKCE (S256)** 认证后，
直接登录到**已存在**的 Nextcloud 账号。

> 本应用**不会自动创建账号（无 JIT）**：SSO 身份必须匹配到一个已有的本地账号，
> 匹配不到则拒绝登录并给出明确提示。

兼容 **Nextcloud 31 – 34**（含最新稳定版 34）。

---

## 目录

1. [工作原理](#工作原理)
2. [安装](#安装)
3. [在统一认证中心注册应用](#在统一认证中心注册应用)
4. [配置 Nextcloud](#配置-nextcloud)
5. [账号匹配规则](#账号匹配规则)
6. [安全设计](#安全设计)
7. [常见问题](#常见问题)
8. [目录结构](#目录结构)

## 工作原理

```
浏览器                     Nextcloud (user_airliny)              统一认证中心
  │                              │                                   │
  │ 1. GET /apps/user_airliny/login                                │
  │──────────────────────────────>│                                  │
  │                              │ 生成 state + PKCE verifier        │
  │  2. 302 → /oauth/authorize?code_challenge&state                 │
  │<─────────────────────────────┼──────────────────────────────────>│
  │            3. 用户在认证中心登录/授权（支持密码、Passkey、2FA）      │
  │  4. 302 → /apps/user_airliny/callback?code&state                │
  │──────────────────────────────>│ 校验 state（防CSRF）               │
  │                              │ 5. POST /oauth/token（code+verifier+secret）
  │                              │──────────────────────────────────>│
  │                              │ 6. GET /oauth/userinfo（Bearer）   │
  │                              │──────────────────────────────────>│
  │                              │ sub / username / email …          │
  │                              │ 7. 匹配已有账号 + 绑定校验          │
  │                              │ 8. 建立会话，跳转目标页             │
  │<─────────────────────────────┘                                   │
```

## 安装

> ⚠️ 目录名必须是应用 ID `user_airliny`，git clone 时请按下面的命令重命名。

### 方式一：从仓库克隆

```bash
cd /var/www/nextcloud/apps        # 按实际 Nextcloud 路径调整
git clone https://github.com/Airliny/NextCloud-LoginAuth2.git user_airliny
sudo chown -R www-data:www-data user_airliny
sudo -u www-data php occ app:enable user_airliny
```

### 方式二：打包安装（推荐用于生产）

```bash
git clone https://github.com/Airliny/NextCloud-LoginAuth2.git user_airliny
cd user_airliny
make dist          # 需要 php-cli；生成 user_airliny-1.0.0.tar.gz

# 上传后在服务器上解压到 apps 目录
sudo tar -xzf user_airliny-1.0.0.tar.gz -C /var/www/nextcloud/apps/
sudo chown -R www-data:www-data /var/www/nextcloud/apps/user_airliny

# 启用
sudo -u www-data php occ app:enable user_airliny
```

### 方式三：Docker 部署（官方 nextcloud 镜像）

```bash
# 拷入容器（/var/www/html 为命名卷，重启后依然生效）
docker cp ./user_airliny <容器名>:/var/www/html/apps/
docker exec -u www-data <容器名> bash -c \
  'chown -R www-data:www-data /var/www/html/apps/user_airliny && php occ app:enable user_airliny'
```

启用后进入 **管理设置 → Airliny SSO 登录**（`/settings/admin/user_airliny`）。

## 在统一认证中心注册应用

1. 登录 `https://account.airliny.com`，进入 **开发者控制台** 创建 OAuth 应用；
2. 获取 `client_id`（格式 `cl_xxxxxxxxxxxx`）与 `client_secret`（仅显示一次，请妥善保存）；
3. 回调地址填写（Nextcloud 地址按实际替换）：

   ```
   https://cloud.example.com/index.php/apps/user_airliny/callback
   ```

   管理面板中会显示当前实例的完整回调地址，可直接复制。

## 配置 Nextcloud

在 **管理设置 → Airliny SSO 登录** 中填写：

| 配置项 | 说明 | 默认值 |
|---|---|---|
| 认证中心地址 | SSO 站点 Base URL | `https://account.airliny.com` |
| Client ID | 开发者控制台获取 | — |
| Client Secret | 加密存储；留空保持不变，输入 `-` 清除 | — |
| Scopes | 空格分隔：`verify userinfo email profile` | `verify userinfo email` |
| 账号匹配策略 | 见下节 | 邮箱优先 |
| 自动跳转 | 打开登录页直接跳 SSO | 关闭 |
| 隐藏密码表单 | 仅前端视觉隐藏 | 关闭 |
| 同步显示名 | 用 SSO 显示名更新本站昵称 | 关闭 |

保存后，登录页会出现「使用统一认证中心登录」按钮。

也可以用命令行验证当前配置是否就绪（无报错即通过）：

```bash
curl -s https://account.airliny.com/.well-known/openid-configuration | python3 -m json.tool
```

## 账号匹配规则

SSO 返回的身份字段按所选策略依次匹配**已存在**的本地账号：

* **邮箱匹配**：`userinfo.email` ↔ Nextcloud 账号邮箱。
  * `email_verified === false` 时邮箱不参与匹配（安全考虑）。
  * 一个邮箱命中多个账号 → 拒绝登录，提示管理员处理（避免歧义登录）。
* **用户名匹配**：`userinfo.username` ↔ Nextcloud 用户 ID（uid）。

被禁用的账号即使匹配成功也会被明确拒绝。**匹配失败时不会创建任何账号**，
错误页会直接提示「该账号尚未注册 ALN Cloud」并说明不支持自动注册，需管理员先开户。

### 身份绑定（防顶替）

每个本地账号首次 SSO 登录成功后，会与其认证中心身份 `sub` 在
`oc_airliny_sso_bindings` 表中锁定绑定：

* 该账号之后只能由同一个 `sub` 登录 —— 即使有人把认证中心的邮箱改成别人的也无法顶替；
* 同一个 `sub` 也只能绑定一个本地账号；
* 账号归属变化时，管理员可在管理面板「SSO 身份绑定记录」中解除绑定。

## 安全设计

| 措施 | 实现 |
|---|---|
| CSRF | 每次登录生成随机 `state`，会话内比对（`hash_equals`），一次性消费，5 分钟过期 |
| 授权码拦截 | 强制 PKCE S256，verifier 仅存服务端会话 |
| 会话固定 | 登录前 `regenerateId()`；完整复刻核心登录事件序列 |
| Secret 存储 | 使用实例密钥经 `ICrypto` 加密落库，界面永不回显明文 |
| 开放重定向 | `redirect_url` 仅接受本站相对路径（拒绝 `//`、`\`、含 scheme 的值） |
| 账号顶替 | sub↔uid 双向唯一绑定校验 |
| 暴力破解 | 对接核心 Bruteforce throttler（失败计数、成功清零） |
| 最小信息 | 默认只请求 `verify userinfo email`，不取多余资料 |

## 常见问题

**Q：登录后桌面客户端 / 手机 App 能用吗？**
A：浏览器会话完全正常。Nextcloud 桌面/移动客户端的设备授权流走的是另一套机制，
如需支持建议为这些客户端单独发放应用密码（设置 → 安全 → 设备与会话）。

**Q：提示“没有匹配的 Nextcloud 账号”？**
A：这是预期行为（无 JIT）。ALN Cloud 不支持自动注册——请让管理员先创建
与认证中心一致（用户名或邮箱）的账号，之后该用户即可 SSO 登录并自动完成绑定。

**Q：管理员改了某用户的邮箱导致匹配不上？**
A：若该用户已有绑定记录，绑定仍指向原身份，不受邮箱改名影响；
若是首次登录前改的，更新本地账号邮箱或切换为用户名匹配即可。

**Q：误绑定了怎么办？**
A：管理员面板 → Airliny SSO 登录 → 「SSO 身份绑定记录」→ 解除绑定，
下次登录会重新绑定。

**Q：自动跳转后想用本地密码登录？**
A：访问 `/login?noredir=1` 即可临时跳过自动跳转。

**Q：如何彻底关闭本地密码登录？**
A：「隐藏本地密码表单」只是前端视觉隐藏。要真正禁用密码认证属于全站安全策略
（例如结合 `occ user:setting`、第三方鉴权插件或反向代理层控制），请谨慎规划。

**Q：Nextcloud 与认证中心都在内网，token 请求被拒？**
A：Nextcloud 默认禁止对内网地址发起 HTTP 请求。如确有需要，在 `config/config.php`
中加入 `'allow_local_remote_servers' => true,`。

## 目录结构

```
user_airliny/
├── appinfo/
│   ├── info.xml                  # 应用元数据（31 ≤ NC ≤ 34）
│   └── routes.php                # /login /callback /admin/*
├── lib/
│   ├── AppInfo/Application.php   # Bootstrap：注册监听器
│   ├── Controller/
│   │   ├── LoginController.php   # 发起授权 + 回调登录（核心流程）
│   │   └── SettingsController.php# 设置保存 / 解绑
│   ├── Service/
│   │   ├── ConfigService.php     # 配置读写（secret 加密）
│   │   ├── AirlinyOAuthClient.php# authorize/token/userinfo
│   │   ├── UserMatcher.php       # 匹配已有账号
│   │   ├── AccountBinder.php     # sub↔uid 绑定防顶替
│   │   ├── LoginCompleter.php    # 编程式建立完整会话
│   │   └── SecurityUtil.php      # state/PKCE 生成
│   ├── Db/                       # Binding 实体 + Mapper
│   ├── Migration/…               # 建表迁移
│   ├── Listener/LoginPageListener.php # 登录页注入按钮
│   └── Settings/                 # 管理面板
├── templates/                    # admin / error 模板
├── css/ js/                      # 前端资源
├── l10n/                         # 中文源串 + 英文翻译
├── composer.json Makefile COPYING CHANGELOG.md README.md
```

---

许可证：AGPL-3.0-or-later（见 `COPYING`）
