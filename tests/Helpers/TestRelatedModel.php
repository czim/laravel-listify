<?php
namespace Czim\Listify\Test\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property integer $id
 * @property string  $name
 */
class TestRelatedModel extends Model
{
    protected $fillable = [
        'name',
    ];

    public function testModels(): HasMany
    {
        return $this->hasMany(TestModel::class);
    }
}
