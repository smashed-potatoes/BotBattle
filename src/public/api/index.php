<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use \BotBattle\BotBattle;
use \BotBattle\Config;

use \BotBattle\Models\Error;
use \BotBattle\Models\Game;

session_start();

// Load the config and app
$config = new Config(include('config.php'));
$botBattle = new BotBattle($config);

// Setup Slim
$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ]
];
$container = new \Slim\Container($configuration);
$app = new \Slim\App($container);

/**
* User requests
*/
$app->group("/users", function () use ($botBattle) {
    $this->post('[/]', function (Request $request, Response $response) use ($botBattle) {
        $json = $request->getBody();

        // TODO: Deserialize to a request type
        $data = json_decode($json, true);

        // Check data is given
        if (empty($data)) {
            return $response->withJson(new Error('No user data sent'), 400);
        }

        $user = $botBattle->userService->login($data['username']);

        return $response->withJson($user);
    });

    $this->get('[/]', function (Request $request, Response $response) use ($botBattle) {
        $user = $botBattle->userService->getCurrentUser();

        return $response->withJson($user);
    });

    $this->get('/{id}', function (Request $request, Response $response) use ($botBattle) {
        $id = $request->getAttribute('id');
        $user = $botBattle->userService->getUser($id);

        // User not found
        if ($user === null) {
            return $response->withJson(new Error('User not found'), 404);
        }

        return $response->withJson($user);
    });
});

/**
* Game requests
*/
$app->group("/games", function () use ($botBattle) {
    /**
    * Create a new game or get an open matching one
    */
    $this->post('[/]', function (Request $request, Response $response) use ($botBattle) {
        $json = $request->getBody();

        // TODO: Deserialize to a request type
        $data = json_decode($json, true);

        // Check data is given
        if (empty($data)) {
            return $response->withJson(new Error('No game data sent'), 400);
        }

        // Create the game
        $game = $botBattle->gameService->createGame($data['difficulty']);

        return $response->withJson($game);
    });


    /**
    * Get current games
    */
    $this->get('[/]', function (Request $request, Response $response) use ($botBattle) {
        // TODO: List games
         return $response->withJson(new Error('Method not implemented'), 400);
    });

    /**
    * Get a game by ID
    */
    $this->get('/{id}', function (Request $request, Response $response) use ($botBattle) {
        $id = $request->getAttribute('id');
        $turn = $request->getQueryParam('turn');

        $game = $botBattle->gameService->getGame($id);

        // Game not found
        if ($game === null) {
            return $response->withJson(new Error('Game not found'), 404);
        }

        if ($turn !== null && $turn < $game->turn) {
            $game = $botBattle->gameService->getTurnState($game, $turn);
        }

        return $response->withJson($game);
    });


    /**
    * Get a game by ID
    */
    $this->get('/{id}/states', function (Request $request, Response $response) use ($botBattle) {
        $id = $request->getAttribute('id');

        $game = $botBattle->gameService->getGame($id);
        // Game not found
        if ($game === null) {
            return $response->withJson(new Error('Game not found'), 404);
        }

        $states = $botBattle->gameService->getGameStates($game);

        return $response->withJson($states);
    });

    /**
    * Join a game
    */
    $this->post('/{id}/players', function (Request $request, Response $response) use ($botBattle) {
        $user = $botBattle->userService->getCurrentUser();
        if ($user == null) {
            return $response->withJson(new Error('Not logged in'), 401);
        }

        $id = $request->getAttribute('id');
        $game = $botBattle->gameService->getGame($id);

        // Game not found
        if ($game === null) {
            return $response->withJson(new Error('Game not found'), 404);
        }

        $player = $game->getUserPlayer($user);
        if ($player == null) {
            // Game isn't waiting for players
            if ($game->state !== Game::STATE_WAITING) {
                return $response->withJson(new Error('Game has already started'), 400);
            }
            
            $player = $botBattle->gameService->joinGame($game, $user);
        }

        return $response->withJson($player);
    });

    /**
    * Make a move in a game
    */
    $this->post('/{id}/moves', function (Request $request, Response $response) use ($botBattle) {
        $user = $botBattle->userService->getCurrentUser();
        if ($user == null) {
            return $response->withJson(new Error('Not logged in'), 401);
        }

        $id = $request->getAttribute('id');
        $game = $botBattle->gameService->getGame($id);

        // Game not found
        if ($game === null) {
            return $response->withJson(new Error('Game not found'), 404);
        }

        if ($game->state === Game::STATE_WAITING) {
            return $response->withJson(new Error('Game hasn\'t started'), 400);
        }
        if ($game->state === Game::STATE_DONE) {
            return $response->withJson(new Error('Game has ended'), 400);
        }

        $json = $request->getBody();

        // TODO: Deserialize to a request type
        $data = json_decode($json, true);

        // Check data is given
        if (empty($data)) {
            return $response->withJson(new Error('No move data sent'), 400);
        }

        // Get the user's player
        $user = $botBattle->userService->getCurrentUser();
        $player = $game->getUserPlayer($user);

        if ($player === null) {
            return $response->withJson(new Error('Current user is not a player in the game'), 400);
        }

        $action = intval($data['action']);
        $move = $botBattle->gameService->makeMove($game, $player, $action);

        // Move failed
        if ($move === null) {
            return $response->withJson(new Error('Invalid move action'), 400);
        }

        return $response->withJson($move);
    });
});

$app->get('[/]', function (Request $request, Response $response) use ($botBattle) {
    return $response->withJson(['BATTLEBOTS']);
});

$app->run();