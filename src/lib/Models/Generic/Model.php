<?php namespace BotBattle\Models\Generic;

/**
* Base model
*/
abstract class Model {
    protected $id;

    /**
    * Constructor
    * @param int $id The ID of the model
    */
    function __construct(int $id){
        $this->id = $id;
    }

    /**
    * Get the ID of the model
    * @return int The ID of the model
    */
    public function getId() {
        return $this->id;
    }
}