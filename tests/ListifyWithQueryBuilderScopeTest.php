<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;
use Illuminate\Support\Facades\DB;

class ListifyWithQueryBuilderScopeTest extends StandardListifyTest
{

    protected $regexpScopePart = '[\'"]?name[\'"]? like [\'"]?%model%[\'"]?';


    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    /**
     * Prepares a new listified model set up for standard tests
     *
     * @param array $data
     * @return TestModel
     */
    protected function makeNewStandardModel(array $data = [])
    {
        $model = parent::makeNewStandardModel($data);

        $model->setListifyConfig('scope', $this->getScopeQueryBuilder());

        return $model;
    }

    /**
     * @param int $id
     * @return TestModel
     */
    protected function findStandardModel($id)
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', $this->getScopeQueryBuilder());

        return $model;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getScopeQueryBuilder()
    {
        return DB::table('test_models')->where('name', 'like', '%model%');
    }

}
