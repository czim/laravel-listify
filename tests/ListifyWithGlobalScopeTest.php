<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;
use Czim\Listify\Test\Helpers\TestModelWithGlobalScope;

class ListifyWithGlobalScopeTest extends StandardListifyTest
{

    protected function seedDatabase(): void
    {
        // set the standard models in the default scope
        parent::seedDatabase();

        // flag all models as inactive
        foreach (TestModelWithGlobalScope::all() as $model) {
            $model->active = false;
            $model->save();
        }

        // assert that we did in fact 'hide' them
        $this->assertEquals(0, TestModelWithGlobalScope::count(), 'Setup failed for global scoped models');
    }

    /**
     * @param int $id
     * @return TestModelWithGlobalScope|TestModel
     */
    protected function findStandardModel(int $id): TestModel
    {
        return TestModelWithGlobalScope::withoutGlobalScopes()->find($id);
    }

}
