<?php
namespace Czim\Listify\Test\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TestRelatedModel
 *
 * @property integer $id
 * @property string  $name
 */
class TestRelatedModel extends Model
{
    protected $fillable = [
        'name',
    ];
    
    public function testModels()
    {
        return $this->hasMany(TestModel::class);
    }
}
