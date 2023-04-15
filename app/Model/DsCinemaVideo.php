<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $cinema_video_id 
 * @property string $cid 
 * @property string $filmCode 
 * @property string $filmName 
 * @property string $version 
 * @property string $duration 
 * @property string $publishDate 
 * @property string $director 
 * @property int $castType 
 * @property string $cast 
 * @property string $introduction 
 * @property int $wantview 
 * @property int $score 
 * @property string $cover 
 * @property string $area 
 * @property string $type 
 * @property string $planNum 
 * @property int $preSaleFlag 
 * @property int $comment_num 
 * @property int $like_num 
 */
class DsCinemaVideo extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_cinema_video';
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
    protected $casts = ['cinema_video_id' => 'integer', 'castType' => 'integer', 'wantview' => 'integer', 'score' => 'integer', 'preSaleFlag' => 'integer', 'comment_num' => 'integer', 'like_num' => 'integer'];
    public $timestamps = false;
}