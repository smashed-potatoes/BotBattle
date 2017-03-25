<?php namespace BotBattle\Models;

class Player implements \JsonSerializable{
    public $name;
    public $x;
    public $y;

    function __construct(string $name, int $x, int $y){
        $this->name = $name;
        $this->x = $x;
        $this->y = $y;
    }

    public function jsonSerialize() {
        return [
            'name' => $this->name,
            'x' => $this->x,
            'y' => $this->y
        ];
    }
}