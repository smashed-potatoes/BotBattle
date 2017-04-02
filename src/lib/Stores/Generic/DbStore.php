<?php namespace BotBattle\Stores\Generic;

use BotBattle\Stores\Generic\Store;
use BotBattle\Models\Generic\Model;
use BotBattle\Data\Db;

/**
* Database backed store - handles the storage of items (CRUD)
*/
abstract class DbStore extends Store {

    protected $table;
    protected $db;

    /**
    * Load a DB row into a model
    * @param array $row The Database row
    *
    * @return Model The loaded model
    */
    abstract protected function loadItem(array $row) : Model;

    /**
    * Get the column => value pairs from a model
    * @param Model $item The model instance to get the values from
    *
    * @return array The array of column => value pairs
    */
    abstract protected function getColumns(Model $item) : array;

    /**
    * Remove any children from their respective stores
    * @param Model $item The model instance to remove the children from
    *
    * @return bool Whether removal was successful
    */
    abstract protected function removeChildren(Model $item) : bool;


    /**
    * Constructor
    * @param Db $db The backing database
    * @param string $table The table the models are stored in
    */
    public function __construct(Db $db, string $table) {
        $this->db = $db;
        $this->table = $table;
        $this->items = [];
    }
    
    /**
    * Get an item from the store by ID
    * @param int $id The ID of the item
    *
    * @return Model|null The instance of the item if found or null
    */
    public function get(int $id) { // : ?Model {
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }

        $qry = "SELECT * FROM {$this->table} WHERE id = :id";
        $params = [
            'id' => $id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            $item = $this->loadItem($row);
            $this->items[$item->id] = $item;

            return $item;
        }

        return null;
    }

    /**
    * Get a set of  items from the store matching a set of criteria
    * @param array $where An array of column => values to match
    *
    * @return array An array of matching instances
    */
    public function getWhere(array $where) : array {

        $constraints = array();
        foreach ($where as $name => $value) {
            $constraints[] = "$name = :$name";
        }
        $constraintsString = implode(" AND ", $constraints);

        $qry = "SELECT id FROM {$this->table} WHERE $constraintsString";
        $result = $this->db->query($qry, $where);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $rows =  $result->statement->fetchAll(\PDO::FETCH_COLUMN, 0);

        $items = array();
        foreach ($rows as $id) {
            $items[] = $this->get($id);
        }

        return $items;
    }

    /**
    * Store a new or existing item in the store
    * @param Model $item The instance to store
    *
    * @return bool Whether storing was successful or not
    */
    public function store(Model $item) : bool {
        if ($item->id == -1) {
            return $this->insert($item);
        }
        else {
            return $this->update($item);
        }
        
    }

    /**
    * Remove an item from the store
    * @param Model $item The instance to remove
    *
    * @return bool Whether storing was removal or not
    */
    public function remove(Model $item) : bool {
        if (!$this->removeChildren($item)) {
            return false;
        }

        $qry = "DELETE FROM {$this->table} WHERE id = :id";
        $params = [
            'id' => $item->id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;

    }

    /**
    * Insert a new item
    * @param Model $item The instance to insert
    *
    * @return bool Whether inserting was successful or not
    */
    private function insert(Model $item) : bool {
        $attributes = $this->getColumns($item);

        $columns = [];
        $paramIds = [];
        foreach ($attributes as $name => $value) {
            $columns[] = $name;
            $paramIds[] = ":$name";
        }
        $columnsString = implode(", ", $columns);
        $paramsString = implode(", ", $paramIds);

        $qry = "INSERT INTO {$this->table} ($columnsString) VALUES ($paramsString)";
        $result = $this->db->query($qry, $attributes);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        $item->id = $this->db->insertId();
        $this->items[$item->id] = $item;

        return true;
    }

    /**
    * Update an existing item
    * @param Model $item The instance to update
    *
    * @return bool Whether updating was successful or not
    */
    private function update(Model $item) : bool {
        $attributes = $this->getColumns($item);
        
        $setStrings = [];
        foreach ($attributes as $name => $value) {
            $setStrings[] = "$name = :$name";
        }
        $setString = implode(", ", $setStrings);

        $qry = "UPDATE {$this->table} SET $setString WHERE id = :id";

        $attributes['id'] = $item->id;
        $result = $this->db->query($qry, $attributes);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }
}