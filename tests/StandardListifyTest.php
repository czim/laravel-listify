<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;

class StandardListifyTest extends TestCase
{

    /**
     * Regular expression where content to check for scope query SQL
     *
     * @var string
     */
    protected $regexpScopePart = '1 = 1';


    /**
     * @test
     */
    function it_offers_a_list_query_builder_scope()
    {
        $model = $this->findStandardModel(1);
        $regex = '#^select \* from [\'"]?test_models[\'"]? where ' . $this->regexpScopePart . '$#i';

        $this->assertRegExp($regex, $model->listifyScope()->toSql(), "Listify scope does not return correct SQL");
    }

    /**
     * @test
     */
    function it_offers_a_scope_for_records_in_a_list()
    {
        $model = $this->findStandardModel(1);
        $regex = '#^select \* from [\'"]?test_models[\'"]? where ' . $this->regexpScopePart
               . ' and [\'"]?test_models[\'"]?.[\'"]?position[\'"]? is not null$#i';

        $this->assertRegExp($regex, $model->inList()->toSql(), "In list scope does not return correct SQL");
    }

    /**
     * @test
     */
    function it_can_set_and_save_a_list_position_while_handling_the_surrounding_records()
    {
        $model = $this->findStandardModel(3);

        $model->setListPosition(1);

        $this->assertEquals(1, $model->getListifyPosition(), "Model does not have directly set position");
        $this->assertEquals(2, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_can_set_a_list_position_directly_without_any_checks()
    {
        $model = $this->findStandardModel(3);

        $model->setListifyPosition(1);

        $this->assertEquals(1, $model->getListifyPosition(), "Model does not have directly set position");
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_correctly_applies_the_default_position_when_adding_a_new_record()
    {
        $model = $this->createNewStandardModel([ 'name' => 'new bottom' ]);

        $this->assertEquals(6, $model->getListifyPosition(), "New default bottom position should be 6");

        $lowestModelId = $model->id;

        $model = $this->makeNewStandardModel([ 'name' => 'new top' ]);
        $model->setListifyConfig('add_new_at', 'top');
        $model->save();


        $this->assertEquals(1, $model->getListifyPosition(), "New default top position should be 1");
        $this->assertEquals(7, $this->findStandardModel($lowestModelId)->getListifyPosition(), "The bottom record should be at position 7");
    }

    /**
     * @test
     */
    function it_can_remove_an_item_from_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->removeFromList();
        $model = $model->fresh();

        $this->assertEquals(null, $model->getListifyPosition(), "Model not without position after removing");
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_adjusts_positions_when_a_record_is_deleted()
    {
        $model = $this->findStandardModel(3);

        $model->delete();

        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_can_insert_an_item_into_a_different_position_in_the_list()
    {
        $model = $this->findStandardModel(2);

        $model->insertAt(4);
        $model = $model->fresh();

        $this->assertEquals(4, $model->getListifyPosition(), "Model not in inserted position");
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, $this->findStandardModel(3)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     * @depends it_can_remove_an_item_from_the_list
     */
    function it_can_insert_an_item_into_a_position_from_outside_of_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->removeFromList();
        $model = $model->fresh();

        $model->insertAt(2);
        $model = $model->fresh();

        $this->assertEquals(2, $model->getListifyPosition(), "Model not in inserted position");
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_can_move_a_record_higher_up_on_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->moveHigher();
        $model = $model->fresh();

        $this->assertEquals(2, $model->getListifyPosition(), "Model not at correct position after moving one up");
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), "Replaced record position incorrect");
    }

    /**
     * @test
     */
    function it_can_move_a_record_lower_down_on_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->moveLower();
        $model = $model->fresh();

        $this->assertEquals(4, $model->getListifyPosition(), "Model not at correct position after moving one down");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Replaced record position incorrect");
    }

    /**
     * @test
     */
    function it_can_move_a_record_to_the_top_of_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->moveToTop();
        $model = $model->fresh();

        $this->assertEquals(1, $model->getListifyPosition(), "Model not at top position after moving");
        $this->assertEquals(2, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_can_move_a_record_to_the_bottom_of_the_list()
    {
        $model = $this->findStandardModel(3);

        $model->moveToBottom();
        $model = $model->fresh();

        $this->assertEquals(5, $model->getListifyPosition(), "Model not at top position after moving");
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(2, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect");
        $this->assertEquals(4, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect");
    }

    /**
     * @test
     */
    function it_can_increment_a_position_without_saving_it()
    {
        $model = $this->findStandardModel(2);

        $model->incrementPosition(2);

        $this->assertEquals(4, $model->getListifyPosition(), "Model does not have new position in memory");
        $this->assertEquals(2, $model->fresh()->getListifyPosition(), "Model should have old position in database");

        // other models should not be affected before saving
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(3, $this->findStandardModel(3)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(4, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect before saving");

        $model->save();
        $model = $model->fresh();

        $this->assertEquals(4, $model->getListifyPosition(), "Model should have new position after saving");

        // other models should be affected after saving
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(2, $this->findStandardModel(3)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(3, $this->findStandardModel(4)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect after saving");
    }
    
    /**
     * @test
     */
    function it_can_decrement_a_position()
    {
        $model = $this->findStandardModel(4);

        $model->decrementPosition(2);

        $this->assertEquals(2, $model->getListifyPosition(), "Model does not have new position in memory");
        $this->assertEquals(4, $model->fresh()->getListifyPosition(), "Model should have old position in database");

        // other models should not be affected before saving
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(2, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(3, $this->findStandardModel(3)->getListifyPosition(), "Other record position incorrect before saving");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect before saving");

        $model->save();
        $model = $model->fresh();

        $this->assertEquals(2, $model->getListifyPosition(), "Model should have new position after saving");

        // other models should be affected after saving
        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(4, $this->findStandardModel(3)->getListifyPosition(), "Other record position incorrect after saving");
        $this->assertEquals(5, $this->findStandardModel(5)->getListifyPosition(), "Other record position incorrect after saving");
    }


    /**
     * @test
     * @depends it_can_remove_an_item_from_the_list
     */
    function it_reports_whether_a_record_is_in_the_list()
    {
        $this->assertTrue($this->findStandardModel(1)->isInList(), "Record in list should report as such");
        $this->assertFalse($this->findStandardModel(1)->isNotInList(), "Record in list should not report the reverse");

        $model = $this->findStandardModel(1);
        $model->removeFromList();

        $this->assertFalse($this->findStandardModel(1)->isInList(), "Record not in list should report as such");
        $this->assertTrue($this->findStandardModel(1)->isNotInList(), "Record not in list should not report the reverse");
    }

    /**
     * @test
     * @depends it_can_remove_an_item_from_the_list
     */
    function it_reports_whether_a_record_is_first_in_the_list()
    {
        $this->assertTrue($this->findStandardModel(1)->isFirst(), "First record should report as such");
        $this->assertFalse($this->findStandardModel(3)->isFirst(), "Middle record should not report as first");

        $model = $this->findStandardModel(1);

        $model->removeFromList();
        $model = $model->fresh();

        $this->assertFalse($model->isFirst(), "Record not in list should not report as first");
    }

    /**
     * @test
     * @depends it_can_remove_an_item_from_the_list
     */
    function it_reports_whether_a_record_is_last_in_the_list()
    {
        $this->assertTrue($this->findStandardModel(5)->isLast(), "Last record should report as such");
        $this->assertFalse($this->findStandardModel(3)->isLast(), "Middle record should not report as last");

        $model = $this->findStandardModel(5);

        $model->removeFromList();
        $model = $model->fresh();

        $this->assertFalse($model->isLast(), "Record not in list should not report as last");
    }

    /**
     * @test
     */
    function it_returns_the_record_at_one_position_above()
    {
        $model = $this->findStandardModel(3);

        $higher = $model->higherItem();

        $this->assertInstanceOf(TestModel::class, $higher);
        $this->assertEquals(2, $higher->id);
    }

    /**
     * @test
     */
    function it_returns_the_record_at_one_position_below()
    {
        $model = $this->findStandardModel(3);

        $lower = $model->lowerItem();

        $this->assertInstanceOf(TestModel::class, $lower);
        $this->assertEquals(4, $lower->id);
    }

    // ------------------------------------------------------------------------------
    //      Setup and Helper methods
    // ------------------------------------------------------------------------------

    /**
     * @before
     */
    protected function seedDatabase()
    {
        for ($x = 0; $x < 5; $x++) {

            $this->createNewStandardModel([ 'name' => 'model ' . ($x + 1) ]);
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
        $model = new TestModel($data);

        return $model;
    }

    /**
     * Creates new listified model set up for standard tests
     *
     * @param array $data
     * @return TestModel
     */
    protected function createNewStandardModel(array $data = [])
    {
        $model = $this->makeNewStandardModel($data);

        $model->save();

        return $model;
    }

    /**
     * @param int $id
     * @return TestModel
     */
    protected function findStandardModel($id)
    {
        return TestModel::find($id);
    }

}
