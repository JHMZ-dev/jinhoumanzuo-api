<?php

declare(strict_types=1);

namespace App\Job;

use App\Controller\AliCmsSend;
use Hyperf\AsyncQueue\Exception\InvalidQueueException;
use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\Parallel;
use Hyperf\DbConnection\Db;
class AliCms extends Job
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
            $cms = make(AliCmsSend::class);
            $go = new Parallel();
            //记录发送日志
            $data = [
                'code_log_type'     => $info['code_type'],
                'code_log_mobile'   => $info['mobile'],
                'code_log_code'     => $info['code'],
                'code_log_time'     => time(),
            ];
            //写入发送日志
            Db::table('ds_code_log')->insert($data);

            //发送短信
            switch ($info['code_type'])
            {
                case 'sell_out':
                    //交易
                    unset($info['code_type']);
                    unset($info['code']);
                    $go->add(function () use ($cms, $info)
                    {
                        $cms->sell_out($info['mobile']);
                    });
                    $go->wait();
                    break;
                case 'jiaoyi_ok':
                    //交易
                    unset($info['code_type']);
                    unset($info['code']);
                    $go->add(function () use ($cms, $info)
                    {
                        $cms->jiaoyi_ok($info['mobile']);
                    });
                    $go->wait();
                    break;
                default:
                    unset($info['code_type']);
                    $go->add(function () use ($cms, $info)
                    {
                        $mobile = $info['mobile'];
                        unset($info['mobile']);
                        $cms->send_register($mobile,$info);
                    });
                    $go->wait();
                    break;
            }
        }catch (InvalidQueueException $exception)
        {
            var_dump($exception->getMessage());
        }
    }
}
