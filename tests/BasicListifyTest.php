<?php
namespace Czim\Listify\Test;

use Czim\Listify\Test\Helpers\TestModel;

class BasicListifyTest extends TestCase
{

    /**
     * @test
     */
    function it_has_default_config_settings()
    {
        $model = new TestModel;

        $this->assertEquals(1, $model->listifyTop(), "Incorrect listifyTop value");
        $this->assertEquals('position', $model->positionColumn(), "Incorrect positionColumn value");
        $this->assertEquals('1 = 1', $model->getScopeName(), "Incorrect scopeName value");
        $this->assertEquals('bottom', $model->addNewAt(), "Incorrect addNewAt value");
    }

    /**
     * @test
     */
    function it_can_change_config_settings()
    {
        $model = new TestModel;

        $model->setListifyConfig('top_of_list', 13);
        $this->assertEquals(13, $model->listifyTop(), "Incorrect listifyTop value");

        $model->setListifyConfig('column', 'e_position');
        $this->assertEquals('e_position', $model->positionColumn(), "Incorrect positionColumn value");

        $model->setListifyConfig('scope', '2 = 2');
        $this->assertEquals('2 = 2', $model->getScopeName(), "Incorrect scopeName value");

        $model->setListifyConfig('add_new_at', 'top');
        $this->assertEquals('top', $model->addNewAt(), "Incorrect addNewAt value");
    }

    /**
     * @test
     */
    function it_reports_the_default_list_position()
    {
        $model = new TestModel;

        $this->assertNull($model->defaultPosition());
    }

    /**
     * @test
     */
    function it_reports_whether_a_record_is_in_the_default_position()
    {
        $model = new TestModel;

        $this->assertTrue($model->isDefaultPosition());

        $model->setListPosition(2);

        $this->assertFalse($model->isDefaultPosition());
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    function it_throws_an_exception_when_config_setting_for_add_new_at_has_no_method()
    {
        $model = new TestModel;

        $model->setListifyConfig('add_new_at', 'existsNot');
    }

}
