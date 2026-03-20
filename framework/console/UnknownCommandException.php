<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

use yii\console\controllers\Help_Controller;
/**
 * UnknownCommandException represents an exception caused by incorrect usage of a console command.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0.11
 */
class Unknown_Command_Exception extends Exception
{
    /**
     * @var string the name of the command that could not be recognized.
     */
    public $command;
    /**
     * @var Application
     */
    protected $application;
    /**
     * Construct the exception.
     *
     * @param string $route the route of the command that could not be found.
     * @param Application $application the console application instance involved.
     * @param int $code the Exception code.
     * @param \Throwable|null $previous the previous exception used for the exception chaining.
     */
    public function __construct($route, $application, $code = 0, $previous = null)
    {
        $this->command = $route;
        $this->application = $application;
        parent::__construct("Unknown command \"{$route}\".", $code, $previous);
    }
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Unknown command';
    }
    /**
     * Suggest alternative commands for [[$command]] based on string similarity.
     *
     * Alternatives are searched using the following steps:
     *
     * - suggest alternatives that begin with `$command`
     * - find typos by calculating the Levenshtein distance between the unknown command and all
     *   available commands. The Levenshtein distance is defined as the minimal number of
     *   characters you have to replace, insert or delete to transform str1 into str2.
     *
     * @see https://www.php.net/manual/en/function.levenshtein.php
     * @return array a list of suggested alternatives sorted by similarity.
     */
    public function get_suggested_alternatives()
    {
        $help = $this->application->create_controller('help');
        if ($help === false || $this->command === '') {
            return [];
        }
        /** @var HelpController<Application> $helpController */
        [$help_controller, $action_id] = $help;
        $available_actions = [];
        foreach ($help_controller->get_commands() as $command) {
            $result = $this->application->create_controller($command);
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            if ($controller->create_action($controller->default_action) !== null) {
                // add the command itself (default action)
                $available_actions[] = $command;
            }
            // add all actions of this controller
            $actions = $help_controller->get_actions($controller);
            $prefix = $controller->get_unique_id();
            foreach ($actions as $action) {
                $available_actions[] = $prefix . '/' . $action;
            }
        }
        return $this->filter_by_similarity($available_actions, $this->command);
    }
    /**
     * Find suggest alternative commands based on string similarity.
     *
     * Alternatives are searched using the following steps:
     *
     * - suggest alternatives that begin with `$command`
     * - find typos by calculating the Levenshtein distance between the unknown command and all
     *   available commands. The Levenshtein distance is defined as the minimal number of
     *   characters you have to replace, insert or delete to transform str1 into str2.
     *
     * @see https://www.php.net/manual/en/function.levenshtein.php
     * @param array $actions available command names.
     * @param string $command the command to compare to.
     * @return array a list of suggested alternatives sorted by similarity.
     */
    private function filter_by_similarity(array $actions, $command): array
    {
        $alternatives = [];
        // suggest alternatives that begin with $command first
        foreach ($actions as $action) {
            if (strpos($action, $command) === 0) {
                $alternatives[] = $action;
            }
        }
        // calculate the Levenshtein distance between the unknown command and all available commands.
        $distances = array_map(function ($action) use ($command): int {
            $action = strlen($action) > 255 ? substr($action, 0, 255) : $action;
            $command = strlen($command) > 255 ? substr($command, 0, 255) : $command;
            return levenshtein($action, $command);
        }, array_combine($actions, $actions));
        // we assume a typo if the levensthein distance is no more than 3, i.e. 3 replacements needed
        $relevant_typos = array_filter($distances, fn(int $distance) => $distance <= 3);
        asort($relevant_typos);
        $alternatives = array_merge($alternatives, array_flip($relevant_typos));
        return array_unique($alternatives);
    }
}