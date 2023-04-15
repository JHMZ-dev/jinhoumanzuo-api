<?php

declare(strict_types=1);

namespace App\Job;
use App\Controller\async\Reg;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;
class Register extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }
    public function handle()
    {
        $Reg = make(Reg::class);
        $go = new Parallel();
        $info = $this->info;
        if($info['type'] == 1)
        {
            $go->add(function () use ($Reg, $info)
            {
                $Reg->reg($info);
            });
            $go->wait();
        }else{
            $go->add(function () use ($Reg, $info)
            {
                $Reg->daoru_reg($info);
            });
            $go->wait();
        }
    }
}
