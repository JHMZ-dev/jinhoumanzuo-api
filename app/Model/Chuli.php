<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Utils\ApplicationContext;
use PHPMailer\PHPMailer\PHPMailer;

//处理繁琐的共用方法
class Chuli
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = '';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * @var bool
     */
    public $timestamps = false;


    /**身份证号码正则匹配校验
     * @param $value
     * @return bool
     */
    public static function validateIdCard($value){
        if (!preg_match('/^\d{17}[0-9xX]$/', $value)) { //基本格式校验
            return false;
        }
        $parsed = date_parse(substr($value, 6, 8));
        if (!(isset($parsed['warning_count'])
            && $parsed['warning_count'] == 0)) { //年月日位校验
            return false;
        }
        $base = substr($value, 0, 17);
        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $tokens = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $checkSum = 0;
        for ($i=0; $i<17; $i++) {
            $checkSum += intval(substr($base, $i, 1)) * $factor[$i];
        }
        $mod = $checkSum % 11;
        $token = $tokens[$mod];
        $lastChar = strtoupper(substr($value, 17, 1));
        return ($lastChar === $token); //最后一位校验位校验
    }

    /**
     * 返回毫秒级
     * @return float
     */
    public static function get_time_13number(){
        //返回毫秒级
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /**隐藏字符串中某些字符串用 指定字符代替
     * @param $str
     * @param $start
     * @param $length
     * @param $replacement
     * @return string
     */
    public static function str_hide($str,$start = 0, $length = 1,$replacement = '*'){
        $len = mb_strlen($str,'utf-8');
        if ($len > intval($start+$length)) {
            $str1 = mb_substr($str,0,$start,'utf-8');
            $str2 = mb_substr($str,intval($start+$length),NULL,'utf-8');
        } else {
            $str1 = mb_substr($str,0,1,'utf-8');
            $str2 = mb_substr($str,$len-1,1,'utf-8');
            $length = $len - 2;
        }
        $new_str = $str1;
        for ($i = 0; $i < $length; $i++) {
            $new_str .= $replacement;
        }
        $new_str .= $str2;
        return $new_str;
    }

    /**二维数组去重
     * @param $array2D
     * @return array
     */
    public static function array_unique_fb($array2D=[]) {
        $temp = [];
        foreach ($array2D as $v) {
            $v = join(",", $v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
            $temp[] = $v;
        }
        $temp = array_unique($temp);//去掉重复的字符串,也就是重复的一维数组
        foreach ($temp as $k => $v) {
            $temp[$k] = explode(",", $v);//再将拆开的数组重新组装
        }
        return $temp;
    }

    public static function time_have($time){
        $timeNow = time();
        $timeOver = ($time);
        $day = intval(($timeOver-$timeNow)/86400);
        $hour = intval((($timeOver-$timeNow)%86400)/3600);
        $minute = intval(((($timeOver-$timeNow)%86400)%3600)/60);
        $second = intval(((($timeOver-$timeNow)%86400)%3600)%60);

        $data = [
            'day' => 0,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
        ];
        if($day){
            $data['day'] = $day;
        }
        if($hour){
            $data['hour'] = $hour;
        }
        if($minute){
            $data['minute'] = $minute;
        }
        if($second){
            $data['second'] = $second;
        }

        return $data;
    }


}