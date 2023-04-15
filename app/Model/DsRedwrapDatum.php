<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $redwrap_data_id 
 * @property string $redwrap_data_name 
 * @property string $redwrap_data_type 
 * @property string $redwrap_data_img 
 */
class DsRedwrapDatum extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_redwrap_data';
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
    protected $casts = ['redwrap_data_id' => 'integer'];
}