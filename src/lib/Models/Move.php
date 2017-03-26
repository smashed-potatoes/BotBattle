<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A player's move within a game
*/
class Move extends Model implements \JsonSerializable{

    const ACTION_NONE = 0;
    const ACTION_LEFT = 1;
    const ACTION_RIGHT = 2;
    const ACTION_UP = 3;
    const ACTION_DOWN = 4;

    public $player;
    public $action;
    public $turn;

    /**
    * Constructor
    * @param int $id The ID of the move
    * @param Player $player The player that made the move
    * @param int $turn The turn within the game that the move was made
    * @param int $action The action that was performed - should be one of MOVE::ACTION_NONE, MOVE::ACTION_LEFT, MOVE::ACTION_RIGHT, MOVE::ACTION_UP, MOVE::ACTION_DOWN
    */
    public function __construct(int $id, Player $player, int $turn, int $action){
        parent::__construct($id);

        $this->player = $player;
        $this->action = $action;
        $this->turn = $turn;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'player' => $this->player,
            'action' => $this->action,
            'turn' => $this->turn
        ];
    }
}