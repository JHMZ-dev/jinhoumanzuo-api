<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;
use App\Controller\async\Test as Test22;
class Test extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }

    public function handle()
    {
        $RegUserAsync = make(Test22::class);
        $go = new Parallel();
        $info = $this->info;
        $go->add(function () use ($RegUserAsync, $info)
        {
            $RegUserAsync->shiming($info);
        });
        $go->wait();
    }
}
