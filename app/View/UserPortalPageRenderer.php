<?php

declare(strict_types=1);

namespace App\View;

/** 渲染用户登录、身份工作台和供应商申请维护页面。 */
class UserPortalPageRenderer
{
    /**
     * 校验凭据并建立登录会话。
     *
     * @param string $csrf 用于防止跨站请求伪造的令牌。
     * @param string $error 需要在页面展示的错误提示。
     * @return string 返回登录字符串结果。
     */
    public function login(string $csrf, string $error = ''): string
    {
        $csrf = $this->e($csrf); $error = $this->e($error); $alert = $error === '' ? '' : '<p role="alert">' . $error . '</p>';
        return $this->shell('用户登录', '<main><p class="eyebrow">UNIAPI / USER GATE</p><h1>进入双侧工作台</h1>' . $alert . '<form action="/portal/login" method="post"><input type="hidden" name="_csrf" value="' . $csrf . '"><label>用户名<input name="username" autocomplete="username" required></label><label>密码<input type="password" name="password" autocomplete="current-password" required></label><button>登录 / ENTER</button></form></main>');
    }
    /**
     * 处理workspace。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @param array{buyer:?array<string,mixed>,supplier:?array<string,mixed>} $workspace 用户的采购方和供应商工作台身份数据。
     * @param string $csrf 用于防止跨站请求伪造的令牌。
     * @param string $active 当前激活的导航项。
     * @return string 返回workspace字符串结果。
     */
    public function workspace(array $session, array $workspace, string $csrf, string $active = 'overview'): string
    {
        $username = $this->e((string) ($session['username'] ?? '')); $csrf = $this->e($csrf);
        $buyer = $workspace['buyer']; $supplier = $workspace['supplier'];
        $supplierState = $supplier === null ? '尚未申请供应商身份' : '供应商状态：' . $this->e((string) ($supplier['status'] ?? 'pending'));
        $supplierForm = $supplier === null ? '<form action="/workspace/supplier/apply" method="post"><input type="hidden" name="_csrf" value="' . $csrf . '"><label>公司名称<input name="company_name" required></label><label>联系人<input name="contact_name" required></label><label>联系邮箱<input name="contact_email" type="email" required></label><button>申请供应商身份</button></form>' : '<form action="/workspace/supplier/update" method="post"><input type="hidden" name="_csrf" value="' . $csrf . '"><label>公司名称<input name="company_name" value="' . $this->e((string) ($supplier['company_name'] ?? '')) . '" required></label><label>联系人<input name="contact_name" value="' . $this->e((string) ($supplier['contact_name'] ?? '')) . '" required></label><label>联系邮箱<input name="contact_email" type="email" value="' . $this->e((string) ($supplier['contact_email'] ?? '')) . '" required></label><button>更新供应商资料</button></form>';
        $catalogLink = $supplier === null ? '' : '<a href="/workspace/supplier/apis">管理 API 商品</a>';
        $content = '<header><strong>UniAPI</strong><span>' . $username . '</span><form action="/portal/logout" method="post"><input type="hidden" name="_csrf" value="' . $csrf . '"><button>退出</button></form></header><main><p class="eyebrow">IDENTITY / SWITCH</p><h1>双侧身份工作台</h1><nav><a href="/workspace/buyer">采购方工作台</a><a href="/workspace/supplier">供应商工作台</a><a href="/market">API 市场</a>' . $catalogLink . '</nav><section class="grid"><article><h2>采购方工作台</h2><p>采购身份已自动开通。</p><strong>' . $this->e((string) ($buyer['display_name'] ?? '采购账户')) . '</strong></article><article><h2>供应商工作台</h2><p>' . $supplierState . '</p>' . $supplierForm . '</article></section></main>';
        return $this->shell('工作台', $content);
    }
    /**
     * 组装包含公共结构和样式的完整页面。
     *
     * @param string $title 页面标题。
     * @param string $body HTTP 请求体原文。
     * @return string 返回shell字符串结果。
     */
    private function shell(string $title, string $body): string { return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->e($title) . ' · UniAPI</title><style>:root{color-scheme:dark;--bg:#080d0f;--panel:#131f21;--line:#2b3b3e;--accent:#42d9c5;--text:#edf5f3;--muted:#9cafad}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(135deg,#080d0f,#0d181a);color:var(--text);font:16px system-ui}header,main{width:min(1120px,calc(100% - 40px));margin:auto}header{min-height:72px;display:flex;align-items:center;gap:20px;justify-content:space-between;border-bottom:1px solid var(--line)}h1{font-size:clamp(38px,7vw,72px);margin:.2em 0}.eyebrow{color:var(--accent);font:12px monospace;letter-spacing:.12em;margin-top:56px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:32px 0}article,main>form{padding:28px;border:1px solid var(--line);background:var(--panel)}form{display:grid;gap:14px}label{display:grid;gap:6px;color:var(--muted)}input,button,a{min-height:44px}input{padding:10px;background:var(--bg);border:1px solid var(--line);color:var(--text)}button,a{padding:10px 14px;border:1px solid var(--accent);background:transparent;color:var(--accent);font-weight:700}nav{display:flex;gap:12px}a{text-decoration:none}@media(max-width:700px){.grid{grid-template-columns:1fr}header{flex-wrap:wrap}}</style></head><body>' . $body . '</body></html>'; }
    /**
     * 转义 HTML 特殊字符，防止页面注入。
     *
     * @param string $value 待写入或校验的值。
     * @return string 返回e字符串结果。
     */
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
