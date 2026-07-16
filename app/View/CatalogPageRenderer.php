<?php

declare(strict_types=1);

namespace App\View;

/** 渲染供应商 API 编辑发布页面及采购方 API 市场。 */
final class CatalogPageRenderer
{
    /**
     * 处理供应商API 商品列表。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @param list<array<string,mixed>> $products API 商品数据列表。
     * @param string $csrf 用于防止跨站请求伪造的令牌。
     * @return string 返回供应商API 商品列表字符串结果。
     */
    public function supplierProducts(array $session, array $products, string $csrf): string
    {
        $items = '';
        foreach ($products as $product) {
            $draft = $product['draft_version_id'] ?? null;
            $edit = $draft === null ? '' : '<a href="/workspace/supplier/apis/edit?product_id=' . (int) $product['id'] . '&amp;version_id=' . (int) $draft . '">编辑草稿</a>';
            $publishedActions = ($product['current_published_version_id'] ?? null) === null ? '' : '<form action="/workspace/supplier/apis/next-version" method="post"><input type="hidden" name="_csrf" value="' . $this->e($csrf) . '"><input type="hidden" name="product_id" value="' . (int) $product['id'] . '"><label>新版本标识<input name="version" placeholder="v2" required></label><button>创建新版本</button></form>' . ((string) $product['status'] === 'published' ? '<form action="/workspace/supplier/apis/unlist" method="post"><input type="hidden" name="_csrf" value="' . $this->e($csrf) . '"><input type="hidden" name="product_id" value="' . (int) $product['id'] . '"><button>下架商品</button></form>' : '');
            $items .= '<article><h2>' . $this->e((string) $product['name']) . '</h2><p>' . $this->e((string) $product['summary']) . '</p><code>' . $this->e((string) $product['status']) . '</code>' . $edit . $publishedActions . '</article>';
        }
        if ($items === '') { $items = '<p class="empty">当前还没有 API 商品，请先创建第一个草稿。</p>'; }
        $body = '<header><a href="/workspace/supplier">← 供应商工作台</a><span>' . $this->e((string) ($session['username'] ?? '')) . '</span></header><main><p class="eyebrow">SUPPLIER / CATALOG</p><h1>供应商 API 管理</h1><form action="/workspace/supplier/apis/create" method="post"><input type="hidden" name="_csrf" value="' . $this->e($csrf) . '"><label>API 名称<input name="name" required maxlength="128"></label><label>唯一标识<input name="slug" required pattern="[a-z0-9][a-z0-9-]{2,95}"></label><label>简介<textarea name="summary" maxlength="500"></textarea></label><button>创建草稿</button></form><section class="grid">' . $items . '</section></main>';
        return $this->shell('供应商 API 管理', $body);
    }

    /**
     * 处理供应商Editor。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @param array<string,mixed> $data 待处理的业务数据。
     * @param string $csrf 用于防止跨站请求伪造的令牌。
     * @return string 返回供应商Editor字符串结果。
     */
    public function supplierEditor(array $session, array $data, string $csrf): string
    {
        $lines = [];
        foreach (($data['endpoints'] ?? []) as $endpoint) { if (is_array($endpoint)) { $lines[] = implode('|', [(string) ($endpoint['method'] ?? ''), (string) ($endpoint['path'] ?? ''), (string) ($endpoint['name'] ?? ''), (string) ($endpoint['description'] ?? '')]); } }
        $price = number_format(((int) ($data['unit_price_micros'] ?? 0)) / 1_000_000, 6, '.', '');
        $hidden = '<input type="hidden" name="_csrf" value="' . $this->e($csrf) . '"><input type="hidden" name="product_id" value="' . (int) ($data['product_id'] ?? 0) . '"><input type="hidden" name="version_id" value="' . (int) ($data['version_id'] ?? 0) . '">';
        $readonly = (string) ($data['version_status'] ?? 'draft') === 'draft' ? '' : ' readonly';
        $actions = $readonly === '' ? '<button>保存草稿</button></form><form action="/workspace/supplier/apis/publish" method="post">' . $hidden . '<button>发布此版本</button></form>' : '</form><p class="empty">已发布版本只读；如需修改，请从 API 管理页创建新版本。</p>';
        $body = '<header><a href="/workspace/supplier/apis">← API 管理</a><span>' . $this->e((string) ($session['username'] ?? '')) . '</span></header><main><p class="eyebrow">VERSION / EDITOR</p><h1>API 版本编辑</h1><form action="/workspace/supplier/apis/save" method="post">' . $hidden . '<label>API 名称<input name="name" value="' . $this->e((string) ($data['name'] ?? '')) . '" required' . $readonly . '></label><label>简介<textarea name="summary"' . $readonly . '>' . $this->e((string) ($data['summary'] ?? '')) . '</textarea></label><label>版本标识<input name="version" value="' . $this->e((string) ($data['version'] ?? 'v1')) . '" required' . $readonly . '></label><label>接口文档<textarea name="documentation" required' . $readonly . '>' . $this->e((string) ($data['documentation'] ?? '')) . '</textarea></label><label>每次调用价格（CNY）<input name="unit_price" inputmode="decimal" value="' . $price . '" required' . $readonly . '></label><label>端点定义（METHOD|PATH|名称|说明，每行一个）<textarea name="endpoints" required' . $readonly . '>' . $this->e(implode("\n", $lines)) . '</textarea></label>' . $actions . '</main>';
        return $this->shell('API 版本编辑', $body);
    }

