<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $node_name 
 * @property string $control_name 
 * @property string $action_name 
 * @property int $is_menu 
 * @property int $type_id 
 * @property string $style 
 */
class DsAdminNode extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_admin_node';
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
    protected $casts = ['id' => 'int', 'is_menu' => 'integer', 'type_id' => 'integer'];
}