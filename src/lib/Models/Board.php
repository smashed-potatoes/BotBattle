<?php namespace BotBattle\Models;

class Board implements \JsonSerializable {
    public $tiles;

    function __construct(int $width, int $height){
        $tiles = [];
        for ($x=0; $x<$width; $x++) {
            $tiles[$x] = [];
            for ($y=0; $y<$height; $y++) {
                $tiles[$x][$y] = new Tile($x, $y);
            }
        }

        $this->tiles = $tiles;
    }


    public function jsonSerialize() {
        return [
            'tiles' => $this->tiles
        ];
    }
}