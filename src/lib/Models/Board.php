<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A game board, holds all of the tiles
*/
class Board extends Model implements \JsonSerializable {
    public $tiles;
    public $width;
    public $height;

    private $tileMap;

    /**
    * Constructor
    * @param int $id The ID of the board
    * @param int $width The width of the board
    * @param int $height The height of the board
    * @param array $tiles The tiles within the board
    */
    public function __construct(int $id, int $width, int $height, array $tiles){
        parent::__construct($id);
        
        $this->width = $width;
        $this->height = $height;
        $this->tiles = $tiles;

        $this->tileMap = [];
        foreach($tiles as $tile) {
            $this->tileMap[$tile->x][$tile->y] = $tile;
        }
    }

    /**
    * Get a tile at a given location
    * @param int $x The x coordinate of the tile
    * @param int $y The y coordinate of the tile
    *
    * @return Tile The tile at the given location
    */
    public function getTileAt(int $x, int $y) {
        return $this->tileMap[$x][$y];
    }


    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'width' => $this->width,
            'height' => $this->height,
            'tiles' => $this->tiles
        ];
    }
}