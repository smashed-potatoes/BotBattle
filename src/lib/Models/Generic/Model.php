<?php namespace BotBattle\Models\Generic;

/**
* Base model
*/
abstract class Model {
    public $id;

    /**
    * Constructor
    * @param int $id The ID of the model
    */
    function __construct(int $id){
        $this->id = $id;
    }
}