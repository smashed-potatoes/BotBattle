<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A game board, holds all of the tiles
*/
class Board extends Model implements \JsonSerializable {
    public $tiles;
    public $width;
    public $height;
    public $healTiles;
    public $goldTiles;


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

        $this->healTiles = [];
        $this->goldTiles = [];
        $this->tileMap = [];
        foreach($tiles as $tile) {
            $this->tileMap[$tile->x][$tile->y] = $tile;
            
            if ($tile->type === Tile::TYPE_HEAL) {
                $this->healTiles[] = $tile;
            }
            elseif ($tile->type === Tile::TYPE_GOLD) {
                $this->goldTiles[] = $tile;
            }
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
            'tiles' => $this->tiles,
            'healTiles' => $this->healTiles,
            'goldTiles' => $this->goldTiles
        ];
    }
}