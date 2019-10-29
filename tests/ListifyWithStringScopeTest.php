<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;

class ListifyWithStringScopeTest extends AbstractListifyIntegrationTest
{

    protected $regexpScopePart = '`scope` = [\'"]?1[\'"]?';


    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    protected function seedDatabase(): void
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
    protected function makeNewStandardModel(array $data = []): TestModel
    {
        $model = parent::makeNewStandardModel($data);

        if ( ! array_key_exists('scope', $data)) {
            $model->scope = 1;
        }

        $model->setListifyConfig('scope', '`scope` = 1');

        return $model;
    }

    protected function findStandardModel(int $id): TestModel
    {
        $model = parent::findStandardModel($id);

        $model->setListifyConfig('scope', '`scope` = 1');

        return $model;
    }

}
