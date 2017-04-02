<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\Player;

/**
* Player store
*/
class PlayerStore extends DbStore {

    private $userStore;

    /**
    * Constructor
    * @param Db $db The backing database
    * @param UserStore $userStore The player store
    */
    public function __construct(Db $db, UserStore $userStore){
        parent::__construct($db, 'players');

        $this->userStore = $userStore;
    }

    /**
    * Load a DB row into a Player
    * @param array $row The Database row
    *
    * @return Model The loaded Player
    */
    protected function loadItem(array $row) : Model {
        $user = $this->userStore->get($row['users_id']);
        return new Player(
            $row['id'],
            $row['games_id'],
            $user,
            $row['x'],
            $row['y'],
            $row['health'],
            $row['points']
        );
    }

    /**
    * Get the column => value pairs from a Player
    * @param Model $item The Player to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'games_id' => $item->gameId,
            'users_id' => ($item->user !== null) ? $item->user->id : null,
            'x' => $item->x,
            'y' => $item->y,
            'health' => $item->health,
            'points' => $item->points,
        );
    }

    /**
    * Remove the Player's children (currently nothing)
    * @param Model $item The Player to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        return true;
    }

}