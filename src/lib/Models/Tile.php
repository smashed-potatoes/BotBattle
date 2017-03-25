<?php namespace BotBattle\Models;

class Tile  implements \JsonSerializable {
    public $x;
    public $y;
    public $owner;

    function __construct(int $x, int $y){
        $this->x = $x;
        $this->y = $y;
    }

    public function jsonSerialize() {
        return [
            'owner' => rand(0,1),//$this->owner,
            'x' => $this->x,
            'y' => $this->y
        ];
    }
}