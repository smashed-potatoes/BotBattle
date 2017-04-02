<?php namespace BotBattle;

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

/**
* The BotBattle main entry point and game controller
*/
class BotBattle {

    private $config;
    private $db;

    private $boardStore;
    private $gameStore;
    private $moveStore;
    private $playerStore;
    private $tileStore;
    private $userStore;

    /**
    * Constructor
    * @param Config $config The configuration to use
    */
    public function __construct(Config $config){
        $this->config = $config;
        $this->db = new Db(
            $this->config->get(Config::DB_HOST),
            $this->config->get(Config::DB_DATABASE),
            $this->config->get(Config::DB_USER),
            $this->config->get(Config::DB_PASS)
        );

        $this->userStore    = new UserStore($this->db);
        $this->playerStore  = new PlayerStore($this->db, $this->userStore);
        $this->moveStore    = new MoveStore($this->db, $this->playerStore);
        $this->tileStore    = new TileStore($this->db, $this->playerStore);
        $this->boardStore   = new BoardStore($this->db, $this->tileStore);
        $this->gameStore    = new GameStore($this->db, $this->boardStore, $this->playerStore);
    }

    /**
    * Log in as a user
    * @param string $username The user to log in as
    *
    * @return User The user
    */
    public function login(string $username) { // : ?User
        $users = $this->userStore->getWhere(['username' => $username]);
        
        $user = null;
        if (count($users) === 0) {
            $user = new User(-1, $username);
            $this->userStore->store($user);
        }
        else {
            $user = $users[0];
        }

        $_SESSION['user'] = $user;

        return $user;
    }

    /**
    * Get the session's current user
    * @return User|null The session's current user
    */
    public function getCurrentUser() { // : ?User
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        else {
            return null;
        }
    }

    /**
    * Get a user
    * @param int $id The ID of the user
    *
    * @return User|null The user
    */
    public function getUser(int $id) { // : ?User
        return $this->userStore->get($id);
    }

    /**
    * Get a game
    * @param int $id The ID of the game
    *
    * @return Game|null The game with the given ID
    */
    public function getGame(int $id) { // : ?Game {
        return $this->gameStore->get($id);
    }

    /**
    * Gets the state of the game at a particular turn
    * @param Game $game The game to get the state for
    * @param int $turn The turn to get the state for
    *
    * @return Game The state of the game at the given turn
    */
    public function getTurnState(Game $game, int $turn) : Game {
        // Start from the beginning
        $this->resetGame($game);
        for ($i=0; $i<=$turn; $i++) {
            $this->processTurn($game, $i);
        }

        return $game;
    }

    /**
    * Gets the sequences of states for the game
    * @param Game $game The game to get all of the states for
    *
    * @return array An array of Games for each turn of the game
    */
    public function getGameStates(Game $game) {
        $turn = $game->turn;
        $states = [];
        // Start from the beginning
        $this->resetGame($game);
        $states[] = unserialize(serialize($game));
        for ($i=0; $i<$turn; $i++) {
            $this->processTurn($game, $i);
            $states[] = unserialize(serialize($game));
        }

        return $states;
    }


    /**
    * Create a new game (or join a matching existing one)
    * A game cannot be created if there is already one of the same difficulty waiting for players
    * @param int $difficulty The difficulty of the game
    *
    * @return Game The created or matching existing game
    */
    public function createGame(int $difficulty) : Game {
        // First check for an existing game that hasn't started
        $existingGames = $this->gameStore->getWhere([
            'state' => Game::STATE_WAITING,
            'difficulty' => $difficulty
        ]);
        if (count($existingGames) > 0) {
            return $existingGames[0];
        }

        // Insert the board
        // TODO: Load level from difficulty
        $maxPlayers = 1;
        if ($difficulty > 4) {
            $maxPlayers = 3;
        }
        elseif ($difficulty > 1) {
            $maxPlayers = 2;
        }
        $gameLength = 500;
        $board = $this->createBoard(11, 11, $difficulty);

        // Players starts empty - users must join
        $players = [];

        $game = new Game(-1, $board, $players, $difficulty, $maxPlayers, $gameLength);
        $this->gameStore->store($game);

        return $game;
    }

