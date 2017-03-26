<?php namespace BotBattle\Services;

use BotBattle\Data\Db;

use BotBattle\Models\User;

/**
* Handles users interactions
*/
class UserService {
    private $db;

    /**
    * Constructor
    * @param Db $db The database connection to use
    */
    public function __construct(Db $db) {
        $this->db = $db;
    }

    /**
    * Log in as a user
    * @param string $username The user to log in as
    *
    * @return User The user
    */
    public function login(string $username) { // : ?User
        $user = $this->getUserByUsername($username);
        
        if ($user == null) {
            $user = $this->addUser($username);
        }

        // User is still null after create, something went wrong
        if ($user == null) {
            throw new \Exception('Failed to create user');
        }

        $_SESSION['user'] = $user;

        return $user;
    }

    /**
    * Get the session's current user
    * @return User|null The session's current user
    */
    public function getCurrentUser() { // : ?User
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        else {
            return null;
        }
    }

    /**
    * Get a user by username
    * @param string $username The username of the user
    *
    * @return User|null The user with the given username if there is one
    */
    public function getUserByUsername(string $username) { // : ?User
        $qry = "SELECT * FROM users WHERE username = :username";
        $params = [
            'username' => $username
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            return new User($row['id'], $row['username']);
        }

        return null;
    }

    /**
    * Get a user
    * @param int $id The ID of the user
    *
    * @return User|null The user
    */
    public function getUser(int $id) { // : ?User
        $qry = "SELECT * FROM users WHERE id = :id";
        $params = [
            'id' => $id
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        if ($row = $result->statement->fetch(\PDO::FETCH_ASSOC)) {
            return new User($row['id'], $row['username']);
        }

        return null;
    }

    /**
    * Add a new user
    * @param string $username The username of the user
    *
    * @return User The added user
    */
    public function addUser(string $username) { // : ?User
        // Create new user
        $qry = "INSERT INTO users (username) VALUES (:username)";
        $params = [
            'username' => $username
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return $this->getUserByUsername($username);
    }

    /**
    * Delete a user
    * @param User $user The user to delete
    *
    * @return bool Whether deletion was succesful
    */
    public function deleteUser(User $user) : bool {
        $qry = "DELETE FROM users WHERE id = :id";
        $params = [
            'id' => $user->getId()
        ];
        $result = $this->db->query($qry, $params);

        if ($result->error !== null) {
            throw new \Exception($result->error);
        }

        return true;
    }
}