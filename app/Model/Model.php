<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Model;

use Hyperf\DbConnection\Model\Model as BaseModel;

/** 项目模型基类：主键统一使用外部生成的整型雪花 ID。 */
abstract class Model extends BaseModel
{
    public bool $incrementing = false;

    protected string $keyType = 'int';
}
