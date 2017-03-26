<?php namespace BotBattle;

use BotBattle\Config;
use BotBattle\Data\Db;

use BotBattle\Services\UserService;
use BotBattle\Services\GameService;

/**
* The BotBattle main entry point
*/
class BotBattle {

    private $config;
    private $db;

    public $userService;
    public $gameService;

    /**
    * Constructor
    * @param Config $config The configuration to use
    */
    public function __construct(Config $config){
        $this->config = $config;
        $this->db = new Db(
            $this->config->get(Config::DB_HOST),
            $this->config->get(Config::DB_DATABASE),
            $this->config->get(Config::DB_USER),
            $this->config->get(Config::DB_PASS)
        );

        $this->userService = new UserService($this->db);
        $this->gameService = new GameService($this->db, $this->userService);
    }
}