    /**
    * Have a user join a game
    * If the required number of users is met, the game is also started
    * @param Game $game The game to join
    * @param User $user The user to join the game
    *
    * @return Player The player for the user that joined
    */
    public function joinGame(Game $game, User $user) { //: ?Player {
        // Can only join when the game is waiting for players
        if ($game->state !== Game::STATE_WAITING) {
            return null;
        }

        $player = new Player(-1, $game->id, $user, 0, 0);
        $this->playerStore->store($player);

        $game->players[] = $player;
        $this->gameStore->store($game);
        
        // Auto start when 2 players are in
        if (count($game->players) === $game->maxPlayers) {
            $game = $this->startGame($game);
        }

        return $player;
    }

    /**
    * Start a game
    * Puts players in the starting position and sets the game state
    * @param Game $game The game to start
    *
    * @return Game The started game
    */
    public function startGame(Game $game) { // : ?Game {
        $this->resetGame($game);
        foreach ($game->players as $player) {
            $this->playerStore->store($player);
        }

        // Update the game's state
        $game->state = Game::STATE_RUNNING;
        $this->gameStore->store($game);

        return $game;
    }

    /**
    * Make a move within a game
    * If an invalid move (i.e. left while at x = 0) is made, Move::ACTION_NONE will be performed and stored
    * Note: The move is stored but the game isn't updated until all players have submitted their moves
    * @param Game $game The game to make the move in
    * @param Player $player The player making the move
    * @param int $action The action being performed
    *
    * @return Move The move that was made
    */
    public function makeMove(Game $game, Player $player, int $action) : Move {
        // Check if the player has already moved
        $existingMoves = $this->moveStore->getWhere([
            'games_id' => $game->id,
            'players_id' => $player->id,
            'turn' => $game->turn
        ]);

        // User has laready moved, return their previous move
        if (count($existingMoves) > 0) {
            return $existingMoves[0];
        }

        // Create new move
        $move = new Move(-1, $game->id, $player, $game->turn, $action);
        $this->moveStore->store($move);

        // Check of the turn is done (all players have moved)
        $turnMoves = $this->moveStore->getWhere([
            'games_id' => $game->id,
            'turn' => $game->turn
        ]);
        if (count($turnMoves) === count($game->players)) {
            $this->advanceGame($game);
        }

        return $move;
    }


    /*******
    * Private functions
    **/

