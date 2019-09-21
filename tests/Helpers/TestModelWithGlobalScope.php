<?php
namespace Czim\Listify\Test\Helpers;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class TestModelWithGlobalScope
 */
class TestModelWithGlobalScope extends TestModel
{
    protected $table = 'test_models';


    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new ActiveScope);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function cleanListifyScopedQuery($query): Builder
    {
        return $query->withoutGlobalScopes();
    }

}
