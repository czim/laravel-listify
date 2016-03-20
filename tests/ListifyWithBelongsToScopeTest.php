<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;
use Czim\Listify\Test\Helpers\TestRelatedModel;

class ListifyWithBelongsToScopeTest extends StandardListifyTest
{

    /**
     * @test
     */
    function it_leaves_records_with_scoped_null_foreign_key_out_of_any_list()
    {
        // create a record that should be kept out of lists
        $model = $this->createNewStandardModel([ 'test_related_model_id' => null ]);

        $this->assertFalse($model->isInList(), "Model with null foreign key should not be in list");

        // add it to a list/scope
        $model->testRelatedModel()->associate(TestRelatedModel::find(3));
        $model->save();
        $model = $model->fresh();

        $this->assertEquals(6, $model->getListifyPosition(), "New Model should be at position 6");

        $model->delete();

        // also check the reverse: a model with a normal scope first
        $model = $this->createNewStandardModel([ 'test_related_model_id' => 3 ]);

        // that is then dissociated from related models
        $model->testRelatedModel()->dissociate();
        $model->save();
        $model = $model->fresh();

        $this->assertFalse($model->isInList(), "New model with empty foreign key should not be in a list");

        $model->delete();


        // it should also handle list removal by null-scope assignment correctly
        $model = $this->findStandardModel(3);

        $model->testRelatedModel()->dissociate();
        $model->save();
        $model = $model->fresh();

        $this->assertNull($model->getListifyPosition(), "New Model should have null position");
        $this->assertEquals(1, TestModel::find(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, TestModel::find(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, TestModel::find(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, TestModel::find(5)->getListifyPosition(), "Other record position incorrect");
    }


    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    /**
     * @before
     */
    protected function seedDatabase()
    {
        // first we require related models to be able to set foreign keys for
        for ($x = 0; $x < 3; $x++) {

            TestRelatedModel::create([
                'name' => 'related ' . ($x + 1),
            ]);
        }

        // set the standard models in the default scope
        parent::seedDatabase();

        // several models outside of the default scope
        for ($x = 0; $x < 4; $x++) {

            $model = $this->makeNewStandardModel([
                'name' => 'model (outside of scope) ' . ($x + 1),
                'test_related_model_id' => 1,
            ]);

            if ($x < 2) {
                $model->testRelatedModel()->associate(TestRelatedModel::find(1));
            }

            $model->save();
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

        // make it use the BelongsTo scope and make it belong to a fixed related model
        if ( ! array_key_exists('test_related_model_id', $data)) {
            $model->testRelatedModel()->associate(TestRelatedModel::find(3));
        }

        $model->setListifyConfig('scope', $model->testRelatedModel());

        return $model;
    }

    /**
     * @param int $id
     * @return TestModel
     */
    protected function findStandardModel($id)
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', $model->testRelatedModel());

        return $model;
    }

}
