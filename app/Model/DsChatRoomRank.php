<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $chat_qun_room_id 
 * @property int $user_id 
 * @property int $type 
 * @property int $time 
 * @property float $money 
 */
class DsChatRoomRank extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_chat_room_rank';
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
    protected $casts = ['chat_qun_room_id' => 'integer', 'user_id' => 'integer', 'type' => 'integer', 'time' => 'integer', 'money' => 'float'];
}