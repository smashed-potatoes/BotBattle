<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\Move;

/**
* Move store
*/
class MoveStore extends DbStore {

    private $playerStore;

    /**
    * Constructor
    * @param Db $db The backing database
    * @param PlayerStore $playerStore The player store
    */
    public function __construct(Db $db, PlayerStore $playerStore){
        parent::__construct($db, 'moves');

        $this->playerStore = $playerStore;
    }

    /**
    * Load a DB row into a Move
    * @param array $row The Database row
    *
    * @return Model The loaded Move
    */
    protected function loadItem(array $row) : Model {
        $player = $this->playerStore->get($row['players_id']);

        return new Move(
            $row['id'],
            $row['games_id'],
            $player,
            $row['turn'],
            $row['action']
        );
    }

    /**
    * Get the column => value pairs from a Move
    * @param Model $item The Move to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'games_id' => $item->gameId,
            'players_id' => ($item->player !== null) ? $item->player->id : null,
            'turn' => $item->turn,
            'action' => $item->action
        );
    }

    /**
    * Remove the Move's children (currently nothing)
    * @param Model $item The Move to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        return true;
    }

}