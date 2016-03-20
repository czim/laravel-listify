<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;
use Czim\Listify\Test\Helpers\TestRelatedModel;

class ListifyWithBelongsToScopeTest extends StandardListifyTest
{


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
        if ( ! isset($data['test_related_model_id'])) {
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
