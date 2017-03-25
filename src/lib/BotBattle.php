<?php namespace BotBattle;

use BotBattle\Models\Board;
use BotBattle\Models\Player;

class BotBattle implements \JsonSerializable {
    public $board;
    public $players;

    function __construct(){
        // TODO: Load state
        $this->board = new Board(10, 10);
        $this->players = [
            new Player('Bob', 0, 0),
            new Player('Steve', 3,3)
        ];
    }

    public function jsonSerialize() {
        return [
            'board' => $this->board,
            'players' => $this->players
        ];
    }
}