<?php
declare(strict_types=1);namespace App\Controller;use App\Exception\AuthException;use App\Http\AgentAdminResponseFactory;use App\Service\BillingService;use App\View\BillingPageRenderer;use Psr\Http\Message\ResponseInterface;
/** 处理后台付款确认、佣金配置和供应商结算确认。 */
final class BillingAdminController extends AbstractController{/**
 * 初始化当前组件所需的依赖。
 *
 * @param BillingService $billing 注入的 BillingService 依赖。
 * @param BillingPageRenderer $pages 注入的 BillingPageRenderer 依赖。
 * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
 * @return void 无返回值。
 */
public function __construct(private BillingService$billing,private BillingPageRenderer$pages,private AgentAdminResponseFactory$responses){}/**
 * 处理当前模块的默认入口请求。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function index():ResponseInterface{return$this->responses->html($this->pages->adminConfirmations($this->billing->pendingProofs(),$this->csrf($this->session())));}/**
 * 确认付款。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function confirmPayment():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->confirmPayment($a,$this->id('proof_id')));}/**
 * 处理佣金。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function commission():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->setCommission($a,$this->id('supplier_profile_id'),$this->id('commission_bps',true)));}/**
 * 设置tlement。
 *
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
public function settlement():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->confirmSettlement($a,$this->id('settlement_id')));}/**
 * 统一执行带校验和异常处理的写操作。
 *
 * @param callable $f 需要统一执行的业务回调。
 * @return ResponseInterface 当前请求对应的 HTTP 响应。
 */
private function write(callable$f):ResponseInterface{$s=$this->session();$v=$this->request->input('_csrf','');if(!is_string($v)||$this->csrf($s)===''||!hash_equals($this->csrf($s),$v))return$this->responses->html($this->pages->adminConfirmations([],$this->csrf($s)),419);try{$f((int)$s['admin_id']);return$this->responses->redirect('/agent_admin/billing',303);}catch(AuthException$e){return$this->responses->html($this->pages->adminConfirmations([],$this->csrf($s)),$e->status());}}/**
 * 读取并校验当前请求的会话数据。
 *
 * @return array 返回会话结构化数据。
 */
private function session():array{$s=$this->request->getAttribute('admin_session');return is_array($s)?$s:[];}/**
 * 读取当前会话的 CSRF 令牌。
 *
 * @param array $s 当前登录会话数据。
 * @return string 返回CSRF字符串结果。
 */
private function csrf(array$s):string{$v=$s['csrf_token']??'';return is_string($v)?$v:'';}/**
 * 读取并校验正整数标识。
 *
 * @param string $k 待读取的请求字段名。
 * @param bool $zero 是否允许标识值为零。
 * @return int 返回标识整数结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function id(string$k,bool$zero=false):int{$v=$this->request->input($k);if((is_int($v)&&($v>0||$zero&&$v===0))||(is_string($v)&&preg_match($zero?'/^[0-9]+$/D':'/^[1-9][0-9]*$/D',$v)))return(int)$v;throw AuthException::badRequest('标识无效。');}}
