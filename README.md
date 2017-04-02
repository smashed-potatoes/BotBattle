# BotBattle
An API driven bot battle for exploring AI development - inspired by vindinium

## About
The purpose of the game is to create a bot that can outsmart the other players in a competition to navigate a grid of tiles and control the most gold resources for the most turns

![Example Gameplay](https://smashedtatoes.com/projects/botbattle/BotBattle.gif)

### Overview
- Each turn the players can make one move by posting their desired action to the game
- After all players have submitted their moves, the game is updated and the turn is advanced
  - If two players are on the same tile, they battle and both lose 20pts of health
  - If a player is on a heal tile, their health is restored by 20pts
  - If a player moves onto a gold tile, it is captured and their health is decreased by 20pts
  - Each player is awarded 1 point per gold tile they control at the end of the round
  - If a player's health falls below 1pt they are killed, losing all captured gold tiles, and restart at the nearest heal tile
- The players continue to make moves until the turn limit is reached at which point the player with the most points wins

#### Example Map
```
0 = Grass
1 = Wall
2 = Gold
3 = Heal

2 0 0 0 0 0 0 0 0 0 2
0 0 0 0 0 1 0 0 0 0 0
0 0 0 0 0 1 0 0 0 0 0
0 0 0 0 0 1 0 0 0 0 0
0 1 1 1 3 1 3 1 1 1 0
0 0 0 0 0 1 0 0 0 0 0
0 0 0 0 0 1 0 0 0 0 0
0 0 0 0 0 1 0 0 0 0 0
2 0 0 0 0 0 0 0 0 0 2
```


### Client Communication
| # | Action | Method | Endpoint | Payload | Response |
|---|--------|--------|----------|---------|----------|
| 1 | Log in / Create user | `POST` | `/api/users` | `{ "username": "MyUserName" }`| User |
| 2 | Find / Create a game | `POST` | `/api/games` | `{ "difficulty": [0-4] }`| Game |
| 3 | Join the game found | `POST` | `/api/games/{gameId}/players` | | Player |
| 4 | Wait for the game to start <br>` { "state" : 1 ... }` | `GET` |  `/api/games/{gameId}` | | Game |
| 5 | Make a move | `POST` | `/api/games/{gameId}/moves` | `{ "action" : [0-4] }` | Move |
| 6 | Wait for the turn to advance <br>` { "turn" : 1 ... }` | `GET` |  `/api/games/{gameId}` | | Game |

Repeat steps 5-6 until the game is over: ` { "state" : 2 ... }`

#### Game States
| # | State |
|---|-------|
| 0 | Waiting for players |
| 1 | Running |
| 2 | Done |

#### Actions
| # | Action |
|---|--------|
| 0 | None |
| 1 | Left |
| 2 | Right |
| 3 | Up |
| 4 | Down |


### Starter Clients
There are starter clients available in the [BotBattle-Clients Repository](https://github.com/smashed-potatoes/BotBattle-Clients)

## Setup
### Using Vagrant
1. Start vagrant
```
vagrant up
```
2. Create a `deploy_resources/app.cfg` based on `deploy_resources/app.cfg.example`
```bash
DB_HOST=db.hostname.com # <-- The hostname of your database (will be mapped to localhost in /etc/hosts)
DB_USER=battle_admin    # <-- The username to create in the DB
DB_PASS=db_password     # <-- The password to use for the created user
```
3. Create a `src/public/api/config.php` based on `src/public/api/config.php.example`
```php
return [
    Config::DB_HOST => 'db.hostname.com', // <-- The hostname of your database (same as above)
    Config::DB_USER => 'battle_admin',    // <-- The username to access the DB (same as above)
    Config::DB_PASS => 'db_password'      // <-- The password to access the DB (same as above)
];
```
4. Run the application initialization script
```
cd /vagrant/deploy_resources
./app_bootstrap.sh
```

*Note: You may need to run `vagrant reload` for everything to get up to date after the initial setup*

### Manual deploy
#### Requirements
1. PHP 7
1. MySQL
1. [Composer](https://getcomposer.org/)

#### Steps
1. Create a user in your MySQL DB to use for the application
2. Load the database by running the initialization script: `/vagrant/deploy_resources/db/init.sql`
3. Create a `src/public/api/config.php` based on `src/public/api/config.php.example`
```php
return [
    Config::DB_HOST => 'db.hostname.com', // <-- The hostname of your database
    Config::DB_USER => 'battle_admin',    // <-- The username to access the DB
    Config::DB_PASS => 'db_password'      // <-- The password to access the DB
];
```
4. Install the dependencies by running `composer install` in the `src` directory
