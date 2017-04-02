<?php

use PHPUnit\Framework\TestCase;

use BotBattle\Config;

use BotBattle\Data\Db;

use BotBattle\Models\Board;
use BotBattle\Models\Game;
use BotBattle\Models\Move;
use BotBattle\Models\Player;
use BotBattle\Models\Tile;
use BotBattle\Models\User;

use BotBattle\Stores\BoardStore;
use BotBattle\Stores\GameStore;
use BotBattle\Stores\MoveStore;
use BotBattle\Stores\PlayerStore;
use BotBattle\Stores\TileStore;
use BotBattle\Stores\UserStore;

class StoresTest extends TestCase {

    private static $boardStore;
    private static $gameStore;
    private static $moveStore;
    private static $playerStore;
    private static $tileStore;
    private static $userStore;

    public function setUp() {
        $config = new Config(include(__DIR__ . '/../src/public/api/config.php'));
        $db = new Db(
            $config->get(Config::DB_HOST),
            $config->get(Config::DB_DATABASE),
            $config->get(Config::DB_USER),
            $config->get(Config::DB_PASS)
        );

        self::$userStore = new UserStore($db);
        self::$playerStore = new PlayerStore($db, self::$userStore);
        self::$moveStore = new MoveStore($db, self::$playerStore);
        self::$tileStore = new TileStore($db, self::$playerStore);
        self::$boardStore = new BoardStore($db, self::$tileStore);
        self::$gameStore = new GameStore($db, self::$boardStore, self::$playerStore);
    }

    public function testCreateUser() {
        $user = new User(-1, 'UnitTestUser');
        $result = self::$userStore->store($user);

        $this->assertEquals(true, $result);
        $this->assertNotEquals(-1, $user->id);

        return $user;
    }

    public function testCreateBoard() {
        $board = new Board(-1, 11, 11, []);
        $result = self::$boardStore->store($board);

        $this->assertEquals(true, $result);
        $this->assertNotEquals(-1, $board->id);

        return $board;
    }

    /**
    * @depends testCreateBoard
    */
    public function testCreateTiles(Board $board) {
        $tiles = [];
        for ($x=0; $x<$board->width; $x++) {
            for ($y=0; $y<$board->height; $y++) {
                $tile = new Tile(-1, $board->id, $x, $y, null, Tile::TYPE_GROUND);
                $result = self::$tileStore->store($tile);
                $tiles[] = $tile;

                $this->assertEquals(true, $result);
                $this->assertNotEquals(-1, $tile->id);
            }
        }
        $board->setTiles($tiles);

        return $board;
    }

    /**
    * @depends testCreateTiles
    */
    public function testCreateGame(Board $board) {
        $game = new Game(-1, $board, [], 0, 1, 500, 0, 0);
        $result = self::$gameStore->store($game);

        $this->assertEquals(true, $result);
        $this->assertNotEquals(-1, $game->id);

        return $game;
    }

    /**
    * @depends testCreateUser
    * @depends testCreateGame
    */
    public function testCreatePlayer(User $user, Game $game) {
        $player = new Player(-1, $game->id, $user, 5, 10, 50, 0);
        $result = self::$playerStore->store($player);

        $game->players[] = $player;

        $this->assertEquals(true, $result);
        $this->assertNotEquals(-1, $player->id);
    }


    /**
    * @depends testCreateGame
    * @depends testCreatePlayer
    */
    public function testRemoveGame(Game $game) {
        $result = self::$gameStore->remove($game);
        $this->assertEquals(true, $result);

        $removedGame = self::$gameStore->get($game->id);
        $this->assertEquals(null, $removedGame);

        return $game;
    }

    /**
    * @depends testRemoveGame
    */
    public function testRemoveBoard(Game $game) {
        $result = self::$boardStore->remove($game->board);
        $this->assertEquals(true, $result);

        $removedBoard = self::$boardStore->get($game->board->id);
        $this->assertEquals(null, $removedBoard);
    }


    /**
    * @depends testCreateUser
    */
    public function testRemoveUser(User $user) {
        $result = self::$userStore->remove($user);
        $this->assertEquals(true, $result);

        $removedUser = self::$userStore->get($user->id);
        $this->assertEquals(null, $removedUser);
    }
}