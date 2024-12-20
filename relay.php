<?php

require_once __DIR__ . '/bootstrap.php';

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket;
use Amp\Websocket\Server\Websocket;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use function Amp\trapSignal;
use nostriphant\Transpher\RequestHandler;
use nostriphant\NIP19\Bech32;
use nostriphant\NIP01\Key;

list($ip, $port) = explode(":", $_SERVER['argv'][1]);

$logger = new Logger('relay-' . $port);
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/server.log', match (strtoupper($_SERVER['RELAY_LOG_LEVEL'] ?? 'INFO')) {
                    'DEBUG' => Level::Debug,
                    'NOTICE' => Level::Notice,
                    'INFO' => Level::Info,
                    'WARNING' => Level::Warning,
                    'ERROR' => Level::Error,
                    'CRITICAL' => Level::Critical,
                    'ALERT' => Level::Alert,
                    'EMERGENCY' => Level::Emergency,
                    default => Level::Info
                }));
$logger->pushHandler(new StreamHandler(STDOUT, Level::Info));

$server = SocketHttpServer::createForDirectAccess($logger, connectionLimitPerIp: $_SERVER['RELAY_MAX_CONNECTIONS_PER_IP'] ?? 1000);
$server->expose(new Socket\InternetAddress($ip, $port));

$errorHandler = new DefaultErrorHandler();
Monolog\ErrorHandler::register($logger);

$acceptor = new Amp\Websocket\Server\Rfc6455Acceptor();
//$acceptor = new AllowOriginAcceptor(
//    ['http://localhost:' . $port, 'http://127.0.0.1:' . $port, 'http://[::1]:' . $port],
//);

$whitelist = nostriphant\Transpher\Nostr\Subscription::make(
        [
            'authors' => [
                Bech32::fromNpub($_SERVER['RELAY_OWNER_NPUB']),
                Key::fromHex(Bech32::fromNsec($_SERVER['AGENT_NSEC']))(Key::public())
            ],
        ],
        [
            '#p' => [Bech32::fromNpub($_SERVER['RELAY_OWNER_NPUB'])]
        ]
);
if (isset($_SERVER['RELAY_DATA'])) {
    $data_dir = $_SERVER['RELAY_DATA'];
    is_dir($data_dir) || mkdir($data_dir);

    $events = new nostriphant\Transpher\Stores\SQLite(new SQLite3($data_dir . '/transpher.sqlite'), $whitelist);

    $store_path = $data_dir . '/events';
    if (is_dir($store_path)) {
        $logger->debug('Starting migrating events...');
        $logger->debug(\nostriphant\Transpher\Stores\Disk::walk_store($store_path, function (nostriphant\NIP01\Event $event) use ($store_path, &$events) {
                    $events[$event->id] = $event;
                    return unlink($store_path . '/' . $event->id . '.php');
                }) . ' events migrated.');
    }

    $files_path = $data_dir . '/files';
} else {
    $store_path = $_SERVER['RELAY_STORE'] ?? ROOT_DIR . '/data/events';
    $events = new \nostriphant\Transpher\Stores\Disk($store_path, $whitelist);

    $files_path = $_SERVER['RELAY_FILES'] ?? ROOT_DIR . '/data/files';
}
$files = new \nostriphant\Transpher\Files($files_path, $events);

$incoming = new \nostriphant\Transpher\Relay\Incoming($events, $files);
$clientHandler = new \nostriphant\Transpher\Relay($incoming, $logger);

$router = new Router($server, $logger, $errorHandler);
$router->addRoute('GET', '/', new RequestHandler(new Websocket($server, $logger, $acceptor, $clientHandler)));
nostriphant\Transpher\Relay\Blossom::connect($files, $router);

$server->start($router, $errorHandler);

// Await SIGINT or SIGTERM to be received.
$signal = trapSignal([SIGINT, SIGTERM]);

$logger->info(sprintf("Received signal %d, stopping Relay server", $signal));

$server->stop();