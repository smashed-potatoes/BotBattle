<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A tile on a game board
*/
class Tile extends Model implements \JsonSerializable {
    public $x;
    public $y;
    public $player;

    /**
    * Constructor
    * @param int $id The ID of the tile
    * @param int $x The x coordinate of the tile
    * @param int $y The y coordinate of the tile
    * @param Player $player The player that owns the tile if there is one
    */
    public function __construct(int $id, int $x, int $y, Player $player = null){
        parent::__construct($id);

        $this->x = $x;
        $this->y = $y;
        $this->player = $player;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'player' => $this->player,
            'x' => $this->x,
            'y' => $this->y
        ];
    }
}