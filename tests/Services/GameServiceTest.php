<?php

use PHPUnit\Framework\TestCase;

use BotBattle\Config;
use BotBattle\Data\Db;
use BotBattle\Services\UserService;
use BotBattle\Services\GameService;
use BotBattle\Models\Game;
use BotBattle\Models\Move;

class GameServiceTest extends TestCase {

    private static $userService;
    private static $gameService;
    private static $user;

    public function setUp() {
        $config = new Config(include(__DIR__ . '/../../src/public/api/config.php'));
        $db = new Db(
            $config->get(Config::DB_HOST),
            $config->get(Config::DB_DATABASE),
            $config->get(Config::DB_USER),
            $config->get(Config::DB_PASS)
        );

        self::$userService = new UserService($db);
        self::$user = self::$userService->addUser('UnitTestUser');

        self::$gameService = new GameService($db, self::$userService);
    }

    public function tearDown() {
        self::$userService->deleteUser(self::$user);
    }

    public function testCreateGame() {
        $game = self::$gameService->createGame(0);

        $this->assertNotEquals(null, $game);
        $this->assertEquals(0, $game->difficulty);

        return $game;
    }

    /**
    * @depends testCreateGame
    */
    public function testJoinGame($game) {
        $user = self::$userService->addUser('UnitTestUser-Join');
        $player = self::$gameService->joinGame($game, $user);

        $this->assertNotEquals(null, $player);

        return $player;
    }

    /**
    * @depends testCreateGame
    */
    public function testGetGame($game) {
        $game = self::$gameService->getGame($game->getId());

        $this->assertNotEquals(null, $game);
    }

    /**
    * @depends testCreateGame
    */
    public function testGetBoard($game) {
        $board = self::$gameService->getBoard($game->board->getId());

        $this->assertNotEquals(null, $board);
    }

    /**
    * @depends testJoinGame
    */
    public function testGetPlayer($player) {
        $player = self::$gameService->getPlayer($player->getId());

        $this->assertNotEquals(null, $player);

        return $player;
    }

    /**
    * @depends testCreateGame
    * @depends testGetPlayer
    * @depends testGetBoard
    */
    public function testDeleteGame($game, $player) {
        $result = self::$gameService->deleteGame($game);
        $this->assertEquals(true, $result);

        $result = self::$userService->deleteUser($player->user);
        $this->assertEquals(true, $result);
    }

    public function testPlayGame() {
        $game = self::$gameService->createGame(0);
        $userA = self::$userService->addUser('UnitTestUser-A');
        $userB = self::$userService->addUser('UnitTestUser-B');
        $playerA = self::$gameService->joinGame($game, $userA);
        $playerB = self::$gameService->joinGame($game, $userB);

        $playerA = self::$gameService->getPlayer($playerA->getId());
        $playerB = self::$gameService->getPlayer($playerB->getId());
        $game = self::$gameService->getGame($game->getId());

        $this->assertEquals(Game::STATE_RUNNING, $game->state);

        $actions = [
            Move::ACTION_NONE => 0,
            Move::ACTION_LEFT => 1,
            Move::ACTION_RIGHT => 2,
            Move::ACTION_UP => 3,
            Move::ACTION_DOWN => 4
        ];

        for ($i=0; $i<$game->length; $i++) {
            $move = self::$gameService->makeMove($game, $playerA, $actions[array_rand($actions)]);
            $move = self::$gameService->makeMove($game, $playerB, $actions[array_rand($actions)]);

            $playerA = self::$gameService->getPlayer($playerA->getId());
            $playerB = self::$gameService->getPlayer($playerB->getId());
            $game = self::$gameService->getGame($game->getId());
        }

        $this->assertEquals(Game::STATE_DONE, $game->state);

        // Delete the game
        $result = self::$gameService->deleteGame($game);
        $this->assertEquals(true, $result);

        // Cleanup the users
        $result = self::$userService->deleteUser($userA);
        $this->assertEquals(true, $result);
        $result = self::$userService->deleteUser($userB);
        $this->assertEquals(true, $result);
    }

}