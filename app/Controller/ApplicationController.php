<?php
declare(strict_types=1);
namespace App\Controller;
use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\ApplicationService;
use App\Service\UserIdentityService;
use App\View\ApplicationPageRenderer;
use Psr\Http\Message\ResponseInterface;
/** 处理采购方应用、密钥、订阅及供应商额度管理。 */
final class ApplicationController extends AbstractController
{
 /**
  * 初始化当前组件所需的依赖。
  *
  * @param ApplicationService $apps 注入的 ApplicationService 依赖。
  * @param UserIdentityService $identities 注入的 UserIdentityService 依赖。
  * @param ApplicationPageRenderer $pages 注入的 ApplicationPageRenderer 依赖。
  * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
  * @return void 无返回值。
  */
 public function __construct(private ApplicationService$apps,private UserIdentityService$identities,private ApplicationPageRenderer$pages,private UserPortalResponseFactory$responses){}
 /**
  * 查询采购方的应用列表。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function applications():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->applications($s,$this->apps->applications($this->buyerId($s)),$this->csrf($s)));}
 /**
  * 查询采购方的 API 订阅列表。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function subscriptions():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->subscriptions($s,$this->apps->subscriptions($this->buyerId($s)),$this->csrf($s)));}
 /**
  * 查询供应商可管理的订阅额度。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function quotas():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->quotas($s,$this->apps->supplierQuotas($this->supplierId($s)),$this->csrf($s)));}
 /**
  * 校验输入并创建业务记录。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function create():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$r=$this->apps->create($this->buyerId($s),$this->str('name'));return$this->responses->html($this->pages->secret($s,$r['secret'],'/workspace/buyer/apps'));});}
 /**
  * 处理reset。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function reset():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$r=$this->apps->resetSecret($this->buyerId($s),$this->id('application_id'));return$this->responses->html($this->pages->secret($s,$r['secret'],'/workspace/buyer/apps'));});}
 /**
  * 处理revoke。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function revoke():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->revokeSecret($this->buyerId($s),$this->id('application_id'));return$this->responses->redirect('/workspace/buyer/apps');});}
 /**
  * 处理subscribe。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function subscribe():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->subscribe($this->buyerId($s),$this->id('application_id'),$this->id('product_id'));return$this->responses->redirect('/workspace/buyer/subscriptions');});}
 /**
  * 处理额度Update。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function quotaUpdate():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->updateQuota($this->supplierId($s),$this->id('subscription_id'),$this->id('qps_limit'),$this->id('period_limit'));return$this->responses->redirect('/workspace/supplier/quotas');});}
 /**
  * 统一执行带校验和异常处理的写操作。
  *
  * @param callable $f 需要统一执行的业务回调。
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
private function write(callable$f):ResponseInterface{$s=$this->session();$a=$this->request->input('_csrf','');if(!is_string($a)||$this->csrf($s)===''||!hash_equals($this->csrf($s),$a))return$this->responses->html('请求验证失败。',419);try{return$f($s);}catch(AuthException$e){return$this->responses->html($e->publicMessage(),$e->status());}}
 /**
  * 读取并校验当前请求的会话数据。
  *
  * @return array<string,mixed> 返回会话结构化数据。
  * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
  */
private function session():array{$s=$this->request->getAttribute('user_session');if(!is_array($s))throw AuthException::invalidCredentials();return$s;}/**
 * 读取当前会话的 CSRF 令牌。
 *
 * @param array $s 当前登录会话数据。
 * @return string 返回CSRF字符串结果。
 */
private function csrf(array$s):string{$v=$s['csrf_token']??'';return is_string($v)?$v:'';}/**
 * 读取并校验字符串请求参数。
 *
 * @param string $k 待读取的请求字段名。
 * @return string 返回str字符串结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function str(string$k):string{$v=$this->request->input($k,'');if(!is_string($v))throw AuthException::badRequest('字段格式错误。');return$v;}/**
 * 读取并校验正整数标识。
 *
 * @param string $k 待读取的请求字段名。
 * @return int 返回标识整数结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function id(string$k):int{$v=$this->request->input($k);if((is_int($v)&&$v>0)||(is_string($v)&&preg_match('/^[1-9][0-9]*$/D',$v)===1))return(int)$v;throw AuthException::badRequest('标识无效。');}
 /**
  * 处理采购方标识。
  *
  * @param array $s 当前登录会话数据。
  * @return int 返回采购方标识整数结果。
  * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
  */
 private function buyerId(array$s):int{$w=$this->identities->workspace((int)($s['user_id']??0));if($w['buyer']===null)throw AuthException::badRequest('采购身份不存在。');return(int)$w['buyer']['id'];}/**
 * 处理供应商标识。
 *
 * @param array $s 当前登录会话数据。
 * @return int 返回供应商标识整数结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function supplierId(array$s):int{$w=$this->identities->workspace((int)($s['user_id']??0));if($w['supplier']===null)throw AuthException::badRequest('供应商身份不存在。');return(int)$w['supplier']['id'];}
}
