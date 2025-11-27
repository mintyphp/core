<?php

namespace MintyPHP\Tests\Core;

use MintyPHP\Core\DB;

class DBTest extends \PHPUnit\Framework\TestCase
{
    private static ?DB $db = null;

    public static function setUpBeforeClass(): void
    {
        self::$db = new DB(null, 'mintyphp_test', 'mintyphp_test', 'mintyphp_test', null, null);
    }

    public function testDropPostsBefore(): void
    {
        assert(self::$db !== null);
        $result = self::$db->query('DROP TABLE IF EXISTS `posts`;');
        $this->assertNotFalse($result, 'drop posts failed');
    }

    public function testDropUsersBefore(): void
    {
        assert(self::$db !== null);
        $result = self::$db->query('DROP TABLE IF EXISTS `users`;');
        $this->assertNotFalse($result, 'drop users failed');
    }

    public function testCreateUsers(): void
    {
        assert(self::$db !== null);
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

    public function testCreatePosts(): void
    {
        assert(self::$db !== null);
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

    public function testInsertUsers(): void
    {
        assert(self::$db !== null);
        $result = self::$db->insert("INSERT INTO `users` (`id`, `username`, `password`, `created`) VALUES (NULL, 'test1', 'c32ac6310706acdadea74c901c3f08fe06c44c61', '2014-05-28 22:58:22');");
        $this->assertNotFalse($result, 'insert user failed 1');
        $this->assertEquals(1, $result);
        $result = self::$db->insert("INSERT INTO `users` (`id`, `username`, `password`, `created`) VALUES (NULL, 'test2', 'c32ac6310706acdadea74c901c3f08fe06c44c61', '2014-05-28 22:58:22');");
        $this->assertNotFalse($result, 'insert user failed 2');
        $this->assertEquals(2, $result);
    }

    public function testInsertPosts(): void
    {
        assert(self::$db !== null);
        $result = self::$db->insert("INSERT INTO `posts` (`id`, `slug`, `tags`, `title`, `content`, `created`, `published`, `user_id`) VALUES (NULL, '2014-08-test1', '', 'test', 'test', '2014-05-28 22:58:22', NULL, 1);");
        $this->assertNotFalse($result, 'insert post failed 1');
        $this->assertEquals(1, $result);
        $result = self::$db->insert("INSERT INTO `posts` (`id`, `slug`, `tags`, `title`, `content`, `created`, `published`, `user_id`) VALUES (NULL, '2014-08-test2', '', 'test', 'test', '2014-05-28 22:58:22', NULL, 1);");
        $this->assertNotFalse($result, 'insert post failed 2');
        $this->assertEquals(2, $result);
    }

    public function testUpdatePosts(): void
    {
        assert(self::$db !== null);
        $result = self::$db->update("UPDATE `posts` SET `created`='2014-05-28 22:58:20' WHERE `id`=? OR `id` IN (???) OR `id`=? OR `id` IN (???) OR `id`=? OR `id` IN (???);", 1, [1, 2], 1, [1], 1, []);
        $this->assertNotFalse($result, 'update posts failed');
        $this->assertEquals(2, $result);
    }

    public function testSelectPosts(): void
    {
        assert(self::$db !== null);
        $result = self::$db->select("SELECT * FROM `posts`;");
        $this->assertEquals(2, count($result));
        $this->assertEquals('posts', array_keys($result[0])[0]);
        $this->assertEquals('id', array_keys($result[0]['posts'])[0]);
        $result = self::$db->select("SELECT * FROM `posts`, `users` WHERE posts.user_id = users.id and users.username = 'test1';");
        $this->assertEquals(2, count($result));
        $this->assertEquals(array('posts', 'users'), array_keys($result[0]));
        $this->assertEquals('id', array_keys($result[0]['posts'])[0]);
        $this->assertEquals('test1', $result[0]['users']['username']);
        $this->expectException('MintyPHP\DBError');
        $result = self::$db->select("some bogus query;");
    }

    public function testSelectOne(): void
    {
        assert(self::$db !== null);
        $result = self::$db->selectOne("SELECT * FROM `posts` limit 1;");
        $this->assertNotEquals(false, $result);
        assert($result !== false);
        $this->assertEquals('posts', array_keys($result)[0]);
        $this->assertEquals('id', array_keys($result['posts'])[0]);
        $result = self::$db->selectOne("SELECT * FROM `posts` WHERE slug like 'm%' limit 1;");
        $this->assertEquals([], $result);
        $this->expectException('MintyPHP\DBError');
        $result = self::$db->selectOne("some bogus query;");
    }

    public function testSelectValues(): void
    {
        assert(self::$db !== null);
        $result = self::$db->selectValues("SELECT username FROM `users`;");
        $this->assertEquals(array('test1', 'test2'), $result);
        $result = self::$db->selectValues("SELECT username FROM `users` WHERE username like 'm%' limit 1;");
        $this->assertEquals([], $result);
        $this->expectException('MintyPHP\DBError');
        $result = self::$db->selectValues("some bogus query;");
    }

    public function testSelectValue(): void
    {
        assert(self::$db !== null);
        $result = self::$db->selectValue("SELECT username FROM `users` limit 1;");
        $this->assertEquals('test1', $result);
        $result = self::$db->selectValue("SELECT username FROM `users` WHERE username like 'm%' limit 1;");
        $this->assertEquals(false, $result);
        $this->expectException('MintyPHP\DBError');
        $result = self::$db->selectValue("some bogus query;");
    }

    public function testQuery(): void
    {
        assert(self::$db !== null);
        $result = self::$db->query("SELECT * FROM `posts` limit 1;");
        $this->assertNotEquals(false, $result);
        $this->expectException('MintyPHP\DBError');
        $result = self::$db->query("some bogus query;");
    }

    public function testDeletePosts(): void
    {
        assert(self::$db !== null);
        $result = self::$db->delete('DELETE FROM `posts`;');
        $this->assertNotFalse($result, 'delete posts failed');
        $this->assertEquals(2, $result);
    }

    public function testDeleteUsers(): void
    {
        assert(self::$db !== null);
        $result = self::$db->delete('DELETE FROM `users`;');
        $this->assertNotFalse($result, 'delete users failed');
        $this->assertEquals(2, $result);
    }

    public function testDropPosts(): void
    {
        assert(self::$db !== null);
        $result = self::$db->query('DROP TABLE `posts`;');
        $this->assertNotFalse($result, 'drop posts failed');
    }

    public function testDropUsers(): void
    {
        assert(self::$db !== null);
        $result = self::$db->query('DROP TABLE `users`;');
        $this->assertNotFalse($result, 'drop users failed');
    }
}
