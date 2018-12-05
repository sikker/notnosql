<?php

namespace Sikker\NotNoSQL;

use PHPUnit\Framework\TestCase;

class NotNoSQLTest extends TestCase
{

    private $notnosql;

    public function setUp()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->notnosql = new NotNoSQL($pdo);
    }

    public function testBasicToplevelIO()
    {
        $notnosql = $this->notnosql;
        $notnosql->put('foo', 'bar');
        $this->assertEquals('bar', $notnosql->get('foo'));
    }

    public function testBasicPathIO()
    {
        $notnosql = $this->notnosql;
        $notnosql->put('one.two.three.four', 'content');
        $this->assertEquals('content', $notnosql->get('one.two.three.four'));
        $this->assertEquals(['three' => ['four' => 'content']], $notnosql->get('one.two'));
    }

    public function testAddToArray()
    {
        $notnosql = $this->notnosql;
        $notnosql->put('an.array', []);
        $this->assertEmpty($notnosql->get('an.array'));
        $notnosql->add('an.array', 'one');
        $this->assertCount(1, $notnosql->get('an.array'));
        $notnosql->add('an.array', 'two');
        $this->assertCount(2, $notnosql->get('an.array'));
        $notnosql->delete('an.array.1');
        $this->assertCount(1, $notnosql->get('an.array'));
    }

    public function testAbsence()
    {
        $notnosql = $this->notnosql;
        $this->assertNull($notnosql->get('missingno'));
        $notnosql->put('this.isnotmissing', 'hello');
        $this->assertNull($notnosql->get('this.butthisis'));
    }

    public function testRemoval()
    {
        $notnosql = $this->notnosql;
        $notnosql->put('now.you.see.me', 'are you watching closely?');
        $this->assertNotNull($notnosql->get('now.you.see.me'));
        $notnosql->delete('now.you.see.me');
        $this->assertNull($notnosql->get('now.you.see.me'));
        $notnosql->put('now.you', []);
        $this->assertNotNull($notnosql->get('now.you'));
        $this->assertNull($notnosql->get('now.you.see'));
        $notnosql->delete('now');
        $this->assertNull($notnosql->get('now'));
        $this->assertNull($notnosql->get('now.you'));
    }
}
