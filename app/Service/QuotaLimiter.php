<?php
declare(strict_types=1);namespace App\Service;use Hyperf\Redis\Redis;
/** 使用 Redis Lua 原子执行订阅 QPS 与周期总量双重限流。 */
final class QuotaLimiter{private const LUA=<<<'LUA'
local qps=tonumber(redis.call('GET',KEYS[1]) or '0'); local used=tonumber(redis.call('GET',KEYS[2]) or '0')
if qps>=tonumber(ARGV[1]) or used>=tonumber(ARGV[2]) then return {0,qps,used} end
qps=redis.call('INCR',KEYS[1]); if qps==1 then redis.call('EXPIRE',KEYS[1],1) end
used=redis.call('INCR',KEYS[2]); if used==1 then redis.call('EXPIRE',KEYS[2],ARGV[3]) end
return {1,qps,used}
LUA;public function __construct(private Redis$redis){} /** @return array{allowed:bool,qps:int,used:int} */public function consume(int$id,int$qps,int$period,int$seconds):array{$tag='{sub:'.$id.'}';$r=$this->redis->eval(self::LUA,['uniapi:'.$tag.':qps','uniapi:'.$tag.':period',(string)$qps,(string)$period,(string)$seconds],2);if(!is_array($r)||count($r)<3)throw new \RuntimeException('Quota backend unavailable.');return['allowed'=>(int)$r[0]===1,'qps'=>(int)$r[1],'used'=>(int)$r[2]];}}
