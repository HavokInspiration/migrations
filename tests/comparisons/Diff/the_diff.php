<?php
use Migrations\AbstractMigration;

class TheDiff extends AbstractMigration
{

    public function up()
    {
        $this->table('articles')
            ->dropForeignKey([], 'articles_ibfk_1')
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('rating_index')
            ->removeIndexByName('BY_NAME')
            ->update();

        $this->table('articles')
            ->removeColumn('content')
            ->changeColumn('title', 'text')
            ->changeColumn('name', 'string', [
                'length' => 50,
            ])
            ->update();

        $table = $this->table('categories');
        $table
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addIndex(
                [
                    'user_id',
                ]
            )
            ->addIndex(
                [
                    'name',
                ]
            )
            ->create();

        $this->table('categories')
            ->addForeignKey(
                'user_id',
                'users',
                'id',
                [
                    'update' => 'RESTRICT',
                    'delete' => 'RESTRICT'
                ]
            )
            ->update();

        $this->table('articles')
            ->addColumn('category_id', 'integer', [
                'default' => null,
                'length' => 11,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'UNIQUE_SLUG',
                ]
            )
            ->addIndex(
                [
                    'category_id',
                ],
                [
                    'name' => 'category_id',
                ]
            )
            ->addIndex(
                [
                    'name',
                ],
                [
                    'name' => 'rating_index',
                ]
            )
            ->update();

        $this->table('articles')
            ->addForeignKey(
                'category_id',
                'categories',
                'id',
                [
                    'update' => 'restrict',
                    'delete' => 'restrict'
                ]
            )
            ->update();

            $this->dropTable('tags');
    }


    public function down()
    {
        $this->table('categories')
            ->dropForeignKey(
                'user_id'
            );
        $this->table('articles')
            ->dropForeignKey(
                'category_id'
            );

            $table = $this->table('tags');
            $table
                ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->create();


        $this->table('articles')
            ->removeIndexByName('UNIQUE_SLUG')
            ->removeIndexByName('category_id')
            ->removeIndexByName('rating_index')
            ->update();

        $this->table('articles')
            ->addIndex(
                [
                    'slug',
                ],
                [
                    'name' => 'UNIQUE_SLUG',
                    'unique' => true,
                ]
            )
            ->addIndex(
                [
                    'rating',
                ],
                [
                    'name' => 'rating_index',
                ]
            )
            ->addIndex(
                [
                    'name',
                ],
                [
                    'name' => 'BY_NAME',
                ]
            )
            ->update();

        $this->table('articles')
            ->addColumn('content', 'text', [
                'default' => null,
                'length' => null,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->changeColumn('title', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->changeColumn('name', 'string', [
                'default' => null,
                'length' => 255,
                'null' => false,
            ])
            ->update();

        $this->table('articles')
            ->removeColumn('category_id')
            ->update();

        $this->dropTable('categories');
    }
}
