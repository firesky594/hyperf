<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\CatalogService;
use App\Service\UserIdentityService;
use App\View\CatalogPageRenderer;
use Psr\Http\Message\ResponseInterface;

/** 处理供应商 API 商品编辑发布及采购方市场浏览。 */
final class CatalogController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param CatalogService $catalog 注入的 CatalogService 依赖。
     * @param UserIdentityService $identities 注入的 UserIdentityService 依赖。
     * @param CatalogPageRenderer $pages 注入的 CatalogPageRenderer 依赖。
     * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(private CatalogService $catalog, private UserIdentityService $identities, private CatalogPageRenderer $pages, private UserPortalResponseFactory $responses) {}

    /**
     * 处理供应商API 商品列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function supplierProducts(): ResponseInterface { $session=$this->session(); return $this->responses->html($this->pages->supplierProducts($session,$this->catalog->supplierProducts($this->supplierId($session)),$this->csrfToken($session))); }
    /**
     * 处理供应商Editor。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function supplierEditor(): ResponseInterface
    {
        $session=$this->session(); $productId=$this->positiveInput('product_id',true); $versionId=$this->positiveInput('version_id',true);
        $draft=$this->catalog->supplierDraft($this->supplierId($session),$productId,$versionId);
        return $draft === null ? $this->responses->html('API 草稿不存在。',404) : $this->responses->html($this->pages->supplierEditor($session,$draft,$this->csrfToken($session)));
    }
    /**
     * 校验输入并创建业务记录。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function create(): ResponseInterface { return $this->write(function (array $session): string { $result=$this->catalog->createProduct($this->supplierId($session),$this->stringInput('name'),$this->stringInput('slug'),$this->stringInput('summary')); return '/workspace/supplier/apis/edit?product_id='.$result['product_id'].'&version_id='.$result['version_id']; }); }
    /**
     * 校验并保存当前业务数据。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function save(): ResponseInterface { return $this->write(function (array $session): string { $productId=$this->positiveInput('product_id');$versionId=$this->positiveInput('version_id');$this->catalog->saveDraft($this->supplierId($session),$productId,$versionId,$this->stringInput('name'),$this->stringInput('summary'),$this->stringInput('version'),$this->stringInput('documentation'),$this->priceMicros($this->stringInput('unit_price')),'CNY',$this->endpoints($this->stringInput('endpoints')));return '/workspace/supplier/apis/edit?product_id='.$productId.'&version_id='.$versionId; }); }
    /**
     * 处理publish。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function publish(): ResponseInterface { return $this->write(function (array $session): string { $this->catalog->publish($this->supplierId($session),$this->positiveInput('version_id'));return '/workspace/supplier/apis'; }); }
    /**
     * 处理unlist。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function unlist(): ResponseInterface { return $this->write(function (array $session): string { $this->catalog->unlist($this->supplierId($session),$this->positiveInput('product_id'));return '/workspace/supplier/apis'; }); }
    /**
     * 处理next版本。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function nextVersion(): ResponseInterface { return $this->write(function (array $session): string { $productId=$this->positiveInput('product_id');$versionId=$this->catalog->createNextVersion($this->supplierId($session),$productId,$this->stringInput('version'));return '/workspace/supplier/apis/edit?product_id='.$productId.'&version_id='.$versionId; }); }
    /**
     * 处理API 市场。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function market(): ResponseInterface { $session=$this->session();return $this->responses->html($this->pages->market($session,$this->catalog->market())); }
    /**
     * 处理detail。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function detail(): ResponseInterface { $session=$this->session();$product=$this->catalog->marketDetail($this->positiveInput('product_id',true));return $product===null?$this->responses->html('API 商品不存在或已下架。',404):$this->responses->html($this->pages->marketDetail($session,$product)); }

    /**
     * 统一执行带校验和异常处理的写操作。
     *
     * @param callable(array<string,mixed>):string $operation 需要统一执行的业务回调。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function write(callable $operation): ResponseInterface { $session=$this->session();if(!$this->validCsrf($session)){return $this->responses->html('请求验证失败。',419);}try{return $this->responses->redirect($operation($session));}catch(AuthException $e){return $this->responses->html($e->publicMessage(),$e->status());} }
    /**
     * 读取并校验当前请求的会话数据。
     *
     * @return array<string,mixed> 返回会话结构化数据。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function session(): array { $session=$this->request->getAttribute('user_session');if(!is_array($session)){throw AuthException::invalidCredentials();}return $session; }
    /**
     * 处理供应商标识。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return int 返回供应商标识整数结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function supplierId(array $session): int { $workspace=$this->identities->workspace((int)($session['user_id']??0));$supplier=$workspace['supplier'];if($supplier===null){throw AuthException::badRequest('请先申请供应商身份。');}return (int)$supplier['id']; }
    /**
     * 处理CSRF令牌。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return string 返回CSRF令牌字符串结果。
     */
    private function csrfToken(array $session): string { $token=$session['csrf_token']??'';return is_string($token)?$token:''; }
    /**
     * 校验CSRF。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function validCsrf(array $session): bool { $expected=$session['csrf_token']??'';$actual=$this->request->input('_csrf','');return is_string($expected)&&is_string($actual)&&$expected!==''&&hash_equals($expected,$actual); }
    /**
     * 处理string输入参数。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return string 返回string输入参数字符串结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function stringInput(string $key): string { $value=$this->request->input($key,'');if(!is_string($value)){throw AuthException::badRequest('请求字段格式错误。');}return $value; }
    /**
     * 处理positive输入参数。
     *
     * @param string $key 缓存、锁或凭据键。
     * @param bool $query 是否从查询字符串读取参数。
     * @return int 返回positive输入参数整数结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function positiveInput(string $key,bool $query=false): int { $value=$query?$this->request->query($key):$this->request->input($key);if((is_int($value)&&$value>0)||(is_string($value)&&preg_match('/^[1-9][0-9]*$/D',$value)===1)){return (int)$value;}throw AuthException::badRequest('请求标识无效。'); }
    /**
     * 处理价格Micros。
     *
     * @param string $value 待写入或校验的值。
     * @return int 返回价格Micros整数结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function priceMicros(string $value): int { $value=trim($value);if(preg_match('/^(\d+)(?:\.(\d{1,6}))?$/D',$value,$m)!==1){throw AuthException::badRequest('价格格式无效。');}$whole=(int)$m[1];if($whole>9_223_372_036_853){throw AuthException::badRequest('价格超出范围。');}$fraction=str_pad($m[2]??'',6,'0');return $whole*1_000_000+(int)$fraction; }
    /**
     * 处理端点列表。
     *
     * @param string $value 待写入或校验的值。
     * @return list<array{method:string,path:string,name:string,description:string}> 返回端点列表结构化数据。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function endpoints(string $value): array { $result=[];foreach(preg_split('/\R/',trim($value))?:[] as $line){if(trim($line)===''){continue;}$parts=explode('|',$line,4);if(count($parts)<3){throw AuthException::badRequest('端点定义格式无效。');}$result[]=['method'=>trim($parts[0]),'path'=>trim($parts[1]),'name'=>trim($parts[2]),'description'=>trim($parts[3]??'')];}return $result; }
}
