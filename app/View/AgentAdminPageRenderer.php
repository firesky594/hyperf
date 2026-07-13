<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\View;

use DateTimeImmutable;
use DateTimeZone;

class AgentAdminPageRenderer
{
    private const TIMEZONE = 'Asia/Shanghai';

    public function login(string $csrfToken, string $username = '', string $error = ''): string
    {
        $csrfToken = $this->escape($csrfToken);
        $username = $this->escape($username);
        $error = $this->escape($error);
        $errorAttributes = $error === '' ? ' hidden' : '';
        $styles = $this->styles();

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>管理员登录 · Agent Admin</title>
    <style>{$styles}</style>
</head>
<body class="login-page">
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <header class="site-header">
        <a class="brand" href="/agent_admin/login" aria-label="Agent Admin 登录页">
            <span class="brand-mark" aria-hidden="true">AA</span>
            <span>
                <strong>Agent Admin</strong>
                <small>CONTROL GATE / 01</small>
            </span>
        </a>
        <div class="system-state" aria-label="系统状态">
            <span class="state-dot" aria-hidden="true"></span>
            <span>AUTH GATE READY</span>
        </div>
    </header>

    <main id="main-content" class="login-main" tabindex="-1">
        <section class="login-shell" aria-labelledby="login-heading">
            <div class="gate-brief">
                <p class="eyebrow">RESTRICTED ACCESS / A-01</p>
                <p class="gate-index" aria-hidden="true">01</p>
                <div class="gate-copy">
                    <p>内部控制入口</p>
                    <p>仅接受已配置的管理员凭据。每个管理员会话均有明确的签发与到期边界。</p>
                </div>
                <dl class="gate-spec">
                    <div><dt>通道</dt><dd>WEB / TLS</dd></div>
                    <div><dt>会话</dt><dd>限时 / HTTP ONLY</dd></div>
                    <div><dt>范围</dt><dd>AGENT ADMIN</dd></div>
                </dl>
            </div>

            <div class="login-panel">
                <div class="panel-rule" aria-hidden="true"><span></span><span></span><span></span></div>
                <p class="eyebrow">IDENTITY CHECK</p>
                <h1 id="login-heading">管理员登录</h1>
                <p class="lede">输入管理员凭据以进入内部运行控制台。</p>

                <div class="alert" role="alert" aria-live="assertive"{$errorAttributes}>{$error}</div>

                <form class="login-form" action="/agent_admin/login" method="post">
                    <input type="hidden" name="_csrf" value="{$csrfToken}">
                    <div class="field">
                        <label for="username">管理员账号</label>
                        <input id="username" name="username" type="text" value="{$username}" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                    </div>
                    <div class="field">
                        <label for="password">密码</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit">
                        <span>验证并进入</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p class="form-note"><span aria-hidden="true">◆</span> 凭据仅用于本次服务器端验证。</p>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <span>AGENT ADMIN</span>
        <span>PRIVATE OPERATIONS SURFACE</span>
    </footer>
</body>
</html>
HTML;
    }

