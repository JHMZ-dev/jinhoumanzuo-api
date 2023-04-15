<?php

declare(strict_types=1);

namespace App\Job;

use App\Controller\async\All_async;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;
class Async extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }
    public function handle()
    {
        $info = $this->info;
        $cms = make(All_async::class);
        $go = new Parallel();
        $go->add(function () use ($cms, $info)
        {
            $cms->execdo($info);
        });
        $go->wait();
    }
}
