<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $username 
 * @property string $title 
 * @property string $password 
 * @property int $phone 
 * @property string $email 
 * @property int $status 
 * @property int $addtime 
 * @property int $role_id 
 */
class DsAdmin extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ds_admin';
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
    protected $casts = ['id' => 'int', 'phone' => 'integer', 'status' => 'integer', 'addtime' => 'integer', 'role_id' => 'integer'];
    public $timestamps = false;
}