    /**
     * @param array{admin_id?: mixed, username?: mixed, issued_at?: mixed, expires_at?: mixed, csrf_token?: mixed} $session
     */
    public function home(array $session): string
    {
        $adminId = $this->escape((string) ($session['admin_id'] ?? ''));
        $username = $this->escape((string) ($session['username'] ?? ''));
        $issuedAt = $this->escape($this->formatTimestamp((int) ($session['issued_at'] ?? 0)));
        $expiresAt = $this->escape($this->formatTimestamp((int) ($session['expires_at'] ?? 0)));
        $csrfToken = $this->escape((string) ($session['csrf_token'] ?? ''));
        $styles = $this->styles();

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>运行控制台 · Agent Admin</title>
    <style>{$styles}</style>
</head>
<body class="console-page">
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <header class="console-header">
        <a class="brand" href="/agent_admin" aria-label="Agent Admin 首页">
            <span class="brand-mark" aria-hidden="true">AA</span>
            <span>
                <strong>Agent Admin</strong>
                <small>OPERATIONS CONSOLE</small>
            </span>
        </a>
        <div class="console-actions">
            <div class="system-state" aria-label="后台状态正常">
                <span class="state-dot" aria-hidden="true"></span>
                <span>ONLINE</span>
            </div>
            <form action="/agent_admin/logout" method="post">
                <input type="hidden" name="_csrf" value="{$csrfToken}">
                <button class="logout-button" type="submit">安全退出</button>
            </form>
        </div>
    </header>

    <div class="console-layout">
        <aside class="console-rail">
            <p class="rail-label">NAV / 01</p>
            <nav aria-label="控制台导航">
                <a class="nav-item" href="/agent_admin" aria-current="page">
                    <span aria-hidden="true">01</span>
                    <span>总览</span>
                </a>
            </nav>
            <div class="rail-meta">
                <span>区域</span>
                <strong>CN / SH</strong>
                <span>时区</span>
                <strong>UTC +08:00</strong>
            </div>
        </aside>

        <main id="main-content" class="console-main" tabindex="-1">
            <section class="overview-head" aria-labelledby="overview-heading">
                <div>
                    <p class="eyebrow">SYSTEM OVERVIEW / LIVE</p>
                    <h1 id="overview-heading">运行控制台</h1>
                    <p class="lede">第一视图聚焦管理员会话、框架基线与当前访问边界。</p>
                </div>
                <div class="operator-chip" aria-label="当前管理员">
                    <span>当前管理员</span>
                    <strong>{$username}</strong>
                    <small>ID / {$adminId}</small>
                </div>
            </section>

            <section class="metrics-grid" aria-label="系统概况">
                <article class="metric-card session-card">
                    <div class="card-head">
                        <span class="card-code">SESSION / 01</span>
                        <span class="status-label status-active">有效</span>
                    </div>
                    <h2>管理员会话</h2>
                    <dl class="session-times">
                        <div>
                            <dt>签发时间</dt>
                            <dd><time>{$issuedAt}</time></dd>
                        </div>
                        <div>
                            <dt>到期时间</dt>
                            <dd><time>{$expiresAt}</time></dd>
                        </div>
                    </dl>
                    <p class="timezone-note">时间基准 / Asia/Shanghai</p>
                </article>

                <article class="metric-card runtime-card">
                    <div class="card-head">
                        <span class="card-code">RUNTIME / 02</span>
                        <span class="status-label">稳定基线</span>
                    </div>
                    <p class="metric-value">Hyperf 3.1</p>
                    <h2>应用框架</h2>
                    <p>协程服务运行基线。此页面只呈现已确认的框架主版本，不推断实时基础设施指标。</p>
                </article>

                <article class="metric-card access-card">
                    <div class="card-head">
                        <span class="card-code">ACCESS / 03</span>
                        <span class="status-label status-limited">限定</span>
                    </div>
                    <p class="metric-value">独立管理员门禁</p>
                    <h2>访问模型</h2>
                    <p>当前验证已配置的独立管理员身份；所有管理员使用统一权限层级，尚未提供角色分层或 RBAC 权限模型。</p>
                </article>
            </section>

            <section class="boundary-panel" aria-labelledby="boundary-heading">
                <div>
                    <p class="eyebrow">SECURITY BOUNDARY</p>
                    <h2 id="boundary-heading">权限边界说明</h2>
                </div>
                <p>登录成功代表通过管理员访问门禁；当前管理员不区分角色，尚未提供团队、租户或细粒度授权能力。</p>
                <span class="boundary-tag">NO ROLE CLAIMS</span>
            </section>
        </main>
    </div>

    <footer class="site-footer console-footer">
        <span>AGENT ADMIN / INTERNAL</span>
        <span>SESSION-BOUND ACCESS</span>
    </footer>
</body>
</html>
HTML;
    }

    public function unavailable(string $message = '后台服务暂时不可用，请稍后再试。'): string
    {
        return $this->error(503, $message);
    }

