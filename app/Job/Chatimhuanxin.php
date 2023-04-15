<?php

declare(strict_types=1);

namespace App\Job;

use App\Controller\ChatIm;
use Hyperf\AsyncQueue\Exception\InvalidQueueException;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;

class Chatimhuanxin extends Job
{
    public $info;
    public function __construct($info)
    {
        $this->info = $info;
    }
    public function handle()
    {
        try {
            $info = $this->info;
            $cms = make(ChatIm::class);
            $go = new Parallel();
            $go->add(function () use ($cms, $info)
            {
                $cms->get_user_token($info['user_id']);
            });
            $go->wait();
        }catch (InvalidQueueException $exception)
        {
            //var_dump($exception->getMessage());
        }
    }
}
