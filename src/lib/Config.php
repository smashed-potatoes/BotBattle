<?php namespace BotBattle;

/**
* Configuration storage and item definitions
*/
class Config {
    private $config;

    const DB_HOST = 'db_host';
    const DB_DATABASE = 'db_database';
    const DB_USER = 'db_user';
    const DB_PASS = 'db_pass';

    /**
    * Constructor
    * @param array $config The configuration items to store
    */
    public function __construct(array $config){
        $this->config = $config;
    }

    /**
    * Get a configuration item
    * @param string $itemId The ID of the configuration item
    *
    * @return mixed The value of configuration item
    */
    public function get(string $itemId) {
        if (isset($this->config[$itemId])) {
            return $this->config[$itemId];
        }
        else {
            throw new \Exception("Attempting to access unset config item: $itemId");
        }
    }
}