<?php
/**
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Migrations\Test\TestCase\Shell\Task;

use Bake\Shell\Task\BakeTemplateTask;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Schema\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Inflector;
use Migrations\Migrations;
use Migrations\Test\TestCase\Shell\TestCompletionStringOutput;

/**
 * MigrationSnapshotTaskTest class
 */
class MigrationDiffTaskTest extends TestCase
{
    use StringCompareTrait;

    public $out;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Returns a MigrationSnapshotTask mock object properly configured
     *
     * @param array $mockedMethods List of methods to mock
     * @return \Migrations\Shell\Task\MigrationSnapshotTask mock
     */
    public function getTaskMock($mockedMethods = [])
    {
        $mockedMethods = $mockedMethods ?: ['in', 'err', 'dispatchShell', '_stop'];

        $this->out = new TestCompletionStringOutput();
        $io = new ConsoleIo($this->out);

        $task = $this->getMock(
            'Migrations\Shell\Task\MigrationDiffTask',
            $mockedMethods,
            [$io]
        );
        $task->name = 'Migration';
        $task->connection = 'test';
        $task->BakeTemplate = new BakeTemplateTask($io);
        $task->BakeTemplate->initialize();
        $task->BakeTemplate->interactive = false;
        return $task;
    }

    /**
     * Tests that baking a diff while history is not in sync will trigger an error
     *
     * @return void
     */
    public function testHistoryNotInSync()
    {
        $this->Task = $this->getTaskMock(['error']);
        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';

        $expectedMessage = 'Your migrations history is not in sync with your migrations files. '
            . 'Make sure all your migrations have been migrated before baking a diff.';
        $this->Task->expects($this->any())
            ->method('error')
            ->with($expectedMessage);

        $this->Task->bake('NotInSync');
    }

    /**
     * Tests that baking a diff while history is empty and no migration files exists
     * will fall back to baking a snapshot
     *
     * @return void
     */
    public function testEmptyHistoryNoMigrations()
    {
        $this->Task = $this->getTaskMock(['error', 'dispatchShell']);
        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'Blog';
        $this->Task->plugin = 'Blog';

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with([
                'command' => 'bake migration_snapshot EmptyHistoryNoMigrations -c test -p Blog'
            ]);

        $this->Task->bake('EmptyHistoryNoMigrations');
    }

    /**
     * Tests that baking a diff while history is empty and no migration files exists
     * will fall back to baking a snapshot.
     * If the snapshot baking returns an error, an error is raised by the diff task
     *
     * @return void
     */
    public function testEmptyHistoryNoMigrationsError()
    {
        $this->Task = $this->getTaskMock(['error', 'dispatchShell']);
        $this->Task->params['require-table'] = false;
        $this->Task->params['connection'] = 'test';
        $this->Task->params['plugin'] = 'Blog';
        $this->Task->plugin = 'Blog';

        $this->Task->expects($this->once())
            ->method('dispatchShell')
            ->with([
                'command' => 'bake migration_snapshot EmptyHistoryNoMigrations -c test -p Blog'
            ])
            ->will($this->returnValue(1));

        $this->Task->expects($this->any())
            ->method('error')
            ->with('Something went wrong during the snapshot baking. Please try again.');

        $this->Task->bake('EmptyHistoryNoMigrations');
    }

    /**
     * Tests baking a diff
     *
     * @return void
     */
    public function testBakingDiff()
    {
        $this->skipIf(env('DB') === 'sqlite');

        $diffConfigFolder = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff'. DS;
        $diffMigrationsPath = $diffConfigFolder . 'the_diff_' . env('DB') . '.php';
        $diffDumpPath = $diffConfigFolder . 'schema-dump-test_comparisons_' . env('DB');

        $destinationConfigDir = ROOT . 'config' . DS . 'MigrationsDiff' . DS;
        $destination = $destinationConfigDir . '20160415220805_TheDiff' . ucfirst(env('DB')) . '.php';
        $destinationDumpPath = $destinationConfigDir . 'schema-dump-test_comparisons_' . env('DB');
        copy($diffMigrationsPath, $destination);

        $this->getMigrations()->migrate();

        unlink($destination);
        copy($diffDumpPath, $destinationDumpPath);

        $connection = ConnectionManager::get('test_comparisons');
        $connection->newQuery()
            ->delete('phinxlog')
            ->where(['version' => 20160415220805])
            ->execute();

        $this->_compareBasePath = Plugin::path('Migrations') . 'tests' . DS . 'comparisons' . DS . 'Diff' . DS;

        $this->Task = $this->getTaskMock(['getDumpSchema']);
        $this->Task
            ->method('getDumpSchema')
            ->will($this->returnValue(unserialize(file_get_contents($destinationDumpPath))));

        $this->Task->params['connection'] = 'test_comparisons';
        $this->Task->pathFragment = 'config/MigrationsDiff/';
        $this->Task->connection = 'test_comparisons';

        $bakeName = $this->getBakeName('TheDiff');
        $result = $this->Task->bake($bakeName);

        $this->assertCorrectSnapshot($bakeName, $result);
    }

    /**
     * Get the baked filename based on the current db environment
     *
     * @param string $name Name of the baked file, unaware of the DB environment
     * @return string Baked filename
     */
    public function getBakeName($name)
    {
        $name .= ucfirst(getenv("DB"));

        return $name;
    }

    /**
     * Gets a Migrations object in order to easily create and drop tables during the
     * tests
     *
     * @return \Migrations\Migrations
     */
    protected function getMigrations()
    {
        $params = [
            'connection' => 'test_comparisons',
            'source' => 'MigrationsDiff'
        ];
        $migrations = new Migrations($params);

        return $migrations;
    }

    /**
     * Assert that the $result matches the content of the baked file
     *
     * @param string $bakeName Name of the file to compare to the test
     * @param string $result Results generated by the test to be compared
     * @return void
     */
    public function assertCorrectSnapshot($bakeName, $result)
    {
        $dbenv = getenv("DB");
        $bakeName = Inflector::underscore($bakeName);
        if (file_exists($this->_compareBasePath . $dbenv . DS . $bakeName . '.php')) {
            $this->assertSameAsFile($dbenv . DS . $bakeName . '.php', $result);
        } else {
            $this->assertSameAsFile($bakeName . '.php', $result);
        }
    }
}
