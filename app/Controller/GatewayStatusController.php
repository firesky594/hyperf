<?php
declare(strict_types=1);namespace App\Controller;use App\Http\UserPortalResponseFactory;use App\Service\AppRoleService;use App\View\GatewayPageRenderer;use Hyperf\DbConnection\Db;use Psr\Http\Message\ResponseInterface;
/** 展示网关路由、调用事件、用量聚合和当前节点角色。 */
final class GatewayStatusController extends AbstractController{/**
 * 初始化当前组件所需的依赖。
 *
 * @param GatewayPageRenderer $pages 注入的 GatewayPageRenderer 依赖。
 * @param AppRoleService $roles 注入的 AppRoleService 依赖。
 * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
 * @param Db $db 数据库访问入口。
 * @return void 无返回值。
 */
public function __construct(private GatewayPageRenderer$pages,private AppRoleService$roles,private UserPortalResponseFactory$responses,private Db$db){}/**
 * 查询并展示网关路由。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function routes():ResponseInterface{$s=$this->session();$r=$this->rows($this->db->select('SELECT e.`method`,e.`path`,e.`name`,v.`version` FROM `api_endpoints` e INNER JOIN `api_versions` v ON v.`id`=e.`api_version_id` AND v.`status`=\'published\' WHERE e.`deleted_at` IS NULL ORDER BY e.`id` DESC LIMIT 100'));return$this->responses->html($this->pages->routes($s,$r));}/**
 * 查询并展示网关调用记录。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function calls():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->calls($s,$this->rows($this->db->select('SELECT `event_id`,`subscription_id`,`status_code`,`duration_ms`,`occurred_at` FROM `gateway_call_events` WHERE `deleted_at` IS NULL ORDER BY `occurred_at` DESC LIMIT 100'))));}/**
 * 查询并展示周期用量统计。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function usage():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->usage($s,$this->rows($this->db->select('SELECT `subscription_id`,`period_id`,`call_count` FROM `usage_aggregates` WHERE `deleted_at` IS NULL ORDER BY `period_id` DESC LIMIT 100'))));}/**
 * 展示当前节点角色状态。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function nodes():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->nodes($s,['role'=>$this->roles->current()]));}/**
 * 读取并校验当前请求的会话数据。
 *
 * @return array<string,mixed> 返回会话结构化数据。
 */
private function session():array{$s=$this->request->getAttribute('user_session');return is_array($s)?$s:[];}/**
 * 把数据库查询结果统一转换为数组列表。
 *
 * @param array $r 待渲染或转换的数据列表。
 * @return list<array<string,mixed>> 返回结果列表结构化数据。
 */
private function rows(array$r):array{return array_map(static fn(object|array$v):array=>is_object($v)?get_object_vars($v):$v,$r);}}
