<?php
declare(strict_types=1);namespace App\Service;use RuntimeException;use function Hyperf\Support\env;
/** 读取并校验当前进程角色，控制各类节点能力是否可用。 */
final class AppRoleService{private const ROLES=['control-plane','gateway','metering','billing','notification','all-in-one'];private string$role;/** 初始化当前组件所需的依赖。 */
public function __construct(?string$role=null){$this->role=$role??(string)env('APP_ROLE','all-in-one');if(!in_array($this->role,self::ROLES,true))throw new RuntimeException('Invalid APP_ROLE.');}/** 获取当前应用节点角色。 */
public function current():string{return$this->role;}/** 判断当前节点角色是否允许指定能力。 */
public function allows(string$capability):bool{return$this->role==='all-in-one'||$this->role===$capability;}/** 断言当前节点具备指定能力。 */
public function require(string$capability):void{if(!$this->allows($capability))throw new RuntimeException('Role capability denied.');}}
