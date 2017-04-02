<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\Tile;

/**
* Tile store
*/
class TileStore extends DbStore {

    private $playerStore;

    /**
    * Constructor
    * @param Db $db The backing database
    * @param PlayerStore $playerStore The player store
    */
    public function __construct(Db $db, PlayerStore $playerStore){
        parent::__construct($db, 'tiles');

        $this->playerStore = $playerStore;
    }

    /**
    * Load a DB row into a Tile
    * @param array $row The Database row
    *
    * @return Model The loaded Tile
    */
    protected function loadItem(array $row) : Model {
        $player = ($row['players_id'] !== null) ? $this->playerStore->get($row['players_id']) : null;

        return new Tile(
            $row['id'],
            $row['boards_id'],
            $row['x'],
            $row['y'],
            $player,
            $row['type']
        );
    }

    /**
    * Get the column => value pairs from a Tile
    * @param Model $item The Tile to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'boards_id' => $item->boardId,
            'x' => $item->x,
            'y' => $item->y,
            'players_id' => ($item->player !== null) ? $item->player->id : null,
            'type' => $item->type,
        );
    }

    /**
    * Remove the Tile's children (currently nothing)
    * @param Model $item The Tile to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        return true;
    }

}