<?php namespace BotBattle\Stores\Generic;

use BotBattle\Models\Generic\Model;

/**
* Base store - handles the storage of items (CRUD)
*/
abstract class Store {
    protected $items;

    /**
    * Get an item from the store by ID
    * @param int $id The ID of the item
    *
    * @return Model|null The instance of the item if found or null
    */
    public abstract function get(int $id);

    /**
    * Get a set of  items from the store matching a set of criteria
    * @param array $where An array of column => values to match
    *
    * @return array An array of matching instances
    */
    public abstract function getWhere(array $where);

    /**
    * Store a new or existing item in the store
    * @param Model $item The instance to store
    *
    * @return bool Whether storing was successful or not
    */
    public abstract function store(Model $item);

    /**
    * Remove an item from the store
    * @param Model $item The instance to remove
    *
    * @return bool Whether storing was removal or not
    */
    public abstract function remove(Model $item);
}