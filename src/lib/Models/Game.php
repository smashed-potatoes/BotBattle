<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

use BotBattle\Models\Board;
use BotBattle\Models\Player;
use BotBattle\Models\User;

/**
* A game - tracks the state including players, turns, and the board
*/
class Game extends Model implements \JsonSerializable {

    const STATE_WAITING = 0;
    const STATE_RUNNING = 1;
    const STATE_DONE = 2;

    public $board;
    public $players;

    public $difficulty;
    public $turn;
    public $state;
    public $length;

    /**
    * Constructor
    * @param int $id The ID of the game
    * @param Board $board The board with the tiles for the game
    * @param array $players The players that have joined the game
    * @param int $difficulty The difficulty of the game
    * @param int $length The length of the game (total number of turns)
    * @param int $state The current state of the game - should be one of Game::STATE_WAITING, Game::STATE_RUNNING, Game::STATE_DONE
    * @param int $turn The current turn of the game
    */
    public function __construct(int $id, Board $board, array $players, int $difficulty, int $length=500, int $state = 0, int $turn = 0){
        parent::__construct($id);

        $this->board = $board;
        $this->players = $players;
        
        $this->difficulty = $difficulty;
        $this->state = $state;
        $this->turn = $turn;
        $this->length = $length;
    }

    /**
    * Get the player of for the given user
    * @param User $user The user to lookup the player for
    *
    * @return Player|null The player or null if the one isn't found for the given user (the user hasn't joined the game)
    */
    public function getUserPlayer(User $user) { // : ?Player {
        foreach ($this->players as $player) {
            if ($player->user->getId() == $user->getId()) {
                return $player;
            }
        }

        return null;
    }

    /**
    * Get a game's player by ID
    * @param int $id The id of the player
    *
    * @return Player|null The player or null if the one isn't found for the given id
    */
    public function getPlayerById(int $id) { // : ?Player {
        foreach ($this->players as $player) {
            if ($player->getId() == $id) {
                return $player;
            }
        }

        return null;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'board' => $this->board,
            'players' => $this->players,
            'difficulty' => $this->difficulty,
            'state' => $this->state,
            'turn' => $this->turn,
            'length' => $this->length
        ];
    }
}