    /**
     * 处理API 市场。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @param list<array<string,mixed>> $products API 商品数据列表。
     * @return string 返回API 市场字符串结果。
     */
    public function market(array $session, array $products): string
    {
        $items = '';
        foreach ($products as $product) { $items .= '<article><p class="eyebrow">' . $this->e((string) $product['version']) . '</p><h2><a href="/market/detail?product_id=' . (int) $product['id'] . '">' . $this->e((string) $product['name']) . '</a></h2><p>' . $this->e((string) $product['summary']) . '</p><strong>' . $this->money($product) . '</strong></article>'; }
        if ($items === '') { $items = '<p class="empty">当前没有已发布 API。</p>'; }
        return $this->shell('API 市场', '<header><a href="/workspace/buyer">← 采购方工作台</a><span>' . $this->e((string) ($session['username'] ?? '')) . '</span></header><main><p class="eyebrow">BUYER / MARKET</p><h1>API 市场</h1><section class="grid">' . $items . '</section></main>');
    }

    /**
     * 处理API 市场Detail。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @param array<string,mixed> $product 单个 API 商品数据。
     * @return string 返回API 市场Detail字符串结果。
     */
    public function marketDetail(array $session, array $product): string
    {
        $endpoints = '';
        foreach (($product['endpoints'] ?? []) as $endpoint) { if (is_array($endpoint)) { $endpoints .= '<li><code>' . $this->e((string) ($endpoint['method'] ?? '')) . ' ' . $this->e((string) ($endpoint['path'] ?? '')) . '</code> — ' . $this->e((string) ($endpoint['name'] ?? '')) . '</li>'; } }
        return $this->shell((string) $product['name'], '<header><a href="/market">← API 市场</a><span>' . $this->e((string) ($session['username'] ?? '')) . '</span></header><main><p class="eyebrow">' . $this->e((string) $product['version']) . '</p><h1>' . $this->e((string) $product['name']) . '</h1><p>' . $this->e((string) $product['summary']) . '</p><strong>' . $this->money($product) . '</strong><section><h2>端点</h2><ul>' . $endpoints . '</ul><h2>接口文档</h2><pre>' . $this->e((string) $product['documentation']) . '</pre></section></main>');
    }

    /**
     * 处理金额。
     *
     * @param array<string,mixed> $row 单条数据库查询结果。
     * @return string 返回金额字符串结果。
     */
    private function money(array $row): string { return number_format(((int) ($row['unit_price_micros'] ?? 0)) / 1_000_000, 6, '.', '') . ' ' . $this->e((string) ($row['currency'] ?? 'CNY')); }
    /**
     * 转义 HTML 特殊字符，防止页面注入。
     *
     * @param string $value 待写入或校验的值。
     * @return string 返回e字符串结果。
     */
    private function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    /**
     * 组装包含公共结构和样式的完整页面。
     *
     * @param string $title 页面标题。
     * @param string $body HTTP 请求体原文。
     * @return string 返回shell字符串结果。
     */
    private function shell(string $title, string $body): string { return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->e($title) . ' · UniAPI</title><style>:root{color-scheme:dark;--bg:#071012;--panel:#102023;--line:#284044;--accent:#45e0c3;--text:#eef7f5;--muted:#9fb4b1}*{box-sizing:border-box}body{margin:0;background:linear-gradient(145deg,#071012,#0c191c);color:var(--text);font:16px system-ui;min-height:100vh}header,main{width:min(1120px,calc(100% - 40px));margin:auto}header{min-height:72px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--line)}h1{font-size:clamp(40px,7vw,72px);margin:.2em 0}.eyebrow{color:var(--accent);font:12px monospace;letter-spacing:.14em}main>.eyebrow{margin-top:52px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:30px 0}article,main>form,main>section{padding:24px;border:1px solid var(--line);background:var(--panel)}form{display:grid;gap:14px;margin:20px 0}label{display:grid;gap:6px;color:var(--muted)}input,textarea,button,a{font:inherit}input,textarea{padding:11px;background:var(--bg);border:1px solid var(--line);color:var(--text)}textarea{min-height:100px}button,a{color:var(--accent)}button{min-height:44px;border:1px solid var(--accent);background:transparent;font-weight:700}.empty{color:var(--muted)}pre{white-space:pre-wrap;overflow-wrap:anywhere}@media(max-width:640px){header{flex-wrap:wrap}}</style></head><body>' . $body . '</body></html>'; }
}
