<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;

class ListifyWithStringScopeTest extends StandardListifyTest
{

    protected $regexpScopePart = '`scope` = [\'"]?1[\'"]?';

    
    ///**
    // * @test
    // */
    //function it_leaves_records_with_scoped_null_foreign_key_out_of_any_list()
    //{
    //    // create a record that should be kept out of lists
    //    $model = $this->createNewStandardModel([ 'test_related_model_id' => null ]);
    //
    //    $this->assertFalse($model->isInList(), "Model with null foreign key should not be in list");
    //
    //    // add it to a list/scope
    //    $model->testRelatedModel()->associate(TestRelatedModel::find(3));
    //    $model->save();
    //    $model = $model->fresh();
    //
    //    $this->assertEquals(6, $model->getListifyPosition(), "New Model should be at position 6");
    //
    //    $model->delete();
    //
    //    // also check the reverse: a model with a normal scope first
    //    $model = $this->createNewStandardModel([ 'test_related_model_id' => 3 ]);
    //
    //    // that is then dissociated from related models
    //    $model->testRelatedModel()->dissociate();
    //    $model->save();
    //    $model = $model->fresh();
    //
    //    $this->assertFalse($model->isInList(), "New model with empty foreign key should not be in a list");
    //
    //    $model->delete();
    //
    //
    //    // it should also handle list removal by null-scope assignment correctly
    //    $model = $this->findStandardModel(3);
    //
    //    $model->testRelatedModel()->dissociate();
    //    $model->save();
    //    $model = $model->fresh();
    //
    //    $this->assertNull($model->getListifyPosition(), "New Model should have null position");
    //    $this->assertEquals(1, TestModel::find(1)->getListifyPosition(), "Other record position incorrect");
    //    $this->assertEquals(2, TestModel::find(2)->getListifyPosition(), "Other record position incorrect");
    //    $this->assertEquals(3, TestModel::find(4)->getListifyPosition(), "Other record position incorrect");
    //    $this->assertEquals(4, TestModel::find(5)->getListifyPosition(), "Other record position incorrect");
    //}


    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    protected function seedDatabase()
    {
        // set the standard models in the default scope
        parent::seedDatabase();

        // several models outside of the default scope
        for ($x = 0; $x < 3; $x++) {

            $this->createNewStandardModel([
                'name'  => 'model (outside of scope) ' . ($x + 1),
                'scope' => 0,
            ]);
        }
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

        if ( ! array_key_exists('scope', $data)) {
            $model->scope = 1;
        }

        $model->setListifyConfig('scope', '`scope` = 1');

        return $model;
    }

    /**
     * @param int $id
     * @return TestModel
     */
    protected function findStandardModel($id)
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', '`scope` = 1');

        return $model;
    }

}
