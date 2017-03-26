<?php

use PHPUnit\Framework\TestCase;

use BotBattle\Config;
use BotBattle\Data\Db;
use BotBattle\Services\UserService;

class UserServiceTest extends TestCase {

    private static $userService;

    public function setUp() {
        $config = new Config(include(__DIR__ . '/../../src/public/api/config.php'));
        $db = new Db(
            $config->get(Config::DB_HOST),
            $config->get(Config::DB_DATABASE),
            $config->get(Config::DB_USER),
            $config->get(Config::DB_PASS)
        );

        self::$userService = new UserService($db);
    }

    public function tearDown() {

    }

    public function testAddUser() {
        $user = self::$userService->addUser('UnitTestUser');

        $this->assertNotEquals(null, $user);
    }

    /**
    * @depends testAddUser
    */
    public function testLoginExisting() {
        $user = self::$userService->login('UnitTestUser');

        $this->assertNotEquals(null, $user);

        return $user;
    }

    /**
    * @depends testLoginExisting
    */
    public function testDeleteUser($user) {
        $result = self::$userService->deleteUser($user);

        $this->assertEquals(true, $result);
    }

    /**
    * @depends testDeleteUser
    */
    public function testLogin() {
        $user = self::$userService->login('UnitTestUser');

        $this->assertNotEquals(null, $user);

        self::$userService->deleteUser($user);
    }

}