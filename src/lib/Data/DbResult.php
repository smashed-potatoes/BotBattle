<?php namespace BotBattle\Data;

/**
* Database query result
*/
class DbResult {

    public $statement;
    public $error;

    /**
    * Constructor
    * @param PDOStatement $statement The statement with the query result
    * @param DbError $error The error if there was one
    */
    public function __construct(\PDOStatement $statement, DbError $error = null) {
        $this->statement = $statement;
        $this->error = $error;
    }

}