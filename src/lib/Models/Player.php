<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A player within a game - a user can be a player in multiple games
*/
class Player extends Model implements \JsonSerializable{
    public $user;
    public $x;
    public $y;
    public $health;
    public $points;

    /**
    * Constructor
    * @param int $id The ID of the player
    * @param User $user The user the player is for
    * @param int $x The x coordinate of the player
    * @param int $y The y coordinate of the player
    * @param int $health The health of the player
    * @param int $points The number of points the player has
    */
    public function __construct(int $id, User $user, int $x, int $y, int $health = 100, int $points = 0) {
        parent::__construct($id);

        $this->user = $user;
        $this->x = $x;
        $this->y = $y;
        $this->health = $health;
        $this->points = $points;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'x' => $this->x,
            'y' => $this->y,
            'health' => $this->health,
            'points' => $this->points
        ];
    }
}