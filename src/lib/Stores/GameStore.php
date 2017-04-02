<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\Game;

/**
* Game store
*/
class GameStore extends DbStore {

    private $boardStore;

    /**
    * Constructor
    * @param Db $db The backing database
    * @param BoardStore $boardStore The board store
    * @param PlayerStore $playerStore The player store
    */
    public function __construct(Db $db, BoardStore $boardStore, PlayerStore $playerStore){
        parent::__construct($db, 'games');

        $this->boardStore = $boardStore;
        $this->playerStore = $playerStore;
    }

    /**
    * Load a DB row into a Game
    * @param array $row The Database row
    *
    * @return Model The loaded Game
    */
    protected function loadItem(array $row) : Model {
        $board = $this->boardStore->get($row['boards_id']);
        $players = $this->playerStore->getWhere(['games_id' => $row['id']]);

        return new Game(
            $row['id'],
            $board,
            $players,
            $row['difficulty'],
            $row['max_players'],
            $row['length'],
            $row['state'],
            $row['turn']
        );
    }

    /**
    * Get the column => value pairs from a Game
    * @param Model $item The Game to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'boards_id' => ($item->board !== null) ? $item->board->id : null,
            'difficulty' => $item->difficulty,
            'max_players' => $item->maxPlayers,
            'length' => $item->length,
            'state' => $item->state,
            'turn' => $item->turn
        );
    }

    /**
    * Remove the Game's Players from their store
    * @param Model $item The Game to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        $result = true;

        foreach ($item->players as $player) {
            $result = $result && $this->playerStore->remove($player);

        }

        return $result;
    }

}