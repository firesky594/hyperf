<?php
declare(strict_types=1);namespace App\Service;use Hyperf\Redis\Redis;
/** 使用 Redis Lua 原子执行订阅 QPS 与周期总量双重限流。 */
final class QuotaLimiter{private const LUA=<<<'LUA'
local qps=tonumber(redis.call('GET',KEYS[1]) or '0'); local used=tonumber(redis.call('GET',KEYS[2]) or '0')
if qps>=tonumber(ARGV[1]) or used>=tonumber(ARGV[2]) then return {0,qps,used} end
qps=redis.call('INCR',KEYS[1]); if qps==1 then redis.call('EXPIRE',KEYS[1],1) end
used=redis.call('INCR',KEYS[2]); if used==1 then redis.call('EXPIRE',KEYS[2],ARGV[3]) end
return {1,qps,used}
LUA;/**
 * 初始化当前组件所需的依赖。
 *
 * @param Redis $redis Redis 客户端实例。
 * @return void 无返回值。
 */
public function __construct(private Redis$redis){} /**
 * 处理consume。
 *
 * @param int $id 标识数值。
 * @param int $qps 每秒允许的最大请求数。
 * @param int $period 账单或用量统计周期。
 * @param int $seconds seconds数值。
 * @return array{allowed:bool,qps:int,used:int} 返回consume结构化数据。
 * @throws \RuntimeException 运行环境或业务状态不满足要求时抛出。
 */
public function consume(int$id,int$qps,int$period,int$seconds):array{$tag='{sub:'.$id.'}';$r=$this->redis->eval(self::LUA,['uniapi:'.$tag.':qps','uniapi:'.$tag.':period',(string)$qps,(string)$period,(string)$seconds],2);if(!is_array($r)||count($r)<3)throw new \RuntimeException('Quota backend unavailable.');return['allowed'=>(int)$r[0]===1,'qps'=>(int)$r[1],'used'=>(int)$r[2]];}}
