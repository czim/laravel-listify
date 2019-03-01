<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;

class ListifyWithCallableScopeTest extends StandardListifyTest
{

    protected $regexpScopePart = '`scope` = [\'"]?2[\'"]?';

    
    /**
     * @test
     */
    function it_adds_a_created_record_to_the_correct_scope_list()
    {
        // new model outside of scope 1/2 should have position 2
        $model = $this->createNewModelWithCallableScope([ 'scope' => null ]);
        $this->assertEquals(2, $model->getListifyPosition(), "null-scoped model should have position 2");
        $model->delete();

        // model in scope with 3 items should get position 4
        $model = $this->createNewModelWithCallableScope([ 'scope' => 1 ]);
        $this->assertEquals(3, $model->getListifyPosition(), "1-scoped model should have position 3");
        $model->delete();

        // model in scope with 2 items should get position 3
        $model = $this->createNewModelWithCallableScope([ 'scope' => 2 ]);
        $this->assertEquals(6, $model->getListifyPosition(), "2-scoped model should have position 6");
        $model->delete();
    }

    /**
     * @test
     */
    function it_cleanly_moves_a_record_from_one_scope_to_another()
    {
        // a model that was just added to the bottom of one scope, then moved to another
        $model = $this->createNewModelWithCallableScope([ 'scope' => 1 ]);

        $model->scope = 2;
        $model->save();
        $model = $model->fresh();

        $this->assertEquals(6, $model->getListifyPosition(), "New Model should be at position 6 in scope 2");
        $model->delete();

        // a model that was in the middle of one scope, then moved to another
        $model = TestModel::where('scope', 2)->where('position', 2)->first();
        $model->setListifyConfig('scope', $this->getScopeMethodCallable());

        $model->scope = 1;
        $model->save();
        $model = $model->fresh();

        $this->assertEquals(3, $model->getListifyPosition(), "New Model should be at position 3 in scope 1");
        $this->assertEquals(1, TestModel::find(1)->getListifyPosition(), "Other record position incorrect (2)");
        $this->assertEquals(2, TestModel::find(3)->getListifyPosition(), "Other record position incorrect (1)");
        $this->assertEquals(3, TestModel::find(4)->getListifyPosition(), "Other record position incorrect (1)");
        $this->assertEquals(4, TestModel::find(5)->getListifyPosition(), "Other record position incorrect (1)");
        $this->assertEquals(1, TestModel::find(6)->getListifyPosition(), "Other record position incorrect (null)");
        $this->assertEquals(1, TestModel::find(7)->getListifyPosition(), "Other record position incorrect (2)");
        $this->assertEquals(2, TestModel::find(8)->getListifyPosition(), "Other record position incorrect (2)");
    }

    /**
     * @test
     */
    function it_leaves_records_with_a_null_scope_out_of_any_list()
    {
        // create a record that should be kept out of lists
        $model = $this->createNewModelWithCallableScope([ 'scope' => 99 ]);

        // add it to a list/scope
        $model->scope = 2;
        $model->save();
        $model = $model->fresh();

        $this->assertEquals(2, $model->scope, "New Model should be at position 6 in scope 2");
        $this->assertEquals(6, $model->getListifyPosition(), "New Model should be at position 6 in scope 2");

        $model->delete();


        // also check the reverse: a model with a normal scope first
        $model = $this->createNewModelWithCallableScope([ 'scope' => 2 ]);

        // that is then updated with a null-scope
        $model->scope = 99;
        $model->save();
        $model = $model->fresh();

        $this->assertEquals(99, $model->scope, "New Model should not be in a list in scope 99");
        $this->assertFalse($model->isInList(), "New Model should not be in a list in scope 99");

        $model->delete();


        // it should also handle list removal by null-scope assignment correctly
        $model = $this->findStandardModel(3);

        $model->scope = 99;
        $model->save();
        $model = $model->fresh();

        $this->assertNull($model->getListifyPosition(), "New Model should have null position in scope 99");
        $this->assertEquals(1, TestModel::find(1)->getListifyPosition(), "Other record position incorrect (2)");
        $this->assertEquals(2, TestModel::find(2)->getListifyPosition(), "Other record position incorrect (2)");
        $this->assertEquals(3, TestModel::find(4)->getListifyPosition(), "Other record position incorrect (2)");
        $this->assertEquals(4, TestModel::find(5)->getListifyPosition(), "Other record position incorrect (2)");
    }


    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    protected function seedDatabase()
    {
        parent::seedDatabase();

        // 1 model outside of the applicable scopes
        for ($x = 0; $x < 1; $x++) {

            $this->createNewModelWithCallableScope([
                'name'  => 'model (null scope) ' . ($x + 1),
                'scope' => null,
            ]);
        }

        // 2 models outside with scope '1'
        for ($x = 0; $x < 2; $x++) {

            $this->createNewModelWithCallableScope([
                'name'  => 'model (scope 1) ' . ($x + 1),
                'scope' => 1,
            ]);
        }
    }

    /**
     * Prepares a new model set up to use a callable scope, but does not save it
     *
     * @param array $data
     * @return TestModel
     */
    protected function makeNewModelWithCallableScope(array $data = [])
    {
        $model = new TestModel($data);

        $model->setListifyConfig('scope', $this->getScopeMethodCallable());

        return $model;
    }

    /**
     * Creates a new model set up to use a callable scope
     *
     * @param array $data
     * @return TestModel
     */
    protected function createNewModelWithCallableScope(array $data = [])
    {
        $model = $this->makeNewModelWithCallableScope($data);
        $model->save();

        return $model;
    }


    /**
     * Returns callable to use as scope
     *
     * @return callable
     */
    protected function getScopeMethodCallable()
    {
        return function (TestModel $model) { return $this->scopeMethod($model); };
    }

    /**
     * Callable to use as scope setting
     *
     * @param TestModel $model
     * @return string
     */
    protected function scopeMethod(TestModel $model)
    {
        // null-scope under very specific conditions
        if ($model->scope == 99) return null;

        if ( ! $model->scope) return '`scope` is null';

        return '`scope` = ' . (int) $model->scope;
    }

    /**
     * Prepares a new listified model set up for standard tests
     *
     * @param array $data
     * @return TestModel
     */
    protected function makeNewStandardModel(array $data = [])
    {
        $model = parent::makeNewStandardModel($data);

        // make it use the BelongsTo scope and make it belong to a fixed related model
        $model->scope = 2;

        $model->setListifyConfig('scope', $this->getScopeMethodCallable());

        return $model;
    }

    /**
     * @param int $id
     * @return TestModel
     */
    protected function findStandardModel($id)
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', $this->getScopeMethodCallable());

        return $model;
    }

}
