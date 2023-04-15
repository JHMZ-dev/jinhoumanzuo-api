<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $comment_id 
 * @property int $user_id 
 * @property string $comment_content 
 * @property string $cid 
 * @property int $comment_like 
 * @property int $comment_time 
 */
class DsCinemaVideoComment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_video_comment';
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
    protected $casts = ['comment_id' => 'integer', 'user_id' => 'integer', 'comment_like' => 'integer', 'comment_time' => 'integer'];
    public $timestamps = false;
}