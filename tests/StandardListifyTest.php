<?php
/** @noinspection AccessModifierPresentedInspection */
/** @noinspection ReturnTypeCanBeDeclaredInspection */

namespace Czim\Listify\Test;

class StandardListifyTest extends AbstractListifyIntegrationTest
{

    /**
     * @test
     */
    function it_inserts_a_new_record_on_a_given_position_and_reorders_the_other_records()
    {
        $this->createNewStandardModel([
            'name'     => 'new model',
            'position' => 2,
        ]);

        $this->assertEquals(1, $this->findStandardModel(1)->getListifyPosition(), 'Record one position incorrect');
        $this->assertEquals(2, $this->findStandardModel(6)->getListifyPosition(), 'New record position incorrect');
        $this->assertEquals(3, $this->findStandardModel(2)->getListifyPosition(), 'Other record position incorrect');
        $this->assertEquals(4, $this->findStandardModel(3)->getListifyPosition(), 'Other record position incorrect');
        $this->assertEquals(5, $this->findStandardModel(4)->getListifyPosition(), 'Other record position incorrect');
        $this->assertEquals(6, $this->findStandardModel(5)->getListifyPosition(), 'Other record position incorrect');
    }

}
