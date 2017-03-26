<?php namespace BotBattle\Data;

/**
* Database error
*/
class DbError {

    public $code;
    public $message;

    /**
    * Constructor
    * @param string $code The error code
    * @param string $message The error message
    */
    public function __construct(string $code, string $message = null) {
        $this->code = $code;
        $this->message = $message;
    }

}