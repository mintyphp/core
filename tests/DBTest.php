<?php

namespace MintyPHP\Tests;

use MintyPHP\DB;
use MintyPHP\Core\DB as CoreDB;
use MintyPHP\DBError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the DB wrapper class (static facade)
 * 
 * Tests that the wrapper correctly delegates to the Core\DB instance
 */
class DBTest extends TestCase
{
    /** @var CoreDB&MockObject */
    private CoreDB $mockCoreDB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock of CoreDB
        $this->mockCoreDB = $this->createMock(CoreDB::class);

        // Reset the static instance before each test
        DB::setInstance($this->mockCoreDB);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset static properties using reflection
        $reflection = new \ReflectionClass(DB::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setValue(null, null);
    }

    public function testGetInstance(): void
    {
        $instance = DB::getInstance();
        $this->assertSame($this->mockCoreDB, $instance);
    }

    public function testSetInstance(): void
    {
        $newMock = $this->createMock(CoreDB::class);
        DB::setInstance($newMock);
        $this->assertSame($newMock, DB::getInstance());
    }

    public function testQuery(): void
    {
        $expectedResult = [
            ['users' => ['id' => 1, 'name' => 'John']],
            ['users' => ['id' => 2, 'name' => 'Jane']]
        ];

        $this->mockCoreDB->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM users WHERE id = ?', 1)
            ->willReturn($expectedResult);

        $result = DB::query('SELECT * FROM users WHERE id = ?', 1);
        $this->assertSame($expectedResult, $result);
    }

    public function testQueryWithMultipleParams(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM users WHERE id = ? AND name = ?', 1, 'John')
            ->willReturn([]);

        $result = DB::query('SELECT * FROM users WHERE id = ? AND name = ?', 1, 'John');
        $this->assertSame([], $result);
    }

    public function testQueryWithArrayParam(): void
    {
        $ids = [1, 2, 3];
        $this->mockCoreDB->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM users WHERE id IN (???)', $ids)
            ->willReturn([]);

        $result = DB::query('SELECT * FROM users WHERE id IN (???)', $ids);
        $this->assertSame([], $result);
    }

    public function testInsert(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('insert')
            ->with('INSERT INTO users (name, email) VALUES (?, ?)', 'John', 'john@example.com')
            ->willReturn(5);

        $result = DB::insert('INSERT INTO users (name, email) VALUES (?, ?)', 'John', 'john@example.com');
        $this->assertSame(5, $result);
    }

    public function testInsertReturnsZero(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('insert')
            ->with('INSERT INTO users (name) VALUES (?)', 'Test')
            ->willReturn(0);

        $result = DB::insert('INSERT INTO users (name) VALUES (?)', 'Test');
        $this->assertSame(0, $result);
    }

    public function testUpdate(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('update')
            ->with('UPDATE users SET name = ? WHERE id = ?', 'Jane', 3)
            ->willReturn(1);

        $result = DB::update('UPDATE users SET name = ? WHERE id = ?', 'Jane', 3);
        $this->assertSame(1, $result);
    }

    public function testUpdateMultipleRows(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('update')
            ->with('UPDATE users SET active = ?', 1)
            ->willReturn(10);

        $result = DB::update('UPDATE users SET active = ?', 1);
        $this->assertSame(10, $result);
    }

    public function testUpdateWithArrayParam(): void
    {
        $ids = [1, 2, 3];
        $this->mockCoreDB->expects($this->once())
            ->method('update')
            ->with('UPDATE users SET active = ? WHERE id IN (???)', 1, $ids)
            ->willReturn(3);

        $result = DB::update('UPDATE users SET active = ? WHERE id IN (???)', 1, $ids);
        $this->assertSame(3, $result);
    }

    public function testDelete(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('delete')
            ->with('DELETE FROM users WHERE id = ?', 5)
            ->willReturn(1);

        $result = DB::delete('DELETE FROM users WHERE id = ?', 5);
        $this->assertSame(1, $result);
    }

    public function testDeleteMultipleRows(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('delete')
            ->with('DELETE FROM users WHERE active = ?', 0)
            ->willReturn(3);

        $result = DB::delete('DELETE FROM users WHERE active = ?', 0);
        $this->assertSame(3, $result);
    }

    public function testDeleteWithArrayParam(): void
    {
        $ids = [1, 2, 3];
        $this->mockCoreDB->expects($this->once())
            ->method('delete')
            ->with('DELETE FROM users WHERE id IN (???)', $ids)
            ->willReturn(3);

        $result = DB::delete('DELETE FROM users WHERE id IN (???)', $ids);
        $this->assertSame(3, $result);
    }

