<?php
declare(strict_types=1);namespace App\Service;use Hyperf\Redis\Redis;
/** 校验网关请求签名、时间窗口与一次性随机数，阻止请求重放。 */
final class GatewaySignatureService{/** 初始化当前组件所需的依赖。 */
public function __construct(private Redis$redis,private int$window=300){}/** 校验输入数据是否满足安全规则。 */
public function verify(int$subscriptionId,string$secret,string$method,string$path,int$timestamp,string$nonce,string$body,string$signature):bool{if($subscriptionId<=0||$nonce===''||abs(time()-$timestamp)>$this->window)return false;$canonical=strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256',$body);if(!hash_equals(hash_hmac('sha256',$canonical,$secret),strtolower($signature)))return false;$key='uniapi:{sub:'.$subscriptionId.'}:nonce:'.hash('sha256',$nonce);return$this->redis->set($key,'1',['nx'=>true,'ex'=>$this->window])===true;}}
