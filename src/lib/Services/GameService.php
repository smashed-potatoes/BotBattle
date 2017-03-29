<?php namespace BotBattle\Services;

use BotBattle\Data\Db;

use BotBattle\Services\UserService;

use BotBattle\Models\Game;
use BotBattle\Models\Board;
use BotBattle\Models\Tile;
use BotBattle\Models\Player;
use BotBattle\Models\User;
use BotBattle\Models\Move;

/**
* Handles game interactions
* TODO: Refactor to separate responsiblities
*/
class GameService {
    private $db;
    private $userService;

    /**
    * Constructor
    * @param Db $db The database connection to use
    * @param UserService $userService The UserService to use when interacting with users
    */
    public function __construct(Db $db, UserService $userService) {
        $this->db = $db;
        $this->userService = $userService;
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
        $qry = "SELECT id FROM games 
                WHERE difficulty = :difficulty
                AND state = :state
                LIMIT 1";
        $params = [
            'difficulty' => $difficulty,
            'state' => Game::STATE_WAITING
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            return $this->getGame($row['id']);
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

        // Insert the game
        $qry = "INSERT INTO games (boards_id, difficulty, max_players, state, turn, length) VALUES (:boardId, :difficulty, :maxPlayers, 0, 0, :gameLength)";
        $params = [
            'boardId' => $board->getId(),
            'difficulty' => $difficulty,
            'maxPlayers' => $maxPlayers,
            'gameLength' => $gameLength
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

        // Players starts empty - users must join
        $players = [];

        return new Game($id, $board, $players, $difficulty, $maxPlayers, $gameLength);
    }

    /**
    * Save a game's state
    * @param Game $game The game to save
    *
    * @return bool Whether saving was successful
    */
    public function saveGame(Game $game) : bool {
        $qry = "UPDATE games SET state = :state, turn = :turn WHERE id = :id";
        $params = [
            'id' => $game->getId(),
            'state' => $game->state,
            'turn' => $game->turn
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
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

        $player = $this->createPlayer($game, $user, 0, 0);
        
        // Auto start when 2 players are in
        $game = $this->getGame($game->getId());
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
        // Put the users at the starting positions
        $horizontalCenter = floor($game->board->width / 2);
        $verticalCenter = floor($game->board->height / 2);
        $startingPositions = [
            [ 'x' => 0,                     'y' => $verticalCenter],
            [ 'x' => $game->board->width-1, 'y' => $verticalCenter],
            [ 'x' => $horizontalCenter,     'y' => 0],
            [ 'x' => $horizontalCenter,     'y' => $game->board->height-1]
        ];

        // Too many players
        if (count($game->players) > count($startingPositions)) {
            error_log("Cannot start game, too many players");
            return null;
        }

        // Position the players
        for ($i=0; $i < count($game->players); $i++) {
            $player = $game->players[$i];
            $player->x = $startingPositions[$i]['x'];
            $player->y = $startingPositions[$i]['y'];

            if (!$this->savePlayer($player)) {
                error_log("Cannot start game, error positioning player");
                return null;
            }
        }

        // Update the game's state
        $game->state = Game::STATE_RUNNING;
        if ($this->saveGame($game)) {
            return $game;
        }

        return null;
    }

    /**
    * Get a game
    * @param int $id The ID of the game
    *
    * @return Game|null The game with the given ID
    */
    public function getGame(int $id) { // : ?Game {
        $qry = "SELECT * FROM games WHERE id = :id";
        $params = [
            'id' => $id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            $board = $this->getBoard($row['boards_id']);
            $players = $this->getGamePlayers($id);

            return new Game($row['id'], $board, $players, $row['difficulty'], $row['max_players'], $row['length'], $row['state'], $row['turn']);
        }

        return null;
    }

    /**
    * Delete an existing game
    * Also deletes all history of the game (players, moves, the board)
    * @param Game $game The game to delete
    *
    * @return bool Whether deletion was succesful
    */
    public function deleteGame(Game $game) : bool {
        // Delete moves
        $qry = "DELETE FROM moves WHERE games_id = :gameId";
        $params = [
            'gameId' => $game->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        // Delete the board's tiles (needs to be done before players)
        if (!$this->deleteBoardTiles($game->board)) {
            return false;
        }

        // Delete players
        $qry = "DELETE FROM players WHERE games_id = :gameId";
        $params = [
            'gameId' => $game->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        // Delete game
        $qry = "DELETE FROM games WHERE id = :gameId";
        $params = [
            'gameId' => $game->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        // Delete board
        if (!$this->deleteBoard($game->board)) {
            return false;
        }

        return true;
    }

    /**
    * Get a board
    * @param int $id The ID of the board
    *
    * @return Board|null The board with the given ID
    */
    public function getBoard(int $id) { // : ?Board {
        $qry = "SELECT * FROM boards WHERE id = :id";
        $params = [
            'id' => $id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            $tiles = $this->getBoardTiles($id);

            return new Board($id, $row['width'], $row['height'], $tiles);
        }

        return null;
    }

    /**
    * Get a Player
    * @param int $id The ID of the player
    *
    * @return Player|null The Player
    */
    public function getPlayer(int $id) { // : ?Player {
        $qry = "SELECT * FROM players WHERE id = :id";
        $params = [
            'id' => $id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            $user = $this->userService->getUser($row['users_id']);

            return new Player($row['id'], $user, $row['x'], $row['y'], $row['health'], $row['points']);
        }

        return null;
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
        $qry = "SELECT * FROM moves WHERE games_id = :gameId AND players_id = :playerId AND turn = :turn";
        $params = [
            'gameId' => $game->getId(),
            'playerId'=> $player->getId(),
            'turn'=> $game->turn
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            // User has laready moved, return their previous move
            return new Move($row['id'], $player, $game->turn, $row['action']);
        }

        // Insert the move
        $qry = "INSERT INTO moves (games_id, players_id, turn, action) VALUES (:gameId, :playerId, :turn, :action)";
        $params = [
            'gameId' => $game->getId(),
            'playerId'=> $player->getId(),
            'turn'=> $game->turn,
            'action'=> $action
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();
        $move = new Move($id, $player, $game->turn, $action);

        // Check of the turn is done (all players have moved)
        $moves = $this->getTurnMoves($game);
        if (count($moves) === count($game->players)) {
            $this->advanceGame($game);
        }

        return $move;
    }

    /**
    * Get the moves for the current turn within a game
    * @param Game $game The game to get the moves for
    * @param int $turn The turn to get the moves for
    *
    * @return array The moves for the game's current turn
    */
    public function getTurnMoves(Game $game, int $turn = null) : array {
        // Check if all players have moved and update state
        $qry = "SELECT * FROM moves WHERE games_id = :gameId AND turn = :turn";
        $params = [
            'gameId' => $game->getId(),
            'turn' => ($turn !== null) ? $turn : $game->turn
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $moves = [];
        if ($rows = $result->statement->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach($rows as $row) {
                $player = $this->getPlayer($row['players_id']);
                $moves[] = new Move($row['id'], $player, $row['turn'], $row['action']);
            }
        }

        return $moves;
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
        $states[] = clone $game;
        for ($i=0; $i<=$turn; $i++) {
            $this->processTurn($game, $i);
            $states[] = unserialize(serialize($game));;
        }

        return $states;
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
            if (!$this->savePlayer($player)) {
                throw new \Exception('Error saving player');
            }
        }

        // Save the gold tiles
        foreach ($game->board->goldTiles as $goldTile) {
            if (!$this->saveTile($goldTile)) {
                throw new \Exception('Error saving tile');
            }
        }

        $this->saveGame($game);
    }

    /**
    * Process the games current turn
    * @param Game $game The game to process the turn for
    * @param int $turn The turn to process
    */
    private function processTurn(Game $game, $turn = null) {
        $moves = $this->getTurnMoves($game, $turn);

        // Move the players
        foreach ($moves as $move) {
            $player = $game->getPlayerById($move->player->getId());

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
                    && ($tile->player == null || $tile->player->getId() !== $player->getId()) ){
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
                    $player = $game->getPlayerById($goldTile->player->getId());
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
                    if ($goldTile->player !== null && $goldTile->player->getId() === $player->getId()) {
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

    /**
    * Create a new board
    * @param int $width The width of the board
    * @param int $height The height of the board
    * @param int $level The level to load
    *
    * @return Board The board that is created
    */
    private function createBoard(int $width, int $height, int $level) : Board {
        // Create new board
        $qry = "INSERT INTO boards (width, height) VALUES (:width, :height)";
        $params = [
            'width' => $width,
            'height'=> $height
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

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
                $tiles[] = $this->createTile($id, $x, $y, $type);
            }
        }

        return new Board($id, $width, $height, $tiles);
    }

    /**
    * Delete a board
    * @param Board $board The board to delete
    *
    * @return bool Whether deletion was successful
    */
    private function deleteBoard(Board $board) : bool {
        // Delete board
        $qry = "DELETE FROM boards WHERE id = :boardId";
        $params = [
            'boardId' => $board->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }

    /**
    * Create a tile
    * @param int $boardId The board to create the tile on
    * @param int $x The x coordinate of the tile
    * @param int $y The y coordinate of the tile
    *
    * @return Tile The tile that was created
    */
    private function createTile(int $boardId, int $x, int $y, int $type) : Tile {
        // Create new tile
        $qry = "INSERT INTO tiles (boards_id, x, y, type) VALUES (:boardId, :x, :y, :type)";
        $params = [
            'boardId' => $boardId,
            'x' => $x,
            'y' => $y,
            'type' => $type
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

        return new Tile($id, $x, $y, null, $type);
    }

    /**
    * Delete the tiles of a board
    * @param Board $board The board to delete the tiles for
    *
    * @return bool Whether deletion was successful
    */
    private function deleteBoardTiles(Board $board) : bool {
        // Delete Tiles
        $qry = "DELETE FROM tiles WHERE boards_id = :boardId";
        $params = [
            'boardId' => $board->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }

    /**
    * Save the state of a tile
    * @param Tile $tile The tile to be saved
    *
    * @return bool Whether saving was successful
    */
    private function saveTile(Tile $tile) : bool {
        // Create new tile
        $qry = "UPDATE tiles SET players_id = :playerId WHERE id = :id";
        $params = [
            'id' => $tile->getId(),
            'playerId' => ($tile->player !== null) ? $tile->player->getId() : null
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }

    /**
    * Get the tiles for a given board
    * @param int $boardId The ID of the board
    * 
    * @return array|null The tiles for the board
    */
    private function getBoardTiles(int $boardId) { // : ?array {
        $qry = "SELECT * FROM tiles WHERE boards_id = :boardId";
        $params = [
            'boardId' => $boardId
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($rows = $result->statement->fetchAll(\PDO::FETCH_ASSOC)) {
            $tiles = [];
            foreach($rows as $row) {
                $player = null;
                if ($row['players_id'] !== null) {
                    $player = $this->getPlayer($row['players_id']);
                }
                $tiles[] = new Tile($row['id'], $row['x'], $row['y'], $player, $row['type']);
            }
            return $tiles;
        }

        return null;
    }

    /**
    * Create a new player
    * @param Game $game The Game to create the player for
    * @param User $user The user to create the player for
    * @param int $x The x coordinate of the player
    * @param int $y The y coordinate of the player
    *
    * @return Player The player that is created
    */
    private function createPlayer(Game $game, User $user, int $x, int $y) : Player {
        // Create new Player
        $qry = "INSERT INTO players (games_id, users_id, x, y, health, points) VALUES (:gameId, :userId, :x, :y, :health, :points)";
        $params = [
            'gameId' => $game->getId(),
            'userId' => $user->getId(),
            'x' => $x,
            'y' => $y,
            'health' => 100,
            'points' => 0,
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

        return new Player($id, $user, $x, $y);
    }

    /**
    * Save the state of a player
    * @param Player $player The Player to save
    */
    public function savePlayer(Player $player) : bool {
        $qry = "UPDATE players SET x = :x, y = :y, health = :health, points = :points WHERE id = :id";
        $params = [
            'id' => $player->getId(),
            'x' => $player->x,
            'y' => $player->y,
            'health' => $player->health,
            'points' => $player->points
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }

    /**
    * Get the players for a game
    * @param int $gameId The ID of the game to get the players for
    *
    * @return array The players for the game
    */
    private function getGamePlayers(int $gameId) : array {
        $qry = "SELECT * FROM players WHERE games_id = :gameId";
        $params = [
            'gameId' => $gameId
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $players = array();
        if ($rows = $result->statement->fetchAll(\PDO::FETCH_ASSOC)) {
            $players = [];
            foreach($rows as $row) {
                $user = $this->userService->getUser($row['users_id']);
                $players[] = new Player($row['id'], $user, $row['x'], $row['y'], $row['health'], $row['points']);
            }
        }

        return $players;
    }
}