<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Process;

use Hyperf\AsyncQueue\Process\ConsumerProcess;
use Hyperf\Process\Annotation\Process;


/**
 * Class AsyncQueueAsync
 * @package App\Process
 * @Process(name="AsyncQueueAsync")
 */
class AsyncQueueAsync extends ConsumerProcess
{
    protected  $queue = 'async';
}
