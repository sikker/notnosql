<?php

namespace Sikker\NotNoSQL;

use PHPUnit\Framework\TestCase;

class NotNoDbTest extends TestCase
{

    private $pdo;
    private $notnodb;

    public function setUp()
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->notnodb = new NotNoDb($this->pdo);
    }

    public function testCreateTable()
    {
        $this->pdo->query('INSERT INTO notnosql_data (key, value) VALUES ("keycontent", "value content")');
        $this->expectException(\PDOException::class);
        $this->pdo->query('INSERT INTO invalidtable (key, value) VALUES ("keycontent", "value content")');
    }

    public function testBasicIO()
    {
        $this->notnodb->statement('put', 'hello.there', 'my friend');
        $result = $this->pdo->query('SELECT `value` FROM notnosql_data WHERE `key` = "hello.there"')->fetchColumn();
        $this->assertEquals('my friend', json_decode($result));
        $this->assertEquals($result, $this->notnodb->statement('get', 'hello.there'));
    }

    public function testAbsence()
    {
        $this->assertNull($this->notnodb->statement('get', 'this.does.not.exist'));
        $this->notnodb->statement('put', 'this.exists', 'for now');
        $this->assertNotNull($this->notnodb->statement('get', 'this.exists'));
        $this->notnodb->statement('put', 'this.exists', null);
        $this->assertNull(json_decode($this->notnodb->statement('get', 'this.exists')));
    }
}
