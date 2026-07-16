<?php
declare(strict_types=1);namespace App\View;
/** 渲染网关路由、调用日志、用量统计和节点角色状态页面。 */
final class GatewayPageRenderer{/** 查询并展示网关路由。 */
public function routes(array$s,array$r):string{return$this->page('网关路由',$r,'当前没有网关路由。');}/** 查询并展示网关调用记录。 */
public function calls(array$s,array$r):string{return$this->page('调用日志',$r,'当前没有调用日志。');}/** 查询并展示周期用量统计。 */
public function usage(array$s,array$r):string{return$this->page('用量统计',$r,'当前没有用量数据。');}/** 展示当前节点角色状态。 */
public function nodes(array$s,array$n):string{return$this->shell('节点角色状态','<h1>节点角色状态</h1><code>'.htmlspecialchars((string)($n['role']??'unknown'),ENT_QUOTES).'</code>');}/** 渲染当前功能页面。 */
private function page(string$t,array$r,string$e):string{return$this->shell($t,'<h1>'.$t.'</h1>'.($r===[]?'<p>'.$e.'</p>':'<p>已接入实时数据。</p>'));}/** 组装包含公共结构和样式的完整页面。 */
private function shell(string$t,string$b):string{return'<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$t.' · UniAPI</title><style>body{background:#061012;color:#eaf6f3;padding:6vw;font:16px system-ui}main{max-width:1000px;margin:auto}h1{font-size:clamp(40px,8vw,72px)}code{color:#45e0c3}</style></head><body><main>'.$b.'</main></body></html>';}}
