<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ListifyWithQueryBuilderScopeTest extends AbstractListifyIntegrationTest
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
    protected function makeNewStandardModel(array $data = []): TestModel
    {
        $model = parent::makeNewStandardModel($data);

        $model->setListifyConfig('scope', $this->getScopeQueryBuilder());

        return $model;
    }

    protected function findStandardModel(int $id): TestModel
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', $this->getScopeQueryBuilder());

        return $model;
    }

    protected function getScopeQueryBuilder(): Builder
    {
        return DB::table('test_models')->where('name', 'like', '%model%');
    }

}
