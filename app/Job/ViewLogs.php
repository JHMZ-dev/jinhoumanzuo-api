<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;
use App\Controller\async\View_logs_add;
class ViewLogs extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }

    public function handle()
    {
        $RegUserAsync = make(View_logs_add::class);
        $go = new Parallel();
        $info = $this->info;
        $go->add(function () use ($RegUserAsync, $info)
        {
            $RegUserAsync->add($info);
        });
        $go->wait();
    }
}
