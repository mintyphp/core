<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\DB;
use MintyPHP\Core\Orm;
use PHPUnit\Framework\TestCase;

/**
 * Note: These tests require a MySQL database named 'mintyphp_test
 * to be set up and accessible with the appropriate credentials.
 * Adjust the connection parameters in setUpBeforeClass() as needed.
 * These tests will create and drop tables in the database,
 * so ensure that it is safe to do so.
 */
class OrmTest extends TestCase
{
	private static DB $db;
	private static Orm $orm;

	public static function setUpBeforeClass(): void
	{
		// Create database connection
		self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);

		// Create Core ORM instance
		self::$orm = new Orm(self::$db);
	}

	public function testDropPostsBefore(): void
	{
		$result = self::$db->query('DROP TABLE IF EXISTS `posts`;');
		$this->assertNotFalse($result, 'drop posts failed');
	}

	/**
	 * @depends testDropPostsBefore
	 */
	public function testDropUsersBefore(): void
	{
		$result = self::$db->query('DROP TABLE IF EXISTS `users`;');
		$this->assertNotFalse($result, 'drop users failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 */
	public function testCreateUsers(): void
	{
		$result = self::$db->query('CREATE TABLE `users` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`username` varchar(255) COLLATE utf8_bin NOT NULL,
			`password` varchar(255) COLLATE utf8_bin NOT NULL,
			`created` datetime NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `username` (`username`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
		$this->assertNotFalse($result, 'create users failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 */
	public function testCreatePosts(): void
	{
		$result = self::$db->query('CREATE TABLE `posts` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`slug` varchar(255) COLLATE utf8_bin NOT NULL,
			`tags` varchar(255) COLLATE utf8_bin NOT NULL,
			`title` text COLLATE utf8_bin NOT NULL,
			`content` mediumtext COLLATE utf8_bin NOT NULL,
			`created` datetime NOT NULL,
			`published` datetime DEFAULT NULL,
			`user_id` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `slug` (`slug`),
			KEY `user_id` (`user_id`),
			CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
		$this->assertNotFalse($result, 'create posts failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 */
	public function testInsertUsers(): void
	{
		$result = self::$orm->insert('users', [
			'username' => 'test1',
			'password' => 'c32ac6310706acdadea74c901c3f08fe06c44c61',
			'created' => '2014-05-28 22:58:22'
		]);
		$this->assertNotFalse($result, 'insert user failed 1');
		$this->assertEquals(1, $result);
		$result = self::$orm->insert('users', [
			'username' => 'test2',
			'password' => 'c32ac6310706acdadea74c901c3f08fe06c44c61',
			'created' => '2014-05-28 22:58:22'
		]);
		$this->assertNotFalse($result, 'insert user failed 2');
		$this->assertEquals(2, $result);
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 */
	public function testInsertPosts(): void
	{
		$result = self::$orm->insert('posts', [
			'slug' => '2014-08-test1',
			'tags' => '',
			'title' => 'test',
			'content' => 'test',
			'created' => '2014-05-28 22:58:22',
			'published' => null,
			'user_id' => 1
		]);
		$this->assertNotFalse($result, 'insert post failed 1');
		$this->assertEquals(1, $result);
		$result = self::$orm->insert('posts', [
			'slug' => '2014-08-test2',
			'tags' => '',
			'title' => 'test',
			'content' => 'test',
			'created' => '2014-05-28 22:58:22',
			'published' => null,
			'user_id' => 1
		]);
		$this->assertNotFalse($result, 'insert post failed 2');
		$this->assertEquals(2, $result);
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 */
	public function testUpdatePosts(): void
	{
		$result = self::$orm->update('posts', ['created' => '2014-05-28 22:58:20'], 1);
		$this->assertTrue($result, 'update post 1 failed');
		$result = self::$orm->update('posts', ['created' => '2014-05-28 22:58:20'], 2);
		$this->assertTrue($result, 'update post 2 failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 */
	public function testSelectPosts(): void
	{
		$result = self::$orm->select('posts', 1);
		$this->assertNotEmpty($result);
		$this->assertEquals('1', $result['id']);
		$this->assertEquals('2014-08-test1', $result['slug']);

		$result = self::$orm->select('posts', 2);
		$this->assertNotEmpty($result);
		$this->assertEquals('2', $result['id']);
		$this->assertEquals('2014-08-test2', $result['slug']);
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 */
	public function testSelectPostNotFound(): void
	{
		$result = self::$orm->select('posts', 999);
		$this->assertEquals([], $result);
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 */
	public function testDeletePosts(): void
	{
		$result = self::$orm->delete('posts', 1);
		$this->assertTrue($result, 'delete post 1 failed');
		$result = self::$orm->delete('posts', 2);
		$this->assertTrue($result, 'delete post 2 failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 * @depends testDeletePosts
	 */
	public function testDeleteUsers(): void
	{
		$result = self::$orm->delete('users', 1);
		$this->assertTrue($result, 'delete user 1 failed');
		$result = self::$orm->delete('users', 2);
		$this->assertTrue($result, 'delete user 2 failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 * @depends testDeletePosts
	 * @depends testDeleteUsers
	 */
	public function testDropPosts(): void
	{
		$result = self::$db->query('DROP TABLE `posts`;');
		$this->assertNotFalse($result, 'drop posts failed');
	}

	/**
	 * @depends testDropPostsBefore
	 * @depends testDropUsersBefore
	 * @depends testCreateUsers
	 * @depends testCreatePosts
	 * @depends testInsertUsers
	 * @depends testInsertPosts
	 * @depends testUpdatePosts
	 * @depends testDeletePosts
	 * @depends testDeleteUsers
	 * @depends testDropPosts
	 */
	public function testDropUsers(): void
	{
		$result = self::$db->query('DROP TABLE `users`;');
		$this->assertNotFalse($result, 'drop users failed');
	}
}
