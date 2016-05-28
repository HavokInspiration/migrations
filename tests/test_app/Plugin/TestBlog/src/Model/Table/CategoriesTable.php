<?php
namespace TestBlog\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;

/**
 * Articles Model
 *
 */
class CategoriesTable extends Table
{
    public function initialize(array $config)
    {
        $this->table('cakephp_test.categories');
    }
}
