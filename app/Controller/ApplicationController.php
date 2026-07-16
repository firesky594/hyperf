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
 public function __construct(private ApplicationService$apps,private UserIdentityService$identities,private ApplicationPageRenderer$pages,private UserPortalResponseFactory$responses){}
 public function applications():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->applications($s,$this->apps->applications($this->buyerId($s)),$this->csrf($s)));}
 public function subscriptions():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->subscriptions($s,$this->apps->subscriptions($this->buyerId($s)),$this->csrf($s)));}
 public function quotas():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->quotas($s,$this->apps->supplierQuotas($this->supplierId($s)),$this->csrf($s)));}
 public function create():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$r=$this->apps->create($this->buyerId($s),$this->str('name'));return$this->responses->html($this->pages->secret($s,$r['secret'],'/workspace/buyer/apps'));});}
 public function reset():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$r=$this->apps->resetSecret($this->buyerId($s),$this->id('application_id'));return$this->responses->html($this->pages->secret($s,$r['secret'],'/workspace/buyer/apps'));});}
 public function revoke():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->revokeSecret($this->buyerId($s),$this->id('application_id'));return$this->responses->redirect('/workspace/buyer/apps');});}
 public function subscribe():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->subscribe($this->buyerId($s),$this->id('application_id'),$this->id('product_id'));return$this->responses->redirect('/workspace/buyer/subscriptions');});}
 public function quotaUpdate():ResponseInterface{return$this->write(function(array$s):ResponseInterface{$this->apps->updateQuota($this->supplierId($s),$this->id('subscription_id'),$this->id('qps_limit'),$this->id('period_limit'));return$this->responses->redirect('/workspace/supplier/quotas');});}
 /** @param callable(array<string,mixed>):ResponseInterface$f */private function write(callable$f):ResponseInterface{$s=$this->session();$a=$this->request->input('_csrf','');if(!is_string($a)||$this->csrf($s)===''||!hash_equals($this->csrf($s),$a))return$this->responses->html('请求验证失败。',419);try{return$f($s);}catch(AuthException$e){return$this->responses->html($e->publicMessage(),$e->status());}}
 /** @return array<string,mixed> */private function session():array{$s=$this->request->getAttribute('user_session');if(!is_array($s))throw AuthException::invalidCredentials();return$s;}private function csrf(array$s):string{$v=$s['csrf_token']??'';return is_string($v)?$v:'';}private function str(string$k):string{$v=$this->request->input($k,'');if(!is_string($v))throw AuthException::badRequest('字段格式错误。');return$v;}private function id(string$k):int{$v=$this->request->input($k);if((is_int($v)&&$v>0)||(is_string($v)&&preg_match('/^[1-9][0-9]*$/D',$v)===1))return(int)$v;throw AuthException::badRequest('标识无效。');}
 private function buyerId(array$s):int{$w=$this->identities->workspace((int)($s['user_id']??0));if($w['buyer']===null)throw AuthException::badRequest('采购身份不存在。');return(int)$w['buyer']['id'];}private function supplierId(array$s):int{$w=$this->identities->workspace((int)($s['user_id']??0));if($w['supplier']===null)throw AuthException::badRequest('供应商身份不存在。');return(int)$w['supplier']['id'];}
}
