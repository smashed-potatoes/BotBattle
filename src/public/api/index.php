<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use \BotBattle\BotBattle;

session_start();

// Setup Slim
$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ]
];
$container = new \Slim\Container($configuration);
$app = new \Slim\App($container);

$app->group("/user", function () {
    $this->post('[/]', function (Request $request, Response $response) {
        $json = $request->getBody();
        $data = json_decode($json, true);

        // Check data is given
        if (empty($data)) {
            error_log("Empty Data");
            return $response->withStatus(400);
        }

        $_SESSION['username'] = $data['username'];

        $json = ['username' => $_SESSION['username']];

        return $response->withJson($json);
    });

    $this->get('[/]', function (Request $request, Response $response) {
        $json = ['username' => $_SESSION['username']];

        return $response->withJson($json);
    });
});

$app->group("/action", function () {
    $this->post('[/]', function (Request $request, Response $response) {
        $json = $request->getBody();
        $data = json_decode($json, true);

        // Check data is given
        if (empty($data)) {
            error_log("Empty Data");
            return $response->withStatus(400);
        }

        switch ($data['action']) {
            case 'left':
                break;
            case 'right':
                break;
            case 'up':
                break;
            case 'down':
                break;
            default:
                // noop
                break;
        }

        // Return the current state
        $state = new BotBattle();

        return $response->withJson($state);
    });
});

$app->get('[/]', function (Request $request, Response $response) {
    $state = new BotBattle();
    return $response->withJson($state);
});

$app->run();