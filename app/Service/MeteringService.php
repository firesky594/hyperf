<?php
declare(strict_types=1);
namespace App\Service;
use Hyperf\Contract\IdGeneratorInterface;use Hyperf\Database\ConnectionInterface;use Hyperf\DbConnection\Db;use Hyperf\Redis\Redis;
use Throwable;
/** 幂等记录网关调用事件，并按账期累计订阅用量。 */
final class MeteringService
{
 /** 初始化当前组件所需的依赖。 */
 public function __construct(private Db$db,private Redis$redis,private IdGeneratorInterface$ids){}
 /** 执行 `emit` 方法对应的业务处理。 @param array{event_id:string,subscription_id:int,status_code:int,duration_ms:int}$e */
 public function emit(array$e):string{return(string)$this->redis->xAdd('uniapi:{metering}:calls','*',array_map('strval',$e));}
 /** 执行 `persist` 方法对应的业务处理。 @param array{event_id:string,subscription_id:int,status_code:int,duration_ms:int}$e */
 public function persist(array$e):void{$this->db->insert('INSERT INTO `gateway_call_events` (`id`,`event_id`,`subscription_id`,`status_code`,`duration_ms`,`occurred_at`,`created_at`,`updated_at`,`deleted_at`) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE `event_id`=`event_id`',[$this->ids->generate(),$e['event_id'],$e['subscription_id'],$e['status_code'],$e['duration_ms']]);}
 /** 执行 `rebuildUsage` 方法对应的业务处理。 */
 public function rebuildUsage(string$periodId,string$from,string$to):void{$this->db->transaction(function(ConnectionInterface$c)use($periodId,$from,$to):void{$c->delete('DELETE FROM `usage_aggregates` WHERE `period_id`=?',[$periodId]);$c->insert('INSERT INTO `usage_aggregates` (`id`,`subscription_id`,`period_id`,`call_count`,`created_at`,`updated_at`,`deleted_at`) SELECT MIN(`id`),`subscription_id`,?,COUNT(*),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,NULL FROM `gateway_call_events` WHERE `occurred_at`>=? AND `occurred_at`<? AND `deleted_at` IS NULL GROUP BY `subscription_id`',[$periodId,$from,$to]);});}
 /** 执行 `ensureConsumerGroup` 方法对应的业务处理。 */
 public function ensureConsumerGroup(string$group):void{try{$this->redis->xGroup('CREATE','uniapi:{metering}:calls',$group,'0',true);}catch(Throwable$e){if(!str_contains($e->getMessage(),'BUSYGROUP'))throw$e;}}
 /** 执行 `claimStale` 方法对应的业务处理。 */
 public function claimStale(string$group,string$consumer,int$idleMs):mixed{return$this->redis->xAutoClaim('uniapi:{metering}:calls',$group,$consumer,$idleMs,'0-0',['COUNT',100]);}
}
