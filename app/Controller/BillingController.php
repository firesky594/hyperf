<?php
declare(strict_types=1);namespace App\Controller;
use App\Exception\AuthException;use App\Http\UserPortalResponseFactory;use App\Service\BillingService;use App\Service\UserIdentityService;use App\View\BillingPageRenderer;use Psr\Http\Message\ResponseInterface;use Psr\Http\Message\UploadedFileInterface;use Throwable;
/** 提供采购方账单与付款凭证入口，以及供应商结算查询。 */
final class BillingController extends AbstractController
{
 /**
  * 初始化当前组件所需的依赖。
  *
  * @param BillingService $billing 注入的 BillingService 依赖。
  * @param UserIdentityService $identities 注入的 UserIdentityService 依赖。
  * @param BillingPageRenderer $pages 注入的 BillingPageRenderer 依赖。
  * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
  * @return void 无返回值。
  */
 public function __construct(private BillingService$billing,private UserIdentityService$identities,private BillingPageRenderer$pages,private UserPortalResponseFactory$responses){}
 /**
  * 处理账单列表。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function invoices():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->buyerInvoices($s,$this->billing->buyerInvoices($this->buyer($s)),$this->csrf($s)));}
 /**
  * 处理付款凭证页面。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function proofPage():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->paymentProof($s,$this->id('invoice_id',true),$this->csrf($s)));}
 /**
  * 处理付款凭证。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  * @throws Throwable 底层处理失败并重新抛出原异常。
  */
 public function proof():ResponseInterface{return$this->write(function(array$s):void{$path=$this->storeProof();try{$this->billing->submitProof($this->buyer($s),$this->id('invoice_id'),$this->str('reference_no'),$path);}catch(Throwable$e){@unlink(BASE_PATH.'/'.$path);throw$e;}});}
 /**
  * 设置tlements。
  *
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 public function settlements():ResponseInterface{$s=$this->session();return$this->responses->html($this->pages->supplierSettlements($s,$this->billing->supplierSettlements($this->supplier($s))));}
 /**
  * 统一执行带校验和异常处理的写操作。
  *
  * @param callable $f 需要统一执行的业务回调。
  * @return ResponseInterface 当前请求对应的 HTTP 响应。
  */
 private function write(callable$f):ResponseInterface{$s=$this->session();$v=$this->request->input('_csrf','');if(!is_string($v)||$this->csrf($s)===''||!hash_equals($this->csrf($s),$v))return$this->responses->html('请求验证失败。',419);try{$f($s);return$this->responses->redirect('/workspace/buyer/billing');}catch(AuthException$e){return$this->responses->html($e->publicMessage(),$e->status());}}
 /**
  * 保存付款凭证。
  *
  * @return string 返回store付款凭证字符串结果。
  * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
  */
 private function storeProof():string{$file=$this->request->file('proof');if(!$file instanceof UploadedFileInterface||$file->getError()!==UPLOAD_ERR_OK||$file->getSize()===null||$file->getSize()<1||$file->getSize()>5*1024*1024)throw AuthException::badRequest('付款凭证文件无效。');$stream=$file->getStream();$head=$stream->read(8);$stream->rewind();$extension=str_starts_with($head,'%PDF-')?'pdf':(str_starts_with($head,"\xFF\xD8\xFF")?'jpg':($head==="\x89PNG\r\n\x1a\n"?'png':''));if($extension==='')throw AuthException::badRequest('付款凭证类型无效。');$relative='runtime/payment-proofs/'.bin2hex(random_bytes(24)).'.'.$extension;$target=BASE_PATH.'/'.$relative;$directory=dirname($target);if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw AuthException::serviceUnavailable('付款凭证存储不可用。');$file->moveTo($target);return$relative;}
 /**
  * 读取并校验当前请求的会话数据。
  *
  * @return array 返回会话结构化数据。
  * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
  */
 private function session():array{$s=$this->request->getAttribute('user_session');if(!is_array($s))throw AuthException::invalidCredentials();return$s;}/**
 * 读取当前会话的 CSRF 令牌。
 *
 * @param array $s 当前登录会话数据。
 * @return string 返回CSRF字符串结果。
 */
private function csrf(array$s):string{$v=$s['csrf_token']??'';return is_string($v)?$v:'';}/**
 * 读取并校验正整数标识。
 *
 * @param string $k 待读取的请求字段名。
 * @param bool $q 控制q行为的布尔标记。
 * @return int 返回标识整数结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function id(string$k,bool$q=false):int{$v=$q?$this->request->query($k):$this->request->input($k);if((is_int($v)&&$v>0)||(is_string($v)&&preg_match('/^[1-9][0-9]*$/D',$v)))return(int)$v;throw AuthException::badRequest('标识无效。');}/**
 * 读取并校验字符串请求参数。
 *
 * @param string $k 待读取的请求字段名。
 * @return string 返回str字符串结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function str(string$k):string{$v=$this->request->input($k,'');if(!is_string($v))throw AuthException::badRequest('字段无效。');return$v;}/**
 * 处理采购方。
 *
 * @param array $s 当前登录会话数据。
 * @return int 返回采购方整数结果。
 */
private function buyer(array$s):int{$w=$this->identities->workspace((int)$s['user_id']);return(int)($w['buyer']['id']??0);}/**
 * 处理供应商。
 *
 * @param array $s 当前登录会话数据。
 * @return int 返回供应商整数结果。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
private function supplier(array$s):int{$w=$this->identities->workspace((int)$s['user_id']);if($w['supplier']===null)throw AuthException::badRequest('供应商身份不存在。');return(int)$w['supplier']['id'];}
}
