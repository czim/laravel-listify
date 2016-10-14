<?php
namespace Czim\Listify\Test\Helpers;

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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function cleanListifyScopedQuery($query)
    {
        return $query->withoutGlobalScopes();
    }

}