    /**
    * Create a new board
    * @param int $width The width of the board
    * @param int $height The height of the board
    * @param int $level The level to load
    *
    * @return Board The board that is created
    */
    private function createBoard(int $width, int $height, int $level) : Board {
        $board = new Board(-1, $width, $height, []);
        $this->boardStore->store($board);

        // Create tiles
        $tiles = [];
        for ($x=0; $x<$width; $x++) {
            for ($y=0; $y<$height; $y++) {
                $type = Tile::TYPE_GROUND;

                // TODO: Load map
                if ($level == 0) {
                    // Put gold directly oposite
                    if ($x === $width - 1 &&$y == floor($height / 2)) {
                        $type = Tile::TYPE_GOLD;
                    }
                }
                elseif ($level == 1) {
                    // Put gold directly oposite
                    if ($x === $width - 1 &&$y == floor($height / 2)) {
                        $type = Tile::TYPE_GOLD;
                    }
                    // Put wall across the center
                    elseif ($x == floor($width / 2) && $y > 0 && $y < $height - 1) {
                        $type = Tile::TYPE_WALL;
                    }
                }
                elseif ($level == 2) {
                    // Put healing in the center
                    if ( ($x == floor($width / 2) + 1 && $y == floor($height / 2))
                        || ($x == floor($width / 2) - 1 && $y == floor($height / 2)) ) {
                        $type = Tile::TYPE_HEAL;
                    }
                    // Put gold in corners
                    elseif (($x === 0 && $y === 0)
                        || ($x === 0 && $y === $height - 1)
                        || ($y === 0 && $x === $width - 1)
                        || ($x === $width - 1 && $y === $height - 1)) {
                        $type = Tile::TYPE_GOLD;
                    }
                    // Put wall across the center
                    elseif (($x == floor($width / 2) && $y > 0 && $y < $height - 1)
                        || ($y == floor($height / 2) && $x > 0 && $x < $width - 1)) {
                        $type = Tile::TYPE_WALL;
                    }
                }
                elseif ($level == 3) {
                    // Put gold in the center
                    if ( ($x == floor($width / 2) + 1 && $y == floor($height / 2))
                        || ($x == floor($width / 2) - 1 && $y == floor($height / 2)) ) {
                        $type = Tile::TYPE_GOLD;
                    }
                    // Put healing in corners
                    elseif (($x === 0 && $y === 0)
                        || ($x === 0 && $y === $height - 1)
                        || ($y === 0 && $x === $width - 1)
                        || ($x === $width - 1 && $y === $height - 1)) {
                        $type = Tile::TYPE_HEAL;
                    }
                    // Put wall around the border, gaps along the sides
                    elseif ( (($x == 1 || $x == $width - 2) && ($y > 0 && $y < $height -1 && $y != floor($height / 2)))
                        || (($y == 1 || $y == $height - 2) && ($x > 0 && $x < $width -1 && $x != floor($width / 2))) ) {
                        $type = Tile::TYPE_WALL;
                    }
                }
                else {
                    // Put gold in the center
                    if ( ($x == floor($width / 2) + 1 && $y == floor($height / 2))
                        || ($x == floor($width / 2) - 1 && $y == floor($height / 2)) ) {
                        $type = Tile::TYPE_GOLD;
                    }
                    // Put healing in corners
                    elseif (($x === 0 && $y === 0)
                        || ($x === 0 && $y === $height - 1)
                        || ($y === 0 && $x === $width - 1)
                        || ($x === $width - 1 && $y === $height - 1)) {
                        $type = Tile::TYPE_HEAL;
                    }
                    // Put wall across the center
                    elseif (($x == floor($width / 2) && $y > 0 && $y < $height - 1)
                        || ($y == floor($height / 2) && $x > 0 && $x < $width - 1)) {
                        $type = Tile::TYPE_WALL;
                    }
                }

                $tile = new Tile(-1, $board->id, $x, $y, null, $type);
                $this->tileStore->store($tile);
                
                $tiles[] = $tile;
            }
        }

        $board->setTiles($tiles);
        return $board;
    }

    /**
    * Reset a game to its initial state
    * @param Game $game The game to reset
    */
    private function resetGame(Game $game) {
        $horizontalCenter = floor($game->board->width / 2);
        $verticalCenter = floor($game->board->height / 2);
        $startingPositions = [
            [ 'x' => 0,                     'y' => $verticalCenter],
            [ 'x' => $game->board->width-1, 'y' => $verticalCenter],
            [ 'x' => $horizontalCenter,     'y' => 0],
            [ 'x' => $horizontalCenter,     'y' => $game->board->height-1]
        ];

        // Position the players
        for ($i=0; $i < count($game->players); $i++) {
            $player = $game->players[$i];
            $player->health = 100;
            $player->points = 0;
            $player->x = $startingPositions[$i]['x'];
            $player->y = $startingPositions[$i]['y'];
        }

        // Reset the gold tiles
        foreach ($game->board->goldTiles as $goldTile) {
            $goldTile->player = null;
        }

        $game->turn = 0;
        $game->state = Game::STATE_RUNNING;
    }

    /**
    * Process the current turn and advance the game to the next turn
    * @param Game $game The game to advance
    */
    private function advanceGame(Game $game) {
        $this->processTurn($game);

        // Save the players
        foreach ($game->players as $player) {
            if (!$this->playerStore->store($player)) {
                throw new \Exception('Error saving player');
            }
        }

        // Save the gold tiles
        foreach ($game->board->goldTiles as $goldTile) {
            if (!$this->tileStore->store($goldTile)) {
                throw new \Exception('Error saving tile');
            }
        }

        $this->gameStore->store($game);
    }

