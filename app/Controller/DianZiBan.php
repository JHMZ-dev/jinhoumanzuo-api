<?php declare(strict_types=1);

namespace App\Controller;


use App\Controller\async\All_async;
use App\Controller\async\Auth;
use App\Controller\async\Coin;
use App\Controller\async\GongXian;
use App\Controller\async\HuoYue;
use App\Controller\async\OrderSn;
use App\Controller\async\RandomGeneration;
use App\Job\Register;
use App\Model\DsCinema;
use App\Model\DsCinemaPaiqi;
use App\Model\DsCodeLog;
use App\Model\DsErrorLog;
use App\Model\DsJiaoyiPrice;
use App\Model\DsPmSh;
use App\Model\DsPrice;
use App\Model\DsTaskPack;
use App\Model\DsTzPrice;
use App\Model\DsUser;
use App\Model\DsUserDatum;
use App\Model\DsUserGongxianzhi;
use App\Model\DsUserHuoyuedu;
use App\Model\DsUserTaskPack;
use App\Model\DsViewlog;
use App\Model\SysUser;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Di\Annotation\Inject;


/**
 * 爬取电资办信息
 * @package App\Controller
 * @Controller(prefix="dianziban")
 * Class UpdateController
 */
class DianZiBan extends XiaoController
{
    protected $url = 'https://app.zgdypw.cn/';
    protected $header = [
        'Content-Type'  => 'application/json',
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36 MicroMessenger/7.0.20.1781(0x6700143B) NetType/WIFI MiniProgramEnv/Windows WindowsWechat/WMPF XWEB/6609',
    ];
    protected $key = 'zgdypw-app';


    /**
     * 获取token
     * @RequestMapping(path="get_token",methods="get,post")
     * @return bool
     */
    public function get_token()
    {
        try {
            $sign = base64_encode(strval($this->getMillisecond().$this->key));
            $url = $this->url.'api/token/get?sign='.$sign;
            $info = $this->http->get($url,['headers' =>$this->header])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }
            $re = json_decode($info,true);
            if($re['code'] == '200')
            {
                $this->redis0->set('dianziban_token',$re['data']['token']);
                return true;
            }
            return false;
        }catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * 获取所有数据
     * @RequestMapping(path="get_info",methods="get,post")
     */
    public function get_info()
    {
        //先获取token
        $token = $this->get_token();
        if($token)
        {
            $this->get_piaofang();
            $this->get_yxpf();
            $this->get_sfpf();
            $this->get_paipian();
            return $this->withSuccess('获取成功');
        }else{
            return $this->withError('token获取失败');
        }
    }

