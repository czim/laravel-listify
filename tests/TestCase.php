<?php
namespace Czim\Listify\Test;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    public const TABLE_NAME_SIMPLE  = 'test_models';
    public const TABLE_NAME_RELATED = 'test_related_models';


    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }


    public function setUp(): void
    {
        parent::setUp();

        $this->migrateDatabase();
    }


    protected function migrateDatabase(): void
    {
        Schema::create(self::TABLE_NAME_SIMPLE, function ($table) {
            $table->increments('id');
            $table->string('name', 255)->nullable();
            $table->integer('scope')->unsigned()->nullable();
            $table->integer('test_related_model_id')->nullable()->unsigned();
            $table->integer('position')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create(self::TABLE_NAME_RELATED, function ($table) {
            $table->increments('id');
            $table->string('name', 255)->nullable();
            $table->timestamps();
        });
    }

}
