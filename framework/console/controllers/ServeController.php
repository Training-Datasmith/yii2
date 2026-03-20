<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exit_Code;
use yii\helpers\Console;
/**
 * Runs PHP built-in web server.
 *
 * In order to access server from remote machines use 0.0.0.0:8000. That is especially useful when running server in
 * a virtual machine.
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0.7
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
class Serve_Controller extends Controller
{
    public const EXIT_CODE_NO_DOCUMENT_ROOT = 2;
    public const EXIT_CODE_NO_ROUTING_FILE = 3;
    public const EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_SERVER = 4;
    public const EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_PROCESS = 5;
    /**
     * @var int port to serve on.
     */
    public $port = 8080;
    /**
     * @var string path or [path alias](guide:concept-aliases) to directory to serve
     */
    public $docroot = '@app/web';
    /**
     * @var string path or [path alias](guide:concept-aliases) to router script.
     * See https://www.php.net/manual/en/features.commandline.webserver.php
     */
    public $router;
    /**
     * Runs PHP built-in web server.
     *
     * @param string $address address to serve on. Either "host" or "host:port".
     */
    public function action_index($address = 'localhost'): int
    {
        $document_root = Yii::get_alias($this->docroot);
        $router = $this->router !== null ? Yii::get_alias($this->router) : null;
        if (strpos($address, ':') === false) {
            $address = $address . ':' . $this->port;
        }
        if (!is_dir($document_root)) {
            $this->stdout("Document root \"{$document_root}\" does not exist.\n", Console::FG_RED);
            return self::EXIT_CODE_NO_DOCUMENT_ROOT;
        }
        if ($this->is_address_taken($address)) {
            $this->stdout("http://{$address} is taken by another process.\n", Console::FG_RED);
            return self::EXIT_CODE_ADDRESS_TAKEN_BY_ANOTHER_PROCESS;
        }
        if ($this->router !== null && !file_exists($router)) {
            $this->stdout("Routing file \"{$router}\" does not exist.\n", Console::FG_RED);
            return self::EXIT_CODE_NO_ROUTING_FILE;
        }
        $this->stdout("Server started on http://{$address}/\n");
        $this->stdout("Document root is \"{$document_root}\"\n");
        if ($this->router) {
            $this->stdout("Routing file is \"{$router}\"\n");
        }
        $this->stdout("Quit the server with CTRL-C or COMMAND-C.\n");
        $command = '"' . PHP_BINARY . '"' . " -S {$address} -t \"{$document_root}\"";
        if ($this->router !== null && $router !== '') {
            $command .= " \"{$router}\"";
        }
        $this->run_command($command);
        return Exit_Code::OK;
    }
    /**
     * {@inheritdoc}
     */
    public function options($action_id): array
    {
        return array_merge(parent::options($action_id), ['docroot', 'router', 'port']);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function option_aliases(): array
    {
        return array_merge(parent::option_aliases(), ['t' => 'docroot', 'p' => 'port', 'r' => 'router']);
    }
    /**
     * @param string $address server address
     * @return bool if address is already in use
     */
    protected function is_address_taken($address): bool
    {
        [$hostname, $port] = explode(':', $address);
        $fp = @fsockopen($hostname, $port, $errno, $errstr, 3);
        if ($fp === false) {
            return false;
        }
        fclose($fp);
        return true;
    }
    protected function run_command($command)
    {
        passthru($command);
    }
}