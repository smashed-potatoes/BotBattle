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
        // First check for an existing game with only one player and the same difficulty
        $qry = "SELECT games_id FROM (
                    SELECT games_id, count(players.id) as count
                    FROM players
                    GROUP BY games_id
                ) as game_players
                WHERE count = 1
                LIMIT 1";
        $result = $this->db->query($qry);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            return $this->getGame($row['games_id']);
        }

        // Insert the board
        // TODO: Legnth of game and size  by difficulty?
        $gameLength = 500;
        $board = $this->createBoard(11, 11);

        // Insert the game
        $qry = "INSERT INTO games (boards_id, difficulty, state, turn, length) VALUES (:boardId, :difficulty, 0, 0, :gameLength)";
        $params = [
            'boardId' => $board->getId(),
            'difficulty' => $difficulty,
            'gameLength' => $gameLength
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

        // Players starts empty - users must join
        $players = [];

        return new Game($id, $board, $players, $difficulty, 500);
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
    public function joinGame(Game $game, User $user) : Player {
        $player = $this->createPlayer($game, $user, 0, 0);
        
        // Auto start when 2 players are in
        $game = $this->getGame($game->getId());
        if (count($game->players) > 1) {
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

            $tile = $game->board->getTileAt($player->x, $player->y);
            $tile->player = $player;

            if (!$this->saveTile($tile)) {
                error_log("Cannot start game, error updating player tile");
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

            return new Game($row['id'], $board, $players, $row['difficulty'], $row['length'], $row['state'], $row['turn']);
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

            return new Player($row['id'], $user, $row['x'], $row['y']);
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
        switch ($action) {
            case Move::ACTION_LEFT:
                // Cannot move further left, default to none
                if ($player->x === 0) {
                    $action = Move::ACTION_NONE;
                }
                break;
            case Move::ACTION_RIGHT:
                if ($player->x === $game->board->width-1) {
                    $action = Move::ACTION_NONE;
                }
                break;
            case Move::ACTION_UP:
                if ($player->y === 0) {
                    $action = Move::ACTION_NONE;
                }
                break;
            case Move::ACTION_DOWN:
                if ($player->y === $game->board->height-1) {
                    $action = Move::ACTION_NONE;
                }
                break;
            case Move::ACTION_NONE:
            default:
                // No update
                break;
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

        // Check of the turn is done
        $this->processTurn($game);

        return $move;
    }

    /**
    * Get the moves for the current turn within a game
    * @param Game $game The game to get the moves for
    *
    * @return array The moves for the game's current turn
    */
    public function getTurnMoves(Game $game) : array {
        // Check if all players have moved and update state
        $qry = "SELECT * FROM moves WHERE games_id = :gameId AND turn = :turn";
        $params = [
            'gameId' => $game->getId(),
            'turn' => $game->turn
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
    * Process the games current turn
    * If there are players that haven't moved, nothing will be done
    * @param Game $game The game to process the turn for
    */
    private function processTurn(Game $game) {
        $moves = $this->getTurnMoves($game);

        if (count($moves) !== count($game->players)) {
            // Some players haven't made moves yet
            return;
        }

        // Move the players
        foreach ($moves as $move) {
            switch ($move->action) {
                case Move::ACTION_LEFT:
                    $move->player->x--;
                    break;
                case Move::ACTION_RIGHT:
                    $move->player->x++;
                    break;
                case Move::ACTION_UP:
                    $move->player->y--;
                    break;
                case Move::ACTION_DOWN:
                    $move->player->y++;
                    break;
                case Move::ACTION_NONE:
                default:
                    // No update
                    break;
            }
            if (!$this->savePlayer($move->player)) {
                throw new \Exception('Error saving player');
            }
        }

        // Get updated game state
        $game = $this->getGame($game->getId());

        // Update tile ownership
        foreach ($game->players as $player) {
            $alone = true;
            foreach ($game->players as $otherPlayer) {
                // Ignore self
                if ($player === $otherPlayer) continue;

                // Sharing a space with another player
                if ($player->x === $otherPlayer->x && $player->y === $otherPlayer->y) {
                    $alone = false;
                    // TODO: Any consequence?
                }
            }

            // When alone on a tile, take control of it
            if ($alone) {
                $tile = $game->board->getTileAt($player->x, $player->y);
                $tile->player = $player;

                $this->saveTile($tile);
            }
        }

        $game->turn++;

        if ($game->turn === $game->length) {
            $game->state = Game::STATE_DONE;
        }

        $this->saveGame($game);
    }

    /**
    * Create a new board
    * @param int $width The width of the board
    * @param int $height The height of the board
    *
    * @return Board The board that is created
    */
    private function createBoard(int $width, int $height) : Board {
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
                $tiles[] = $this->createTile($id, $x, $y);
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
    private function createTile(int $boardId, int $x, int $y) : Tile {
        // Create new tile
        $qry = "INSERT INTO tiles (boards_id, x, y) VALUES (:boardId, :x, :y)";
        $params = [
            'boardId' => $boardId,
            'x' => $x,
            'y' => $y
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $id = $this->db->insertId();

        return new Tile($id, $x, $y);
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
                $tiles[] = new Tile($row['id'], $row['x'], $row['y'], $player);
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
        $qry = "INSERT INTO players (games_id, users_id, x, y) VALUES (:gameId, :userId, :x, :y)";
        $params = [
            'gameId' => $game->getId(),
            'userId' => $user->getId(),
            'x' => $x,
            'y' => $y
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
        $qry = "UPDATE players SET x = :x, y = :y  WHERE id = :id";
        $params = [
            'id' => $player->getId(),
            'x' => $player->x,
            'y' => $player->y
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
                $players[] = new Player($row['id'], $user, $row['x'], $row['y']);
            }
        }

        return $players;
    }
}