<?php
/**
 * Created by PhpStorm.
 * User: dingxiang-inc
 * Date: 2017/8/25
 * Time: 9:26
 */
include ("CaptchaClient.php");
/**
 * 构造入参为appId和appSecret
 * appId和前端验证码的appId保持一致，appId可公开
 * appSecret为秘钥，请勿公开
 * token在前端完成验证后可以获取到，随业务请求发送到后台，token有效期为两分钟
 **/
$appId = "982f0301767f71cfe2903b86ed6895d9";
$appSecret = "447f8a55f2bcde7d22868e5d36fdd03f";
$client = new CaptchaClient($appId,$appSecret);
$client->setTimeOut(2);      //设置超时时间
# $client->setCaptchaUrl("http://cap.dingxiang-inc.com/api/tokenVerify");   //特殊情况需要额外指定服务器,可以在这个指定，默认情况下不需要设置
$response = $client->verifyToken("7E62E390F1A8AEFB4336B3103350DD41E078296320B577FC2C7B03A8BC6A880ADF029D3BECC7A054DF3C544E695861517970A92A37BFFE543438E0F1A78D7AF6CFD13B89154D1BDA393CB87C0E6FBC06:5d59fb76lRCuatGwWK757lgnMcMTaNUhhIl6t4X1");

pr($response);
// echo $response->serverStatus;
// //确保验证状态是SERVER_SUCCESS，SDK中有容错机制，在网络出现异常的情况会返回通过
// if($response->result){
//     echo "true";
//     /**token验证通过，继续其他流程**/
// }else{
//     echo "false";
//     /**token验证失败**/
// }