    public function testSelect(): void
    {
        $expectedResult = [
            ['users' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']],
            ['users' => ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']]
        ];

        $this->mockCoreDB->expects($this->once())
            ->method('select')
            ->with('SELECT * FROM users')
            ->willReturn($expectedResult);

        $result = DB::select('SELECT * FROM users');
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectWithParams(): void
    {
        $expectedResult = [
            ['users' => ['id' => 1, 'name' => 'John']]
        ];

        $this->mockCoreDB->expects($this->once())
            ->method('select')
            ->with('SELECT * FROM users WHERE id = ?', 1)
            ->willReturn($expectedResult);

        $result = DB::select('SELECT * FROM users WHERE id = ?', 1);
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectWithMultipleTables(): void
    {
        $expectedResult = [
            [
                'users' => ['id' => 1, 'name' => 'John'],
                'posts' => ['id' => 100, 'title' => 'Hello World']
            ]
        ];

        $this->mockCoreDB->expects($this->once())
            ->method('select')
            ->with('SELECT * FROM users, posts WHERE users.id = posts.user_id')
            ->willReturn($expectedResult);

        $result = DB::select('SELECT * FROM users, posts WHERE users.id = posts.user_id');
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectOne(): void
    {
        $expectedResult = ['users' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']];

        $this->mockCoreDB->expects($this->once())
            ->method('selectOne')
            ->with('SELECT * FROM users WHERE id = ?', 1)
            ->willReturn($expectedResult);

        $result = DB::selectOne('SELECT * FROM users WHERE id = ?', 1);
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectOneReturnsFalse(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectOne')
            ->with('SELECT * FROM users WHERE id = ?', 999)
            ->willReturn(false);

        $result = DB::selectOne('SELECT * FROM users WHERE id = ?', 999);
        $this->assertFalse($result);
    }

    public function testSelectOneReturnsEmptyArray(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectOne')
            ->with('SELECT * FROM users WHERE name = ?', 'NonExistent')
            ->willReturn([]);

        $result = DB::selectOne('SELECT * FROM users WHERE name = ?', 'NonExistent');
        $this->assertSame([], $result);
    }

    public function testSelectValue(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValue')
            ->with('SELECT name FROM users WHERE id = ?', 1)
            ->willReturn('John');

        $result = DB::selectValue('SELECT name FROM users WHERE id = ?', 1);
        $this->assertSame('John', $result);
    }

    public function testSelectValueReturnsFalse(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValue')
            ->with('SELECT name FROM users WHERE id = ?', 999)
            ->willReturn(false);

        $result = DB::selectValue('SELECT name FROM users WHERE id = ?', 999);
        $this->assertFalse($result);
    }

    public function testSelectValueReturnsInteger(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValue')
            ->with('SELECT COUNT(*) FROM users')
            ->willReturn(42);

        $result = DB::selectValue('SELECT COUNT(*) FROM users');
        $this->assertSame(42, $result);
    }

    public function testSelectValues(): void
    {
        $expectedResult = ['John', 'Jane', 'Bob'];

        $this->mockCoreDB->expects($this->once())
            ->method('selectValues')
            ->with('SELECT name FROM users')
            ->willReturn($expectedResult);

        $result = DB::selectValues('SELECT name FROM users');
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectValuesWithParams(): void
    {
        $expectedResult = ['John'];

        $this->mockCoreDB->expects($this->once())
            ->method('selectValues')
            ->with('SELECT name FROM users WHERE active = ?', 1)
            ->willReturn($expectedResult);

        $result = DB::selectValues('SELECT name FROM users WHERE active = ?', 1);
        $this->assertSame($expectedResult, $result);
    }

    public function testSelectValuesReturnsEmpty(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValues')
            ->with('SELECT name FROM users WHERE active = ?', 0)
            ->willReturn([]);

        $result = DB::selectValues('SELECT name FROM users WHERE active = ?', 0);
        $this->assertSame([], $result);
    }

    public function testClose(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('close');

        DB::close();
    }

    public function testHandle(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('handle');

        DB::handle();
        // No assertion needed - we just verify the method was called
    }

    public function testQueryThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('query')
            ->with('INVALID SQL')
            ->willThrowException(new DBError('Syntax error'));

        $this->expectException(DBError::class);
        $this->expectExceptionMessage('Syntax error');

        DB::query('INVALID SQL');
    }

    public function testInsertThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('insert')
            ->with('INSERT INTO invalid_table VALUES (?)', 'test')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);
        $this->expectExceptionMessage('Table does not exist');

        DB::insert('INSERT INTO invalid_table VALUES (?)', 'test');
    }

    public function testUpdateThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('update')
            ->with('UPDATE invalid_table SET x = ?', 1)
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::update('UPDATE invalid_table SET x = ?', 1);
    }

    public function testDeleteThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('delete')
            ->with('DELETE FROM invalid_table')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::delete('DELETE FROM invalid_table');
    }

    public function testSelectThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('select')
            ->with('SELECT * FROM invalid_table')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::select('SELECT * FROM invalid_table');
    }

    public function testSelectOneThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectOne')
            ->with('SELECT * FROM invalid_table')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::selectOne('SELECT * FROM invalid_table');
    }

    public function testSelectValueThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValue')
            ->with('SELECT col FROM invalid_table')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::selectValue('SELECT col FROM invalid_table');
    }

    public function testSelectValuesThrowsDBError(): void
    {
        $this->mockCoreDB->expects($this->once())
            ->method('selectValues')
            ->with('SELECT col FROM invalid_table')
            ->willThrowException(new DBError('Table does not exist'));

        $this->expectException(DBError::class);

        DB::selectValues('SELECT col FROM invalid_table');
    }
}
