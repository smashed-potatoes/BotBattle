<?php namespace BotBattle\Data;

use \BotBattle\Data\DbResult;
use \BotBattle\Data\DbError;

/**
* Handles database communication
*/
class Db {
    private $connection = null;

    /**
    * Constructor
    * @param string $host The hostname or IP address of the database server
    * @param string $db The name of the database
    * @param string $user The username to authenticate with
    * @param string $pass The password to authenticate with
    */
    public function __construct(string $host, string $db, string $user, string $pass) {
        try {
            $this->connection = new \PDO("mysql:host=$host;dbname=$db", $user, $pass);
            // Avoiding emulation - required to have int returned
            $this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            // Set error mode to exception
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        catch (Exception $ex) {
            die($ex->getMessage());
        }
    }

    /**
    * Query the database
    * @param string $query The SQL query to run
    * @param array $params Any parameters that should be bound to the statement
    *
    * @return DbResult The result of the query
    */
    public function query(string $query, array $params = null) {
        $statement = $this->connection->prepare($query);

        if ($statement->execute($params)) {
            return new DbResult($statement);
        }
        else {
            $error = new DbError($dbErr[0], $dbErr[1] . ': ' . $dbErr[2]);
            return new DbResult($statement, $error);
        }
    }

    /**
    * Get the ID of the last inserted row
    * @return int The ID of the last inserted row
    */
    public function insertId() {
        return intval($this->connection->lastInsertId());
    }

    /**
    * Start a transaction
    */
    public function beginTransaction() {
        $this->connection->beginTransaction();
    }

    /**
    * Commit a transaction
    */
    public function commit() {
        $this->connection->commit();
    }

    /**
    * Roll back a transaction
    */
    public function rollBack() {
        $this->connection->rollBack();
    }
}