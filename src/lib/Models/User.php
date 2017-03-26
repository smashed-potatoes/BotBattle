<?php namespace BotBattle\Models;

use BotBattle\Models\Generic\Model;

/**
* A user
*/
class User extends Model implements \JsonSerializable{
    public $id;
    public $username;

    /**
    * Constructor
    * @param int $id The ID of the user
    * @param string $username The user's name
    */
    public function __construct(int $id, string $username){
        parent::__construct($id);

        $this->username = $username;
    }

    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'username' => $this->username
        ];
    }
}