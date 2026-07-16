<?php
declare(strict_types=1);namespace App\Controller;use App\Exception\AuthException;use App\Http\AgentAdminResponseFactory;use App\Service\BillingService;use App\View\BillingPageRenderer;use Psr\Http\Message\ResponseInterface;
/** 处理后台付款确认、佣金配置和供应商结算确认。 */
final class BillingAdminController extends AbstractController{/** 初始化当前组件所需的依赖。 */
public function __construct(private BillingService$billing,private BillingPageRenderer$pages,private AgentAdminResponseFactory$responses){}/** 处理当前模块的默认入口请求。 */
public function index():ResponseInterface{return$this->responses->html($this->pages->adminConfirmations($this->billing->pendingProofs(),$this->csrf($this->session())));}/** 确认 `confirmPayment` 方法对应的数据或业务状态。 */
public function confirmPayment():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->confirmPayment($a,$this->id('proof_id')));}/** 执行 `commission` 方法对应的业务处理。 */
public function commission():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->setCommission($a,$this->id('supplier_profile_id'),$this->id('commission_bps',true)));}/** 设置 `settlement` 方法对应的数据或业务状态。 */
public function settlement():ResponseInterface{return$this->write(fn(int$a)=>$this->billing->confirmSettlement($a,$this->id('settlement_id')));}/** 统一执行带校验和异常处理的写操作。 */
private function write(callable$f):ResponseInterface{$s=$this->session();$v=$this->request->input('_csrf','');if(!is_string($v)||$this->csrf($s)===''||!hash_equals($this->csrf($s),$v))return$this->responses->html($this->pages->adminConfirmations([],$this->csrf($s)),419);try{$f((int)$s['admin_id']);return$this->responses->redirect('/agent_admin/billing',303);}catch(AuthException$e){return$this->responses->html($this->pages->adminConfirmations([],$this->csrf($s)),$e->status());}}/** 读取并校验当前请求的会话数据。 */
private function session():array{$s=$this->request->getAttribute('admin_session');return is_array($s)?$s:[];}/** 读取当前会话的 CSRF 令牌。 */
private function csrf(array$s):string{$v=$s['csrf_token']??'';return is_string($v)?$v:'';}/** 读取并校验正整数标识。 */
private function id(string$k,bool$zero=false):int{$v=$this->request->input($k);if((is_int($v)&&($v>0||$zero&&$v===0))||(is_string($v)&&preg_match($zero?'/^[0-9]+$/D':'/^[1-9][0-9]*$/D',$v)))return(int)$v;throw AuthException::badRequest('标识无效。');}}
