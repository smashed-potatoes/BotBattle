<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\User;

/**
* User store
*/
class UserStore extends DbStore {

    /**
    * Constructor
    * @param Db $db The backing database
    */
    public function __construct(Db $db){
        parent::__construct($db, 'users');
    }

    /**
    * Load a DB row into a User
    * @param array $row The Database row
    *
    * @return Model The loaded User
    */
    protected function loadItem(array $row) : Model {
        return new User(
            $row['id'],
            $row['username']
        );
    }

    /**
    * Get the column => value pairs from a User
    * @param Model $item The User to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'username' => $item->username
        );
    }

    /**
    * Remove the User's children (currently nothing)
    * @param Model $item The User to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        return true;
    }

}