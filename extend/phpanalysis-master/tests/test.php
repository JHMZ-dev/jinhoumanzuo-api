<?php
// 严格开发模式

require_once __DIR__.'/../src/PhpAnalysis.php';
echo sprintf("[%d]: %s\n", 1, getMemory());

$result_str = \Tutu\PhpAnalysis::Instance()
    ->SetSource("composer的出现真是让人们眼前一亮，web开发从此变成了一件很『好玩』的事情。")
    ->Exec( true,0);
var_dump($result_str);
echo sprintf("[%d]: %s\n", 9, getMemory());


function getMemory($memory = 0,$index=0)
{
    $index++;
    if ($memory == 0){
        $memory = memory_get_usage();
    }

    $memory = round($memory / 1024,3);

    if ($memory >= 1000){
        return getMemory($memory,$index);
    }

    if ($index == 1){
        $unit = 'KB';
    }elseif ($index == 2){
        $unit = 'MB';
    }elseif($index == 3){
        $unit = 'GB';
    }else{
        $unit = '';
    }
    return $memory.' '.$unit;
}