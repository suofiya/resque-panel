<?php
/**
 * server.php
 *
 */
define('APP_PATH', __DIR__ . '/../');

$autoload = require __DIR__ . '/../vendor/autoload.php';
$autoload->add('', APP_PATH . 'src/');

$config = require APP_PATH . 'config/config.php';

// fork a child process to record queue status data
$child_logger = new \swoole_process(function () use ($config) {
    $logger = new \Monolog\Logger('Queue Monitor');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler('/tmp/queue_monitor.log', \Monolog\Logger::INFO));
    while (true) {
        (new \ResquePanel\Service\CollectorService())->persistCurrentMinuteStatus();
        $logger->info('Hello ' . strtotime('now')); // todo remove
        sleep(60);
    }
});

$child_logger_pid = $child_logger->start();

$server     = new \swoole_websocket_server('0.0.0.0', 11011);
$dispatcher = new \ResquePanel\Dispatcher();

$server->on('Open', function ($server, $req) use ($dispatcher, $config) {
    echo "connection open: " . $req->fd;
});

$server->on('Message', function ($server, $frame) use ($dispatcher, $config) {
    try {
        $dispatcher->setServer($server)->setFrame($frame)->setConfig($config)->handle();
    } catch (\Exception $e) {
    }
});

$server->on('Close', function ($server, $fd) {
    echo "connection close: " . $fd;
});

$server->start();