    /**
    * Process the games current turn
    * @param Game $game The game to process the turn for
    * @param int $turn The turn to process
    */
    private function processTurn(Game $game, $turn = null) {
        $moves = $this->moveStore->getWhere([
            'games_id' => $game->id,
            'turn' => ($turn !== null) ? $turn : $game->turn
        ]);

        // Move the players
        foreach ($moves as $move) {
            $player = $game->getPlayerById($move->player->id);

            $x = $player->x;
            $y = $player->y;
            switch ($move->action) {
                case Move::ACTION_LEFT:
                    $x--;
                    break;
                case Move::ACTION_RIGHT:
                    $x++;
                    break;
                case Move::ACTION_UP:
                    $y--;
                    break;
                case Move::ACTION_DOWN:
                    $y++;
                    break;
                case Move::ACTION_NONE:
                default:
                    // No update
                    break;
            }

            // Constrain to the board
            if ($x < 0) $x = 0;
            if ($x > $game->board->width-1) $x = $game->board->width-1;
            if ($y < 0) $y = 0;
            if ($y > $game->board->height-1) $y = $game->board->height-1;

            // Check if the user can move into the tile
            $targetTile = $game->board->getTileAt($x, $y);
            if ($targetTile->type !== Tile::TYPE_WALL) {
                $player->x = $x;
                $player->y = $y;
            }
        }

        // Interact with tiles
        // TODO: Separate to per-level logic
        if ($game->difficulty < 2) {
            foreach ($game->players as $player) {
                $tile = $game->board->getTileAt($player->x, $player->y);

                if ($tile->type === Tile::TYPE_GOLD) {
                    $tile->player = $player;
                    $player->points = 100;
                    $game->state = Game::STATE_DONE;
                }
            }
        }
        else {
            foreach ($game->players as $player) {
                $alone = true;
                $tile = $game->board->getTileAt($player->x, $player->y);

                foreach ($game->players as $otherPlayer) {
                    // Ignore self
                    if ($player === $otherPlayer) continue;

                    // Sharing a space with another player
                    if ($player->x === $otherPlayer->x && $player->y === $otherPlayer->y) {
                        $alone = false;

                        // Can't attack while healing
                        if ($tile->type !== Tile::TYPE_HEAL){
                            $otherPlayer->health = $otherPlayer->health - 20;
                        }
                    }
                }

                // When alone on a gold tile, take control of it
                if ( $alone && $tile->type === Tile::TYPE_GOLD 
                    && ($tile->player == null || $tile->player->id !== $player->id) ){
                    $player->health -= 20;

                    if ($player->health > 0) {
                        $tile->player = $player;
                    }
                }
                // Heal on health tiles
                elseif ($tile->type === Tile::TYPE_HEAL) {
                    $player->health += 20;
                    $player->health = $player->health > 100 ? 100 : $player->health;
                }
            }

            // Update the player points
            foreach ($game->board->goldTiles as $goldTile) {
                if ($goldTile->player !== null) {
                    $player = $game->getPlayerById($goldTile->player->id);
                    $player->points++;
                }
            }
        }

        // Check for dead players
        foreach ($game->players as $player) {
            // Check if the player died
            if ($player->health <= 0) {
                // Remove user's gold tiles
                foreach ($game->board->goldTiles as $goldTile) {
                    if ($goldTile->player !== null && $goldTile->player->id === $player->id) {
                        $goldTile->player = null;
                    }
                }

                // Respawn at closest health tile
                $closest = null;
                foreach($game->board->healTiles as $healTile) {
                    if ($closest == null || (floor($closest->x - $player->x) + floor($closest->y - $player->y)) > (floor($healTile->x - $player->x) + floor($healTile->y - $player->y))) {
                        $closest = $healTile;
                    }
                }
                $player->health = 100;
                $player->x = $closest->x;
                $player->y = $closest->y;
            }
        }

        $game->turn++;

        if ($game->turn === $game->length) {
            $game->state = Game::STATE_DONE;
        }
    }
}