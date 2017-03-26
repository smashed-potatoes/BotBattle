<?php namespace BotBattle\Models;

/**
* An error that can be serialized
*/
class Error implements \JsonSerializable {
    private $message;

    /**
    * Constructor
    * @param string $message The error message
    */
    public function __construct(string $message){
        $this->message = $message;
    }


    /**
    * Serializes the object to a value that can be serialized
    * @return array Indexed array of exposed values to be serialized
    */
    public function jsonSerialize() {
        return [
            'message' => $this->message
        ];
    }
}