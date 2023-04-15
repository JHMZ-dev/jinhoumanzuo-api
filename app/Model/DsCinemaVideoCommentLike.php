<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $comment_like_id 
 * @property int $user_id 
 * @property int $comment_id 
 * @property string $cid 
 */
class DsCinemaVideoCommentLike extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_video_comment_like';
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
    protected $casts = ['comment_like_id' => 'integer', 'user_id' => 'integer', 'comment_id' => 'integer'];
    public $timestamps = false;
}