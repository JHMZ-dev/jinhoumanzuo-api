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
 * Class TestQueueAsync
 * @package App\Process
 * @Process(name="TestQueueAsync")
 */
class TestQueueAsync extends ConsumerProcess
{
    protected  $queue = 'test';
}
