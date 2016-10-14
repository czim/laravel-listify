<?php
namespace Czim\Listify\Test\Helpers;

use Czim\Listify\Contracts\ListifyInterface;
use Czim\Listify\Listify;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TestModel
 *
 * @property integer $id
 * @property string  $name
 * @property integer $scope
 * @property integer $position
 * @property bool    $active
 */
class TestModel extends Model implements ListifyInterface
{
    use Listify;

    protected $fillable = [
        'name',
        'scope',
        'position',
        'test_related_model_id',
    ];
    
    protected $casts = [
        'position' => 'integer',
        'active'   => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->initListify();
    }
    
    public function testRelatedModel()
    {
        return $this->belongsTo(TestRelatedModel::class);
    }
}
