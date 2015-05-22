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
namespace Migrations\Test\Command;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\MigrationsDispatcher;
use Phinx\Migration\Manager\Environment;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Output\StreamOutput;
use Phinx\Config\Config;

/**
 * MigrateTest class
 */
class MigrateTest extends TestCase
{

    /**
     * Instance of a Symfony Command object
     *
     * @var \Symfony\Component\Console\Command\Command
     */
    protected $command;

    /**
     * Instance of a Phinx Config object
     *
     * @var \Phinx\Config\Config
     */
    protected $config = [];

    /**
     * Instance of a Cake Connection object
     *
     * @var \Cake\Database\Connection
     */
    protected $Connection;

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->Connection = ConnectionManager::get('test');
        $connectionConfig = $this->Connection->config();
        $this->Connection->execute('DROP TABLE IF EXISTS phinxlog');

        $this->config = new Config([
            'paths' => [
                'migrations' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'cakephp_test',
                'default' => [
                    'adapter' => getenv('DB'),
                    'host' => '127.0.0.1',
                    'name' => !empty($connectionConfig['database']) ? $connectionConfig['database'] : '',
                    'user' => !empty($connectionConfig['username']) ? $connectionConfig['username'] : '',
                    'pass' => !empty($connectionConfig['password']) ? $connectionConfig['password'] : ''
                ]
            ]
        ]);

        $application = new MigrationsDispatcher('testing');
        $output = new StreamOutput(fopen('php://memory', 'a', false));

        $this->command = $application->find('migrate');

        $Environment = new Environment('default', $this->config['environments']['default']);

        $Manager = $this->getMock('\Phinx\Migration\Manager', [], [$this->config, $output]);
        $Manager->expects($this->any())
            ->method('getEnvironment')
            ->will($this->returnValue($Environment));

        $this->command->setManager($Manager);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        unset($this->Connection, $this->config, $this->command);
    }

    /**
     * Test executing a "migrate" command with the collation option will add it to
     * the environment
     *
     * @return void
     */
    public function testExecuteCollation()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test',
            '--collation' => 'latin1_swedish_ci',
        ]);

        $this->assertEquals('latin1_swedish_ci', $this->command->getConfig()->getEnvironment('default')['collation']);
    }

    /**
     * Test executing a "migrate" command with a Configure'd collation will add it to
     * the environment
     *
     * @return void
     */
    public function testExecuteCollationInConfigure()
    {
        Configure::write('Migrations.collation', 'latin1_swedish_ci');
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test'
        ]);

        $this->assertEquals('latin1_swedish_ci', $this->command->getConfig()->getEnvironment('default')['collation']);

    }

    /**
     * Test executing a "migrate" command with a Configure'd collation will add it to
     * the environment
     *
     * @return void
     */
    public function testExecuteNoCollation()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'command' => $this->command->getName(),
            '--connection' => 'test'
        ]);

        $defaultConfig = $this->command->getConfig()->getEnvironment('default');
        $this->assertTrue(!isset($defaultConfig['collation']));
    }
}