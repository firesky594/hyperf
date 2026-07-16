<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\View\CatalogPageRenderer;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class CatalogPageRendererTest extends TestCase
{
    private array $session = ['user_id' => 7, 'username' => 'supplier_7'];

    public function testSupplierListShowsCreateFormAndHonestEmptyState(): void
    {
        $html = (new CatalogPageRenderer())->supplierProducts($this->session, [], 'csrf');
        self::assertStringContainsString('供应商 API 管理', $html);
        self::assertStringContainsString('action="/workspace/supplier/apis/create"', $html);
        self::assertStringContainsString('当前还没有 API 商品', $html);
        self::assertSame(1, substr_count($html, '<h1'));
    }

    public function testDraftEditorContainsVersionDocumentationPriceAndEndpointFields(): void
    {
        $html = (new CatalogPageRenderer())->supplierEditor($this->session, ['product_id' => 10, 'version_id' => 11, 'name' => '天气 API', 'slug' => 'weather', 'summary' => '天气查询', 'version' => 'v1', 'documentation' => '# 文档', 'unit_price_micros' => 250000, 'currency' => 'CNY', 'endpoints' => []], 'csrf');
        foreach (['版本标识', '接口文档', '每次调用价格', '端点定义', '/workspace/supplier/apis/save'] as $text) { self::assertStringContainsString($text, $html); }
        self::assertStringContainsString('name="_csrf" value="csrf"', $html);
    }

    public function testMarketAndDetailShowOnlyRealPublishedDataOrEmptyState(): void
    {
        $renderer = new CatalogPageRenderer();
        $empty = $renderer->market($this->session, []);
        self::assertStringContainsString('API 市场', $empty); self::assertStringContainsString('当前没有已发布 API', $empty);
        $detail = $renderer->marketDetail($this->session, ['name' => '天气 API', 'version' => 'v1', 'summary' => '天气查询', 'documentation' => '# 使用说明', 'unit_price_micros' => 250000, 'currency' => 'CNY', 'endpoints' => [['method' => 'GET', 'path' => '/weather', 'name' => '查询天气']]]);
        foreach (['天气 API', 'v1', 'GET', '/weather', '0.250000 CNY'] as $text) { self::assertStringContainsString($text, $detail); }
        self::assertSame(1, substr_count($detail, '<h1'));
    }
}