    /**
     * 获取影票数据
     * @return bool
     */
    protected function get_piaofang()
    {
        try {
            $token = $this->redis0->get('dianziban_token');
            $date = date('Ymd');
            $url = $this->url.'api/dashboard/boxoffice/detail?date='.$date;
            $this->header['D-TOKEN'] = $token;
            $info = $this->http->get($url,['headers' =>$this->header ])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }
            $re = json_decode($info,true);
            if($re['code'] == '200')
            {
                if(!empty($re['data']['includedService']['filmBoxOfficeData']))
                {
                    $day = date('Y-m-d');
                    $data = $re['data']['includedService']['filmBoxOfficeData'];
                    $shuju = [];
                    $totalBoxOffice = $this->convert_new($re['data']['includedService']['totalBoxOffice']);
                    foreach ($data as $k => $v)
                    {
                        $shuju[$k] = [
                            'filmTotalBoxOffice'    => $this->convert_new($v['filmTotalBoxOffice']),
                            'piaofang'  => $this->convert_nc($v['dayBoxOffice']),
                            'zhanbi'  => $v['boxOfficeRate']>0?$v['boxOfficeRate'].'%':'<0.01%',
                            'paipian'  => $v['sessionRate']>0?$v['sessionRate'].'%':'<0.01%',
                            'shangzuo'  => $v['attendRate']>0?$v['attendRate'].'%':'<0.01%',
                            'filmCode'  => '',
                            'filmName'  => '',
                            'premiereDate'  => '',
                        ];
                        if(!empty($v['filmInfo']))
                        {
                            $shuju[$k]['filmCode'] = $v['filmInfo']['filmCode'];
                            $shuju[$k]['filmName'] = $v['filmInfo']['filmName'];
                            if($day == $v['filmInfo']['premiereDate'])
                            {
                                $shuju[$k]['premiereDate'] = '上映首日';
                            }else{
                                $shuju[$k]['premiereDate'] = '上映'.$this->diff_date($v['filmInfo']['premiereDate'],$day).'天';
                            }
                        }
                    }
                    if(!empty($shuju))
                    {
                        //写入缓存
                        $this->redis0->set('dianziban_piaofang',json_encode(['totalBoxOffice' =>$totalBoxOffice,'info'=> $shuju]));
                    }
                }
                return true;
            }
            if($re['code'] == '-103')
            {
                //授权错误：token已失效
                $this->get_token();
                return false;
            }
            return false;
        }catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * 获取院线票房榜
     * @return bool
     */
    protected function get_yxpf()
    {
        try {
            $token = $this->redis0->get('dianziban_token');
            $date = date('Ymd');
            $url = $this->url.'api/cinemachain/boxofficerank?areaType=COUNTRY&areaCode=000000&date='.$date;
            $this->header['D-TOKEN'] = $token;
            $info = $this->http->get($url,['headers' =>$this->header ])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }
            $re = json_decode($info,true);
            if($re['code'] == '200')
            {
                if(!empty($re['data']['cinemaChainBoxOfficeList']))
                {
                    $data = $re['data']['cinemaChainBoxOfficeList'];
                    $shuju = [];
                    foreach ($data as $k => $v)
                    {
                        $shuju[$k] = [
                            'boxOffice'    => $this->convert_new($v['boxOffice']),
                            'audience'  => $this->convert_z($v['audience']),
                            'session'  => $this->convert_z($v['session']),
                            'avgSessionOn'  => strval($v['avgSessionOn']),
                            'shortName'  => '',
                        ];
                        if(!empty($v['cinemaChainInfo']))
                        {
                            $shuju[$k]['shortName'] = $v['cinemaChainInfo']['shortName'];
                        }
                    }
                    if(!empty($shuju))
                    {
                        //写入缓存
                        $this->redis0->set('dianziban_yxpf',json_encode($shuju));
                    }
                }
                return true;
            }
            if($re['code'] == '-103')
            {
                //授权错误：token已失效
                $this->get_token();
                return false;
            }
            return false;
        }catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * 获取省份票房榜
     * @return bool
     */
    protected function get_sfpf()
    {
        try {
            $token = $this->redis0->get('dianziban_token');
            $date = date('Ymd');
            $url = $this->url.'api/province/city/boxofficerank?areaType=1&areaCode=000000&date='.$date;
            $this->header['D-TOKEN'] = $token;
            $info = $this->http->get($url,['headers' =>$this->header ])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }
            $re = json_decode($info,true);
            if($re['code'] == '200')
            {
                if(!empty($re['data']['areaBoxOfficeList']))
                {
                    $data = $re['data']['areaBoxOfficeList'];
                    $shuju = [];
                    foreach ($data as $k => $v)
                    {
                        $shuju[$k] = [
                            'boxOffice'    => $this->convert_new($v['boxOffice']),
                            'audience'  => $this->convert_z($v['audience']),
                            'session'  => $this->convert_z($v['session']),
                            'avgSessionOn'  => strval($v['avgSessionOn']),
                            'shortName'  => '',
                        ];
                        if(!empty($v['areaInfo']))
                        {
                            $shuju[$k]['shortName'] = $v['areaInfo']['areaName'];
                        }
                    }
                    if(!empty($shuju))
                    {
                        //写入缓存
                        $this->redis0->set('dianziban_sfpf',json_encode($shuju));
                    }
                }
                return true;
            }
            if($re['code'] == '-103')
            {
                //授权错误：token已失效
                $this->get_token();
                return false;
            }
            return false;
        }catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * 获取排片
     * @return bool
     */
    protected function get_paipian()
    {
        try {
            $token = $this->redis0->get('dianziban_token');
            $date = date('Ymd');
            $url = $this->url.'api/session/film?date='.$date;
            $this->header['D-TOKEN'] = $token;
            $info = $this->http->get($url,['headers' =>$this->header ])->getBody()->getContents();
            if(empty($info))
            {
                return false;
            }
            $re = json_decode($info,true);
            if($re['code'] == '200')
            {
                if(!empty($re['data']['filmData']))
                {
                    $data = $re['data']['filmData'];
                    $shuju = [];
                    foreach ($data as $k => $v)
                    {
                        $filmCode = $v['filmCode'];
                        $url2 = $this->url.'api/session/city?date='.$date.'&filmCode='.$filmCode;
                        $info2 = $this->http->get($url2,['headers' =>$this->header ])->getBody()->getContents();
                        if(!empty($info2))
                        {
                            $citySessionData = [];
                            $re2 = json_decode($info2,true);
                            if(!empty($re2['data']['citySessionData']))
                            {
                                foreach ($re2['data']['citySessionData'] as $kk => $vv)
                                {
                                    $citySessionData[$kk]['areaName'] = $vv['areaName'];
                                    $citySessionData[$kk]['session'] = $vv['session'];
                                    $citySessionData[$kk]['sessionRate'] = $vv['sessionRate'];
                                }
                                $shuju[$k] = [
                                    'filmName'  => strval($v['filmName']),
                                    'filmPicUrl'  => strval($v['filmPicUrl']),
                                    'citySessionData'  => $citySessionData,
                                ];
                            }

                        }
                    }
                    if(!empty($shuju))
                    {
                        //写入缓存
                        $this->redis0->set('dianziban_paipian',json_encode($shuju));
                    }
                }
                return true;
            }
            if($re['code'] == '-103')
            {
                //授权错误：token已失效
                $this->get_token();
                return false;
            }
            return false;
        }catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * @param $num
     * @return mixed|string
     */
    protected function convert_new($num)
    {
        switch ($num)
        {
            case ($num > 10000000000):
                $num = round($num / 10000000000, 2) . '亿';
                break;
            case ($num > 1000000):
                $num = round($num / 1000000, 2) . '万';
                break;
        }
        return strval($num);
    }
    /**
     * @param $num
     * @return mixed|string
     */
    protected function convert_z($num)
    {
        switch ($num)
        {
            case ($num > 100000000):
                $num = round($num / 100000000, 2) . '亿';
                break;
            case ($num > 10000):
                $num = round($num / 10000, 2) . '万';
                break;
        }
        return strval($num);
    }
    protected function convert_nc($num)
    {
        $wan = round($num / 1000000, 2);
        if($wan > 0.01)
        {
            return strval($wan);
        }else{
            return '<0.01';
        }
    }
    /**
     * 求两个日期之间相差的天数
     * (针对1970年1月1日之后，求之前可以采用泰勒公式)
     * @param string $date1
     * @param string $date2
     * @return number
     */
    protected function diff_date($date1, $date2)
    {
        if ($date1 > $date2) {
            $startTime = strtotime($date1);
            $endTime = strtotime($date2);
        } else {
            $startTime = strtotime($date2);
            $endTime = strtotime($date1);
        }
        $diff = $startTime - $endTime;
        $day = $diff / 86400;
        return intval($day)+1;
    }
}