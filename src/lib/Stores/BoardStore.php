<?php namespace BotBattle\Stores;

use BotBattle\Data\Db;
use BotBattle\Stores\Generic\DbStore;
use BotBattle\Models\Generic\Model;
use BotBattle\Models\Board;

/**
* Board store
*/
class BoardStore extends DbStore {

    /**
    * Constructor
    * @param Db $db The backing database
    * @param TileStore $tileStore The tile store
    */
    public function __construct(Db $db, TileStore $tileStore){
        parent::__construct($db, 'boards');

        $this->tileStore = $tileStore;
    }

    /**
    * Load a DB row into a Board
    * @param array $row The Database row
    *
    * @return Model The loaded Board
    */
    protected function loadItem(array $row) : Model {
        $tiles = $this->tileStore->getWhere(['boards_id' => $row['id']]);
        
        return new Board(
            $row['id'],
            $row['width'],
            $row['height'],
            $tiles
        );
    }

    /**
    * Get the column => value pairs from a Board
    * @param Model $item The Board to get the values from
    *
    * @return array The array of column => value pairs
    */
    protected function getColumns(Model $item) : array {
        return array(
            'width' => $item->width,
            'height' => $item->height
        );
    }

    /**
    * Remove the Board's Tiles from their store
    * @param Model $item The Board to remove the children from
    *
    * @return bool Whether removal was successful
    */
    protected function removeChildren(Model $item) : bool {
        $result = true;
        foreach ($item->getTiles() as $tile) {
            $result = $result && $this->tileStore->remove($tile);
        }
        
        return $result;
    }

}