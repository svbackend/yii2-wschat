<?php
namespace tests\codeception\unit;

use yii\codeception\TestCase;
use svbackend\wschat\components\AbstractStorage;

class AbstractStorageTest extends TestCase
{
    /**
     * @covers \svbackend\wschat\components\AbstractStorage::factory
     */
    public function testMongoStorage()
    {
        $storage = AbstractStorage::factory('mongodb');
        $this->assertInstanceOf('\svbackend\wschat\collections\History', $storage);
        $storage = AbstractStorage::factory();
        $this->assertInstanceOf('\svbackend\wschat\collections\History', $storage);
    }

    /**
     * @covers \svbackend\wschat\components\AbstractStorage::factory
     */
    public function testPgsqlStorage()
    {
        $storage = AbstractStorage::factory('pgsql');
        $this->assertInstanceOf('\svbackend\wschat\components\DbStorage', $storage);
    }

    /**
     * @covers \svbackend\wschat\components\AbstractStorage::factory
     */
    public function testMysqlStorage()
    {
        $storage = AbstractStorage::factory('mysql');
        $this->assertInstanceOf('\svbackend\wschat\components\DbStorage', $storage);
    }
}