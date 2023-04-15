<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $notice_id 
 * @property string $cont 
 * @property string $img 
 * @property string $img_url 
 * @property int $time 
 * @property string $title 
 */
class DsNotice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_notice';
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
    protected $casts = ['notice_id' => 'integer', 'time' => 'integer'];
    public $timestamps = false;
}