<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A tile on a game board
*/
class Tile extends Model implements \JsonSerializable {
    const TYPE_GROUND = 0;
    const TYPE_WALL = 1;
    const TYPE_GOLD = 2;
    const TYPE_HEAL = 3;

    public $x;
    public $y;
    public $player;
    public $type;

    /**
    * Constructor
    * @param int $id The ID of the tile
    * @param int $x The x coordinate of the tile
    * @param int $y The y coordinate of the tile
    * @param Player $player The player that owns the tile if there is one
    * @param int $type The type of tile, must be one of Tile::TYPE_GROUND, Tile::TYPE_WALL
    */
    public function __construct(int $id, int $x, int $y, Player $player = null, int $type = Tile::TYPE_GROUND){
        parent::__construct($id);

        $this->x = $x;
        $this->y = $y;
        $this->player = $player;
        $this->type = $type;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'player' => $this->player,
            'type' => $this->type,
            'x' => $this->x,
            'y' => $this->y
        ];
    }
}