    public function error(int $status, string $message): string
    {
        $message = $this->escape($message);
        $styles = $this->styles();
        [$title, $eyebrow, $heading, $footer] = match ($status) {
            419 => ['请求验证失败', 'REQUEST REJECTED', '请求验证失败', 'RELOAD AND RETRY'],
            default => ['服务暂不可用', 'SERVICE INTERRUPTED', '后台服务暂不可用', 'RETRY LATER'],
        };

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{$title} · Agent Admin</title>
    <style>{$styles}</style>
</head>
<body class="status-page">
    <a class="skip-link" href="#main-content">跳到主要内容</a>
    <header class="site-header">
        <a class="brand" href="/agent_admin/login" aria-label="Agent Admin 登录页">
            <span class="brand-mark" aria-hidden="true">AA</span>
            <span><strong>Agent Admin</strong><small>SERVICE STATUS</small></span>
        </a>
    </header>
    <main id="main-content" class="status-main" tabindex="-1">
        <section class="status-panel" aria-labelledby="status-heading">
            <p class="status-code" aria-hidden="true">{$status}</p>
            <p class="eyebrow">{$eyebrow}</p>
            <h1 id="status-heading">{$heading}</h1>
            <p class="status-message" role="alert">{$message}</p>
            <a class="action-link" href="/agent_admin/login">返回登录入口 <span aria-hidden="true">→</span></a>
        </section>
    </main>
    <footer class="site-footer"><span>AGENT ADMIN</span><span>{$footer}</span></footer>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatTimestamp(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->format('Y-m-d H:i:s');
    }

    private function styles(): string
    {
        return <<<'CSS'
:root {
    --ink-0: #080d0f;
    --ink-1: #0d1517;
    --ink-2: #131f21;
    --ink-3: #1a292b;
    --rule: #2b3b3e;
    --rule-strong: #486064;
    --text: #edf5f3;
    --muted: #9cafad;
    --muted-strong: #c1cecc;
    --teal: #42d9c5;
    --teal-dark: #123f3c;
    --warning: #e4b96c;
    --danger: #ff8f85;
    --radius: 2px;
    --content: 1440px;
    --ease: cubic-bezier(.2, .7, .2, 1);
}

* {
    box-sizing: border-box;
}

html {
    min-width: 320px;
    background: var(--ink-0);
    color: var(--text);
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    line-height: 1.5;
}

body {
    min-height: 100vh;
    margin: 0;
    background:
        linear-gradient(rgba(66, 217, 197, .035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(66, 217, 197, .035) 1px, transparent 1px),
        radial-gradient(circle at 80% 10%, rgba(66, 217, 197, .08), transparent 34%),
        var(--ink-0);
    background-size: 32px 32px, 32px 32px, auto, auto;
}

a {
    color: inherit;
}

button,
input {
    font: inherit;
}

button,
.action-link,
input[type="text"],
input[type="password"] {
    min-height: 44px;
}

:focus-visible {
    outline: 3px solid var(--teal);
    outline-offset: 3px;
}

.skip-link {
    position: fixed;
    top: 12px;
    left: 12px;
    z-index: 100;
    padding: 12px 16px;
    color: var(--ink-0);
    background: var(--teal);
    font-weight: 800;
    transform: translateY(-180%);
    transition: transform 160ms var(--ease);
}

.skip-link:focus {
    transform: translateY(0);
}

.site-header,
.console-header,
.site-footer {
    width: min(100%, var(--content));
    margin: 0 auto;
}

.site-header,
.console-header {
    min-height: 80px;
    padding: 16px clamp(20px, 4vw, 56px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 1px solid var(--rule);
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.brand-mark {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border: 1px solid var(--teal);
    color: var(--teal);
    background: var(--ink-1);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .08em;
}

.brand strong,
.brand small {
    display: block;
}

.brand strong {
    font-size: 15px;
    letter-spacing: .04em;
}

.brand small,
.system-state,
.eyebrow,
.card-code,
.status-label,
.rail-label,
.rail-meta,
.site-footer,
.form-note,
.timezone-note,
.boundary-tag {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    text-transform: uppercase;
    letter-spacing: .1em;
}

.brand small {
    margin-top: 2px;
    color: var(--muted);
    font-size: 10px;
}

.system-state {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: var(--muted-strong);
    font-size: 10px;
}

.state-dot {
    width: 8px;
    height: 8px;
    background: var(--teal);
    box-shadow: 0 0 0 4px rgba(66, 217, 197, .12);
}

.login-main,
.status-main {
    width: min(100%, var(--content));
    min-height: calc(100vh - 144px);
    margin: 0 auto;
    padding: clamp(28px, 6vw, 88px) clamp(20px, 4vw, 56px);
    display: grid;
    align-items: center;
}

.login-shell {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(340px, .75fr);
    border: 1px solid var(--rule-strong);
    background: var(--ink-1);
    box-shadow: 18px 18px 0 rgba(0, 0, 0, .24);
}

.gate-brief,
.login-panel,
.metric-card,
.boundary-panel,
.status-panel {
    position: relative;
}

.gate-brief::before,
.login-panel::before,
.metric-card::before,
.status-panel::before {
    content: "";
    position: absolute;
    top: -1px;
    left: -1px;
    width: 18px;
    height: 18px;
    border-top: 2px solid var(--teal);
    border-left: 2px solid var(--teal);
    pointer-events: none;
}

.gate-brief {
    min-height: 560px;
    padding: clamp(28px, 5vw, 64px);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    border-right: 1px solid var(--rule-strong);
    background:
        repeating-linear-gradient(135deg, transparent 0 10px, rgba(66, 217, 197, .025) 10px 11px),
        var(--ink-2);
}

.eyebrow {
    margin: 0 0 18px;
    color: var(--teal);
    font-size: 11px;
    font-weight: 700;
}

.gate-index {
    margin: auto 0 0;
    color: transparent;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: clamp(110px, 18vw, 230px);
    font-weight: 800;
    line-height: .8;
    -webkit-text-stroke: 1px var(--rule-strong);
}

.gate-copy {
    max-width: 540px;
    margin-top: 36px;
}

.gate-copy p:first-child {
    margin: 0 0 8px;
    color: var(--text);
    font-size: clamp(22px, 3vw, 34px);
    font-weight: 750;
}

.gate-copy p:last-child {
    max-width: 42ch;
    margin: 0;
    color: var(--muted);
}

.gate-spec {
    margin: 36px 0 0;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    border-top: 1px solid var(--rule);
}

.gate-spec div {
    padding: 14px 12px 0 0;
}

.gate-spec dt,
.gate-spec dd {
    margin: 0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 10px;
}

.gate-spec dt {
    color: var(--muted);
}

.gate-spec dd {
    margin-top: 4px;
    color: var(--muted-strong);
}

.login-panel {
    padding: clamp(30px, 5vw, 64px);
    align-self: center;
}

.panel-rule {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
}

.panel-rule span {
    width: 24px;
    height: 5px;
    border-left: 1px solid var(--ink-1);
    background: var(--rule-strong);
}

.panel-rule span:first-child {
    background: var(--teal);
}

h1,
h2,
p {
    overflow-wrap: anywhere;
}

h1 {
    margin: 0;
    font-size: clamp(34px, 6vw, 64px);
    line-height: .98;
    letter-spacing: -.045em;
}

.login-panel h1 {
    font-size: clamp(36px, 5vw, 58px);
}

.lede {
    max-width: 56ch;
    margin: 18px 0 0;
    color: var(--muted);
}

.alert {
    min-width: 0;
    margin: 24px 0 0;
    padding: 12px 14px;
    border-left: 3px solid var(--danger);
    color: #ffd4d0;
    background: rgba(255, 143, 133, .08);
    font-size: 14px;
    overflow-wrap: anywhere;
}

.alert[hidden] {
    display: none;
}

.login-form {
    margin-top: 30px;
    display: grid;
    gap: 20px;
}

.field {
    display: grid;
    gap: 8px;
}

.field label {
    color: var(--muted-strong);
    font-size: 13px;
    font-weight: 700;
}

.field input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--rule-strong);
    border-radius: 0;
    color: var(--text);
    background: var(--ink-0);
    caret-color: var(--teal);
    transition: border-color 160ms var(--ease), box-shadow 160ms var(--ease);
}

.field input:hover {
    border-color: var(--muted);
}

.field input:focus {
    border-color: var(--teal);
    box-shadow: inset 3px 0 0 var(--teal);
}

.login-form button,
.logout-button {
    border: 1px solid var(--teal);
    border-radius: var(--radius);
    cursor: pointer;
    font-weight: 800;
    transition: color 160ms var(--ease), background 160ms var(--ease), transform 160ms var(--ease);
}

.login-form button {
    min-height: 52px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--ink-0);
    background: var(--teal);
}

.login-form button:hover,
.login-form button:focus-visible {
    background: #7eeadb;
}

.login-form button:active,
.logout-button:active,
.action-link:active {
    transform: translateY(1px);
}

.form-note {
    margin: 18px 0 0;
    color: var(--muted);
    font-size: 9px;
}

.form-note span {
    color: var(--teal);
}

.site-footer {
    min-height: 64px;
    padding: 18px clamp(20px, 4vw, 56px);
    display: flex;
    justify-content: space-between;
    gap: 20px;
    border-top: 1px solid var(--rule);
    color: var(--muted);
    font-size: 9px;
}

.console-header {
    position: relative;
}

.console-header::after {
    content: "";
    position: absolute;
    left: clamp(20px, 4vw, 56px);
    bottom: -1px;
    width: 86px;
    height: 2px;
    background: var(--teal);
}

.console-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logout-button {
    min-height: 44px;
    padding: 0 16px;
    color: var(--teal);
    background: transparent;
}

.logout-button:hover,
.logout-button:focus-visible {
    color: var(--ink-0);
    background: var(--teal);
}

.console-layout {
    width: min(100%, var(--content));
    min-height: calc(100vh - 144px);
    margin: 0 auto;
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
}

.console-rail {
    padding: 30px 20px 30px clamp(20px, 4vw, 56px);
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--rule);
    background: rgba(13, 21, 23, .78);
}

.rail-label {
    margin: 0 0 16px;
    color: var(--muted);
    font-size: 9px;
}

.nav-item {
    min-height: 48px;
    padding: 0 12px;
    display: grid;
    grid-template-columns: 30px 1fr;
    align-items: center;
    border-left: 2px solid var(--teal);
    color: var(--text);
    background: var(--teal-dark);
    text-decoration: none;
    font-size: 14px;
    font-weight: 750;
}

.nav-item span:first-child {
    color: var(--teal);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 10px;
}

.rail-meta {
    margin-top: auto;
    padding-top: 24px;
    display: grid;
    gap: 4px;
    border-top: 1px solid var(--rule);
    font-size: 9px;
}

.rail-meta span {
    color: var(--muted);
}

.rail-meta strong {
    margin-bottom: 10px;
    color: var(--muted-strong);
    font-weight: 600;
}

.console-main {
    padding: clamp(32px, 5vw, 64px);
}

.overview-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 32px;
}

.operator-chip {
    min-width: 0;
    padding: 14px 16px;
    flex: 0 1 220px;
    border-top: 1px solid var(--teal);
    border-bottom: 1px solid var(--rule);
    background: var(--ink-1);
    overflow-wrap: anywhere;
}

.operator-chip span,
.operator-chip strong,
.operator-chip small {
    min-width: 0;
    display: block;
    overflow-wrap: anywhere;
}

.operator-chip span,
.operator-chip small {
    color: var(--muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 9px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.operator-chip strong {
    margin: 5px 0;
    font-size: 17px;
}

.metrics-grid {
    margin-top: clamp(32px, 5vw, 56px);
    display: grid;
    grid-template-columns: 1.25fr 1fr 1fr;
    border-top: 1px solid var(--rule-strong);
    border-left: 1px solid var(--rule-strong);
}

.metric-card {
    min-height: 310px;
    padding: clamp(22px, 3vw, 32px);
    border-right: 1px solid var(--rule-strong);
    border-bottom: 1px solid var(--rule-strong);
    background: rgba(13, 21, 23, .94);
}

.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.card-code,
.status-label {
    font-size: 9px;
}

.card-code {
    color: var(--muted);
}

.status-label {
    padding: 4px 6px;
    border: 1px solid var(--rule-strong);
    color: var(--muted-strong);
}

.status-active {
    border-color: var(--teal-dark);
    color: var(--teal);
    background: rgba(66, 217, 197, .06);
}

.status-limited {
    border-color: #634f2c;
    color: var(--warning);
}

.metric-card h2 {
    margin: 14px 0 0;
    font-size: 16px;
}

.metric-card > p:not(.metric-value) {
    color: var(--muted);
    font-size: 14px;
}

.metric-value {
    margin: 46px 0 0;
    color: var(--text);
    font-size: clamp(24px, 3vw, 38px);
    font-weight: 800;
    letter-spacing: -.04em;
}

.runtime-card .metric-value {
    color: var(--teal);
}

.session-times {
    margin: 44px 0 0;
    display: grid;
    gap: 20px;
}

.session-times div {
    padding-bottom: 12px;
    border-bottom: 1px solid var(--rule);
}

.session-times dt {
    color: var(--muted);
    font-size: 11px;
}

.session-times dd {
    margin: 5px 0 0;
    color: var(--muted-strong);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: clamp(13px, 1.5vw, 16px);
}

.timezone-note {
    margin: 18px 0 0;
    color: var(--muted);
    font-size: 8px;
}

.boundary-panel {
    margin-top: 24px;
    padding: 24px;
    display: grid;
    grid-template-columns: minmax(180px, .6fr) minmax(260px, 1fr) auto;
    align-items: center;
    gap: 28px;
    border: 1px solid var(--rule);
    background: var(--ink-1);
}

.boundary-panel .eyebrow {
    margin-bottom: 7px;
}

.boundary-panel h2 {
    margin: 0;
    font-size: 18px;
}

.boundary-panel > p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}

.boundary-tag {
    padding: 7px 9px;
    border: 1px solid #634f2c;
    color: var(--warning);
    font-size: 8px;
    white-space: nowrap;
}

.status-main {
    place-items: center;
}

.status-panel {
    width: min(100%, 760px);
    padding: clamp(32px, 6vw, 72px);
    border: 1px solid var(--rule-strong);
    background: var(--ink-1);
    box-shadow: 18px 18px 0 rgba(0, 0, 0, .24);
}

.status-code {
    position: absolute;
    top: 20px;
    right: 24px;
    margin: 0;
    color: var(--rule-strong);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: clamp(38px, 7vw, 78px);
    font-weight: 800;
}

.status-message {
    margin: 24px 0;
    padding: 14px 16px;
    border-left: 3px solid var(--warning);
    color: var(--muted-strong);
    background: rgba(228, 185, 108, .06);
}

.action-link {
    min-height: 44px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    gap: 16px;
    border: 1px solid var(--teal);
    color: var(--teal);
    text-decoration: none;
    font-weight: 800;
    transition: color 160ms var(--ease), background 160ms var(--ease), transform 160ms var(--ease);
}

.action-link:hover,
.action-link:focus-visible {
    color: var(--ink-0);
    background: var(--teal);
}

@media (max-width: 980px) {
    .login-shell {
        grid-template-columns: 1fr;
    }

    .gate-brief {
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--rule-strong);
    }

    .gate-index {
        position: absolute;
        right: 20px;
        bottom: 18px;
        font-size: 130px;
        opacity: .55;
    }

    .gate-copy,
    .gate-spec {
        position: relative;
        z-index: 1;
    }

    .console-layout {
        grid-template-columns: 1fr;
    }

    .console-rail {
        padding: 14px clamp(20px, 4vw, 56px);
        border-right: 0;
        border-bottom: 1px solid var(--rule);
    }

    .rail-label,
    .rail-meta {
        display: none;
    }

    .nav-item {
        max-width: 220px;
    }

    .metrics-grid {
        grid-template-columns: 1fr 1fr;
    }

    .session-card {
        grid-column: 1 / -1;
    }

    .boundary-panel {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .boundary-tag {
        justify-self: start;
    }
}

@media (max-width: 640px) {
    .site-header,
    .console-header {
        min-height: 72px;
    }

    .system-state {
        display: none;
    }

    .login-main,
    .status-main {
        align-items: start;
    }

    .login-shell,
    .status-panel {
        box-shadow: 8px 8px 0 rgba(0, 0, 0, .24);
    }

    .gate-spec {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .gate-spec div {
        display: flex;
        gap: 10px;
    }

    .gate-index {
        display: none;
    }

    .site-footer {
        display: grid;
    }

    .console-actions {
        gap: 8px;
    }

    .logout-button {
        padding: 0 12px;
    }

    .console-main {
        padding: 28px 20px 44px;
    }

    .overview-head {
        display: grid;
    }

    .operator-chip {
        min-width: 0;
    }

    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .session-card {
        grid-column: auto;
    }

    .metric-card {
        min-height: 260px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
    }
}
CSS;
    }
}
