<?php

declare(strict_types=1);

namespace App\Job;

use App\Controller\async\Qr;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;

/**
 * Class QrOk
 * @package App\Job
 */
class QrOk extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }

    public function handle()
    {
        $local = make(Qr::class);
        $go = new Parallel();
        $info = $this->info;
        if($info['type'] == 1)
        {
            $go->add(function () use ($local, $info)
            {
                $local->create_user_qr($info['to_id']);
            });
            $go->wait();
        }elseif($info['type'] == 2)
        {
            $go->add(function () use ($local, $info)
            {
                $local->create_group_qr($info['to_id']);
            });
            $go->wait();
        }elseif($info['type'] == 3)
        {
            $go->add(function () use ($local, $info)
            {
                $local->create_xxshangjia_qr($info['to_id']);
            });
            $go->wait();
        }elseif($info['type'] == 4)
        {
            $go->add(function () use ($local, $info)
            {
                $local->create_xxshangjia_mini_qr($info['to_id']);
            });
            $go->wait();
        }
    }
}
