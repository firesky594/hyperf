<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\CatalogService;
use App\Service\UserIdentityService;
use App\View\CatalogPageRenderer;
use Psr\Http\Message\ResponseInterface;

final class CatalogController extends AbstractController
{
    public function __construct(private CatalogService $catalog, private UserIdentityService $identities, private CatalogPageRenderer $pages, private UserPortalResponseFactory $responses) {}

    public function supplierProducts(): ResponseInterface { $session=$this->session(); return $this->responses->html($this->pages->supplierProducts($session,$this->catalog->supplierProducts($this->supplierId($session)),$this->csrfToken($session))); }
    public function supplierEditor(): ResponseInterface
    {
        $session=$this->session(); $productId=$this->positiveInput('product_id',true); $versionId=$this->positiveInput('version_id',true);
        $draft=$this->catalog->supplierDraft($this->supplierId($session),$productId,$versionId);
        return $draft === null ? $this->responses->html('API 草稿不存在。',404) : $this->responses->html($this->pages->supplierEditor($session,$draft,$this->csrfToken($session)));
    }
    public function create(): ResponseInterface { return $this->write(function (array $session): string { $result=$this->catalog->createProduct($this->supplierId($session),$this->stringInput('name'),$this->stringInput('slug'),$this->stringInput('summary')); return '/workspace/supplier/apis/edit?product_id='.$result['product_id'].'&version_id='.$result['version_id']; }); }
    public function save(): ResponseInterface { return $this->write(function (array $session): string { $productId=$this->positiveInput('product_id');$versionId=$this->positiveInput('version_id');$this->catalog->saveDraft($this->supplierId($session),$productId,$versionId,$this->stringInput('name'),$this->stringInput('summary'),$this->stringInput('version'),$this->stringInput('documentation'),$this->priceMicros($this->stringInput('unit_price')),'CNY',$this->endpoints($this->stringInput('endpoints')));return '/workspace/supplier/apis/edit?product_id='.$productId.'&version_id='.$versionId; }); }
    public function publish(): ResponseInterface { return $this->write(function (array $session): string { $this->catalog->publish($this->supplierId($session),$this->positiveInput('version_id'));return '/workspace/supplier/apis'; }); }
    public function unlist(): ResponseInterface { return $this->write(function (array $session): string { $this->catalog->unlist($this->supplierId($session),$this->positiveInput('product_id'));return '/workspace/supplier/apis'; }); }
    public function nextVersion(): ResponseInterface { return $this->write(function (array $session): string { $productId=$this->positiveInput('product_id');$versionId=$this->catalog->createNextVersion($this->supplierId($session),$productId,$this->stringInput('version'));return '/workspace/supplier/apis/edit?product_id='.$productId.'&version_id='.$versionId; }); }
    public function market(): ResponseInterface { $session=$this->session();return $this->responses->html($this->pages->market($session,$this->catalog->market())); }
    public function detail(): ResponseInterface { $session=$this->session();$product=$this->catalog->marketDetail($this->positiveInput('product_id',true));return $product===null?$this->responses->html('API 商品不存在或已下架。',404):$this->responses->html($this->pages->marketDetail($session,$product)); }

    /** @param callable(array<string,mixed>):string $operation */
    private function write(callable $operation): ResponseInterface { $session=$this->session();if(!$this->validCsrf($session)){return $this->responses->html('请求验证失败。',419);}try{return $this->responses->redirect($operation($session));}catch(AuthException $e){return $this->responses->html($e->publicMessage(),$e->status());} }
    /** @return array<string,mixed> */
    private function session(): array { $session=$this->request->getAttribute('user_session');if(!is_array($session)){throw AuthException::invalidCredentials();}return $session; }
    /** @param array<string,mixed> $session */
    private function supplierId(array $session): int { $workspace=$this->identities->workspace((int)($session['user_id']??0));$supplier=$workspace['supplier'];if($supplier===null){throw AuthException::badRequest('请先申请供应商身份。');}return (int)$supplier['id']; }
    /** @param array<string,mixed> $session */
    private function csrfToken(array $session): string { $token=$session['csrf_token']??'';return is_string($token)?$token:''; }
    /** @param array<string,mixed> $session */
    private function validCsrf(array $session): bool { $expected=$session['csrf_token']??'';$actual=$this->request->input('_csrf','');return is_string($expected)&&is_string($actual)&&$expected!==''&&hash_equals($expected,$actual); }
    private function stringInput(string $key): string { $value=$this->request->input($key,'');if(!is_string($value)){throw AuthException::badRequest('请求字段格式错误。');}return $value; }
    private function positiveInput(string $key,bool $query=false): int { $value=$query?$this->request->query($key):$this->request->input($key);if((is_int($value)&&$value>0)||(is_string($value)&&preg_match('/^[1-9][0-9]*$/D',$value)===1)){return (int)$value;}throw AuthException::badRequest('请求标识无效。'); }
    private function priceMicros(string $value): int { $value=trim($value);if(preg_match('/^(\d+)(?:\.(\d{1,6}))?$/D',$value,$m)!==1){throw AuthException::badRequest('价格格式无效。');}$whole=(int)$m[1];if($whole>9_223_372_036_853){throw AuthException::badRequest('价格超出范围。');}$fraction=str_pad($m[2]??'',6,'0');return $whole*1_000_000+(int)$fraction; }
    /** @return list<array{method:string,path:string,name:string,description:string}> */
    private function endpoints(string $value): array { $result=[];foreach(preg_split('/\R/',trim($value))?:[] as $line){if(trim($line)===''){continue;}$parts=explode('|',$line,4);if(count($parts)<3){throw AuthException::badRequest('端点定义格式无效。');}$result[]=['method'=>trim($parts[0]),'path'=>trim($parts[1]),'name'=>trim($parts[2]),'description'=>trim($parts[3]??'')];}return $result; }
}
