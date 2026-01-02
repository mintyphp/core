<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\DB;

/**
 * Note: These tests require a MySQL database named 'mintyphp_test
 * to be set up and accessible with the appropriate credentials.
 * Adjust the connection parameters in setUpBeforeClass() as needed.
 * These tests will create and drop tables in the database,
 * so ensure that it is safe to do so.
 */
class DBTest extends \PHPUnit\Framework\TestCase
{
    private static DB $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);
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
        $result = self::$db->insert("INSERT INTO `users` (`id`, `username`, `password`, `created`) VALUES (NULL, 'test1', 'c32ac6310706acdadea74c901c3f08fe06c44c61', '2014-05-28 22:58:22');");
        $this->assertNotFalse($result, 'insert user failed 1');
        $this->assertEquals(1, $result);
        $result = self::$db->insert("INSERT INTO `users` (`id`, `username`, `password`, `created`) VALUES (NULL, 'test2', 'c32ac6310706acdadea74c901c3f08fe06c44c61', '2014-05-28 22:58:22');");
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
        $result = self::$db->insert("INSERT INTO `posts` (`id`, `slug`, `tags`, `title`, `content`, `created`, `published`, `user_id`) VALUES (NULL, '2014-08-test1', '', 'test', 'test', '2014-05-28 22:58:22', NULL, 1);");
        $this->assertNotFalse($result, 'insert post failed 1');
        $this->assertEquals(1, $result);
        $result = self::$db->insert("INSERT INTO `posts` (`id`, `slug`, `tags`, `title`, `content`, `created`, `published`, `user_id`) VALUES (NULL, '2014-08-test2', '', 'test', 'test', '2014-05-28 22:58:22', NULL, 1);");
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
        $result = self::$db->update("UPDATE `posts` SET `created`='2014-05-28 22:58:20' WHERE `id`=? OR `id` IN (???) OR `id`=? OR `id` IN (???) OR `id`=? OR `id` IN (???);", 1, [1, 2], 1, [1], 1, []);
        $this->assertNotFalse($result, 'update posts failed');
        $this->assertEquals(2, $result);
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
        $result = self::$db->select("SELECT * FROM `posts`;");
        $this->assertEquals(2, count($result));
        $this->assertEquals('posts', array_keys($result[0])[0]);
        $this->assertEquals('id', array_keys($result[0]['posts'])[0]);
        $result = self::$db->select("SELECT * FROM `posts`, `users` WHERE posts.user_id = users.id and users.username = 'test1';");
        $this->assertEquals(2, count($result));
        $this->assertEquals(['posts', 'users'], array_keys($result[0]));
        $this->assertEquals('id', array_keys($result[0]['posts'])[0]);
        $this->assertEquals('test1', $result[0]['users']['username']);
        $this->expectException(\MintyPHP\Error\DBError::class);
        $result = self::$db->select("some bogus query;");
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
    public function testSelectOne(): void
    {
        $result = self::$db->selectOne("SELECT * FROM `posts` limit 1;");
        $this->assertNotEquals(false, $result);
        assert($result !== false);
        $this->assertEquals('posts', array_keys($result)[0]);
        $this->assertEquals('id', array_keys($result['posts'])[0]);
        $result = self::$db->selectOne("SELECT * FROM `posts` WHERE slug like 'm%' limit 1;");
        $this->assertEquals(false, $result);
        $this->expectException(\MintyPHP\Error\DBError::class);
        $result = self::$db->selectOne("some bogus query;");
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
    public function testSelectValues(): void
    {
        $result = self::$db->selectValues("SELECT username FROM `users`;");
        $this->assertEquals(['test1', 'test2'], $result);
        $result = self::$db->selectValues("SELECT username FROM `users` WHERE username like 'm%' limit 1;");
        $this->assertEquals([], $result);
        $this->expectException(\MintyPHP\Error\DBError::class);
        $result = self::$db->selectValues("some bogus query;");
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
    public function testSelectValue(): void
    {
        $result = self::$db->selectValue("SELECT username FROM `users` limit 1;");
        $this->assertEquals('test1', $result);
        $result = self::$db->selectValue("SELECT username FROM `users` WHERE username like 'm%' limit 1;");
        $this->assertEquals(false, $result);
        $this->expectException(\MintyPHP\Error\DBError::class);
        $result = self::$db->selectValue("some bogus query;");
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
    public function testQuery(): void
    {
        $result = self::$db->query("SELECT * FROM `posts` limit 1;");
        $this->assertNotEquals(false, $result);
        $this->expectException(\MintyPHP\Error\DBError::class);
        $result = self::$db->query("some bogus query;");
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
        $result = self::$db->delete('DELETE FROM `posts`;');
        $this->assertNotFalse($result, 'delete posts failed');
        $this->assertEquals(2, $result);
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
        $result = self::$db->delete('DELETE FROM `users`;');
        $this->assertNotFalse($result, 'delete users failed');
        $this->assertEquals(2, $result);
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
