<?php

use PHPUnit\Framework\TestCase;

use BotBattle\Config;
use BotBattle\BotBattle;

use BotBattle\Models\Board;
use BotBattle\Models\Game;
use BotBattle\Models\Move;
use BotBattle\Models\Player;
use BotBattle\Models\Tile;
use BotBattle\Models\User;

class BotBattleTest extends TestCase {

    private static $botBattle;

    public function setUp() {
        $config = new Config(include(__DIR__ . '/../src/public/api/config.php'));
        self::$botBattle = new BotBattle($config);
    }

    public function testLogin() {
        $user = self::$botBattle->login("BotBattleTestUser");

        $this->assertNotEquals(null, $user);
        return $user;
    }

    public function testCreateGame() {
        $game = self::$botBattle->createGame(0);

        $this->assertNotEquals(null, $game);
        return $game;
    }

    /**
    * @depends testLogin
    */
    public function testGetCurrentUser(User $user) {
        $currentUser = self::$botBattle->getCurrentUser();

        $this->assertEquals($user, $currentUser);
    }

    /**
    * @depends testLogin
    */
    public function testGetUser(User $user) {
        $getUser = self::$botBattle->getUser($user->id);

        $this->assertEquals($user, $getUser);
    }

    /**
    * @depends testCreateGame
    */
    public function testGetGame(Game $game) {
        $getGame = self::$botBattle->getGame($game->id);

        $this->assertNotEquals(null, $getGame);
    }


    /**
    * @depends testCreateGame
    * @depends testLogin
    */
    public function testJoinGame(Game $game, User $user) {
        $player = self::$botBattle->joinGame($game, $user);

        $this->assertNotEquals(null, $player);
        $this->assertEquals($user, $player->user);
        $this->assertEquals(1, count($game->players));

        return $player;
    }

    /**
    * @depends testCreateGame
    * @depends testJoinGame
    */
    public function testMakeMove(Game $game, Player $player) {
        $move = self::$botBattle->makeMove($game, $player, 2);

        $this->assertNotEquals(null, $move);
        $this->assertEquals(2, $move->action);

        return $move;
    }

    /**
    * @depends testCreateGame
    * @depends testJoinGame
    * @depends testMakeMove
    */
    public function testGetTurnState(Game $game) {
        $gameState = self::$botBattle->getTurnState($game, 0);

        $this->assertNotEquals(null, $gameState);
    }

    /**
    * @depends testCreateGame
    * @depends testJoinGame
    * @depends testMakeMove
    */
    public function testGetGameStates(Game $game) {
        $gameStates = self::$botBattle->getGameStates($game);

        $this->assertEquals(2, count($gameStates));
    }

    public function testRunGame() {
        $game = self::$botBattle->createGame(2);

        $userA = self::$botBattle->login("BotBattleTestUserA");
        $userB = self::$botBattle->login("BotBattleTestUserB");
        
        $playerA = self::$botBattle->joinGame($game, $userA);
        $playerB = self::$botBattle->joinGame($game, $userB);

        $this->assertEquals(Game::STATE_RUNNING, $game->state);
        $actions = [
            Move::ACTION_NONE => 0,
            Move::ACTION_LEFT => 1,
            Move::ACTION_RIGHT => 2,
            Move::ACTION_UP => 3,
            Move::ACTION_DOWN => 4
        ];

        for ($i=0; $i<$game->length; $i++) {
            $move = self::$botBattle->makeMove($game, $playerA, $actions[array_rand($actions)]);
            $move = self::$botBattle->makeMove($game, $playerB, $actions[array_rand($actions)]);
        }


        $this->assertEquals(Game::STATE_DONE, $game->state);
    }


}