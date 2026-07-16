<?php
declare(strict_types=1);
namespace App\Service;
use Hyperf\Contract\IdGeneratorInterface;use Hyperf\Database\ConnectionInterface;use Hyperf\DbConnection\Db;use Hyperf\Redis\Redis;
use Throwable;
/** 幂等记录网关调用事件，并按账期累计订阅用量。 */
final class MeteringService
{
 /**
  * 初始化当前组件所需的依赖。
  *
  * @param Db $db 数据库访问入口。
  * @param Redis $redis Redis 客户端实例。
  * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
  * @return void 无返回值。
  */
 public function __construct(private Db$db,private Redis$redis,private IdGeneratorInterface$ids){}
 /**
  * 处理emit。
  *
  * @param array $e 数据为空时显示的提示文本。
  * @return string 返回emit字符串结果。
  */
 public function emit(array$e):string{return(string)$this->redis->xAdd('uniapi:{metering}:calls','*',array_map('strval',$e));}
 /**
  * 处理persist。
  *
  * @param array $e 数据为空时显示的提示文本。
  * @return void 无返回值。
  */
 public function persist(array$e):void{$this->db->insert('INSERT INTO `gateway_call_events` (`id`,`event_id`,`subscription_id`,`status_code`,`duration_ms`,`occurred_at`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE `event_id`=`event_id`',[$this->ids->generate(),$e['event_id'],$e['subscription_id'],$e['status_code'],$e['duration_ms']]);}
 /**
  * 处理rebuild用量。
  *
  * @param string $periodId 账期唯一标识。
  * @param string $from from字符串。
  * @param string $to to字符串。
  * @return void 无返回值。
  */
 public function rebuildUsage(string$periodId,string$from,string$to):void{$this->db->transaction(function(ConnectionInterface$c)use($periodId,$from,$to):void{$c->delete('DELETE FROM `usage_aggregates` WHERE `period_id`=?',[$periodId]);$c->insert('INSERT INTO `usage_aggregates` (`id`,`subscription_id`,`period_id`,`call_count`,`created_at`,`updated_at`,`deleted_at`) SELECT MIN(`id`),`subscription_id`,?,COUNT(*),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL FROM `gateway_call_events` WHERE `occurred_at`>=? AND `occurred_at`<? AND `deleted_at` IS NULL GROUP BY `subscription_id`',[$periodId,$from,$to]);});}
 /**
  * 处理ensureConsumerGroup。
  *
  * @param string $group group字符串。
  * @return void 无返回值。
  * @throws Throwable 底层处理失败并重新抛出原异常。
  */
 public function ensureConsumerGroup(string$group):void{try{$this->redis->xGroup('CREATE','uniapi:{metering}:calls',$group,'0',true);}catch(Throwable$e){if(!str_contains($e->getMessage(),'BUSYGROUP'))throw$e;}}
 /**
  * 处理claimStale。
  *
  * @param string $group group字符串。
  * @param string $consumer consumer字符串。
  * @param int $idleMs idleMs数值。
  * @return mixed 返回claimStale处理结果。
  */
 public function claimStale(string$group,string$consumer,int$idleMs):mixed{return$this->redis->xAutoClaim('uniapi:{metering}:calls',$group,$consumer,$idleMs,'0-0',['COUNT',100]);}
}
