<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\caching\Apc_Cache;
use yii\caching\Cache_Interface;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\helpers\Console;
/**
 * Allows you to flush cache.
 *
 * see list of available components to flush:
 *
 *     yii cache
 *
 * flush particular components specified by their names:
 *
 *     yii cache/flush first second third
 *
 * flush all cache components that can be found in the system
 *
 *     yii cache/flush-all
 *
 * Note that the command uses cache components defined in your console application configuration file. If components
 * configured are different from web application, web application cache won't be cleared. In order to fix it please
 * duplicate web application cache components in console config. You can use any component names.
 *
 * APC is not shared between PHP processes so flushing cache from command line has no effect on web.
 * Flushing web cache could be either done by:
 *
 * - Putting a php file under web root and calling it via HTTP
 * - Using [Cachetool](https://gordalina.github.io/cachetool/)
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
class Cache_Controller extends Controller
{
    /**
     * Lists the caches that can be flushed.
     */
    public function action_index(): void
    {
        $caches = $this->find_caches();
        if (!empty($caches)) {
            $this->notify_caches_can_be_flushed($caches);
        } else {
            $this->notify_no_caches_found();
        }
    }
    /**
     * Flushes given cache components.
     *
     * For example,
     *
     * ```
     * # flushes caches specified by their id: "first", "second", "third"
     * yii cache/flush first second third
     * ```
     */
    public function action_flush()
    {
        $caches_input = func_get_args();
        if (empty($caches_input)) {
            throw new Exception('You should specify cache components names');
        }
        $caches = $this->find_caches($caches_input);
        $caches_info = [];
        $found_caches = array_keys($caches);
        $not_found_caches = array_diff($caches_input, array_keys($caches));
        if ($not_found_caches !== []) {
            $this->notify_not_found_caches($not_found_caches);
        }
        if ($found_caches === []) {
            $this->notify_no_caches_found();
            return Exit_Code::OK;
        }
        if (!$this->confirm_flush($found_caches)) {
            return Exit_Code::OK;
        }
        foreach ($caches as $name => $class) {
            $caches_info[] = ['name' => $name, 'class' => $class, 'is_flushed' => $this->can_be_flushed($class) ? Yii::$app->get($name)->flush() : false];
        }
        $this->notify_flushed($caches_info);
    }
    /**
     * Flushes all caches registered in the system.
     */
    public function action_flush_all()
    {
        $caches = $this->find_caches();
        $caches_info = [];
        if (empty($caches)) {
            $this->notify_no_caches_found();
            return Exit_Code::OK;
        }
        foreach ($caches as $name => $class) {
            $caches_info[] = ['name' => $name, 'class' => $class, 'is_flushed' => $this->can_be_flushed($class) ? Yii::$app->get($name)->flush() : false];
        }
        $this->notify_flushed($caches_info);
    }
    /**
     * Clears DB schema cache for a given connection component.
     *
     * ```
     * # clears cache schema specified by component id: "db"
     * yii cache/flush-schema db
     * ```
     *
     * @param string $db id connection component
     * @return int exit code
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     *
     * @since 2.0.1
     */
    public function action_flush_schema($db = 'db'): int
    {
        $connection = Yii::$app->get($db, false);
        if ($connection === null) {
            $this->stdout("Unknown component \"{$db}\".\n", Console::FG_RED);
            return Exit_Code::UNSPECIFIED_ERROR;
        }
        if (!$connection instanceof \yii\db\Connection) {
            $this->stdout("\"{$db}\" component doesn't inherit \\yii\\db\\Connection.\n", Console::FG_RED);
            return Exit_Code::UNSPECIFIED_ERROR;
        }
        if (!$this->confirm("Flush cache schema for \"{$db}\" connection?")) {
            return Exit_Code::OK;
        }
        try {
            $schema = $connection->get_schema();
            $schema->refresh();
            $this->stdout("Schema cache for component \"{$db}\", was flushed.\n\n", Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->stdout($e->get_message() . "\n\n", Console::FG_RED);
        }
        return Exit_Code::OK;
    }
    /**
     * Notifies user that given caches are found and can be flushed.
     * @param array $caches array of cache component classes
     */
    private function notify_caches_can_be_flushed($caches): void
    {
        $this->stdout("The following caches were found in the system:\n\n", Console::FG_YELLOW);
        foreach ($caches as $name => $class) {
            if ($this->can_be_flushed($class)) {
                $this->stdout("\t* {$name} ({$class})\n", Console::FG_GREEN);
            } else {
                $this->stdout("\t* {$name} ({$class}) - can not be flushed via console\n", Console::FG_YELLOW);
            }
        }
        $this->stdout("\n");
    }
    /**
     * Notifies user that there was not found any cache in the system.
     */
    private function notify_no_caches_found(): void
    {
        $this->stdout("No cache components were found in the system.\n", Console::FG_RED);
    }
    /**
     * Notifies user that given cache components were not found in the system.
     */
    private function notify_not_found_caches(array $caches_names): void
    {
        $this->stdout("The following cache components were NOT found:\n\n", Console::FG_RED);
        foreach ($caches_names as $name) {
            $this->stdout("\t* {$name} \n", Console::FG_GREEN);
        }
        $this->stdout("\n");
    }
    private function notify_flushed(array $caches): void
    {
        $this->stdout("The following cache components were processed:\n\n", Console::FG_YELLOW);
        foreach ($caches as $cache) {
            $this->stdout("\t* " . $cache['name'] . ' (' . $cache['class'] . ')', Console::FG_GREEN);
            if (!$cache['is_flushed']) {
                $this->stdout(" - not flushed\n", Console::FG_RED);
            } else {
                $this->stdout("\n");
            }
        }
        $this->stdout("\n");
    }
    /**
     * Prompts user with confirmation if caches should be flushed.
     * @return bool
     */
    private function confirm_flush(array $caches_names)
    {
        $this->stdout("The following cache components will be flushed:\n\n", Console::FG_YELLOW);
        foreach ($caches_names as $name) {
            $this->stdout("\t* {$name} \n", Console::FG_GREEN);
        }
        return $this->confirm("\nFlush above cache components?");
    }
    /**
     * Returns array of caches in the system, keys are cache components names, values are class names.
     * @param array $cachesNames caches to be found
     */
    private function find_caches(array $caches_names = []): array
    {
        $caches = [];
        $components = Yii::$app->get_components();
        $find_all = $caches_names === [];
        foreach ($components as $name => $component) {
            if (!$find_all && !in_array($name, $caches_names, true)) {
                continue;
            }
            if ($component instanceof Cache_Interface) {
                $caches[$name] = get_class($component);
            } elseif (is_array($component) && isset($component['class']) && $this->is_cache_class($component['class'])) {
                $caches[$name] = $component['class'];
            } elseif (is_string($component) && $this->is_cache_class($component)) {
                $caches[$name] = $component;
            } elseif ($component instanceof \Closure) {
                $cache = Yii::$app->get($name);
                if ($this->is_cache_class($cache)) {
                    $cache_class = get_class($cache);
                    $caches[$name] = $cache_class;
                }
            }
        }
        return $caches;
    }
    /**
     * Checks if given class is a Cache class.
     * @param string $className class name.
     */
    private function is_cache_class($class_name): bool
    {
        return is_subclass_of($class_name, 'yii\caching\CacheInterface') || $class_name === 'yii\caching\CacheInterface';
    }
    /**
     * Checks if cache of a certain class can be flushed.
     * @param string $className class name.
     */
    private function can_be_flushed($class_name): bool
    {
        return !is_a($class_name, Apc_Cache::class_name(), true) || PHP_SAPI !== 'cli';
    }
}