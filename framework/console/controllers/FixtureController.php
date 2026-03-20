<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\base\Invalid_Param_Exception;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\helpers\Console;
use yii\helpers\File_Helper;
use yii\test\Fixture;
use yii\test\Fixture_Trait;
/**
 * Manages fixture data loading and unloading.
 *
 * ```
 * #load fixtures from UsersFixture class with default namespace "tests\unit\fixtures"
 * yii fixture/load User
 *
 * #also a short version of this command (generate action is default)
 * yii fixture User
 *
 * #load all fixtures
 * yii fixture "*"
 *
 * #load all fixtures except User
 * yii fixture "*, -User"
 *
 * #load fixtures with different namespace.
 * yii fixture/load User --namespace=alias\my\custom\namespace\goes\here
 * ```
 *
 * The `unload` sub-command can be used similarly to unload fixtures.
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
class Fixture_Controller extends Controller
{
    use Fixture_Trait;
    /**
     * @var string controller default action ID.
     */
    public $default_action = 'load';
    /**
     * @var string default namespace to search fixtures in
     */
    public $namespace = 'tests\unit\fixtures';
    /**
     * @var array global fixtures that should be applied when loading and unloading. By default it is set to `InitDbFixture`
     * that disables and enables integrity check, so your data can be safely loaded.
     */
    public $global_fixtures = ['yii\test\InitDbFixture'];
    /**
     * {@inheritdoc}
     */
    public function options($action_id): array
    {
        return array_merge(parent::options($action_id), ['namespace', 'globalFixtures']);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function option_aliases(): array
    {
        return array_merge(parent::option_aliases(), ['g' => 'globalFixtures', 'n' => 'namespace']);
    }
    /**
     * Loads the specified fixture data.
     *
     * For example,
     *
     * ```
     * # load the fixture data specified by User and UserProfile.
     * # any existing fixture data will be removed first
     * yii fixture/load "User, UserProfile"
     *
     * # load all available fixtures found under 'tests\unit\fixtures'
     * yii fixture/load "*"
     *
     * # load all fixtures except User and UserProfile
     * yii fixture/load "*, -User, -UserProfile"
     * ```
     *
     * @return int return code
     * @throws Exception if the specified fixture does not exist.
     */
    public function action_load(array $fixtures_input = []): int
    {
        if ($fixtures_input === []) {
            $this->print_help_message();
            return Exit_Code::OK;
        }
        $filtered = $this->filter_fixtures($fixtures_input);
        $except = $filtered['except'];
        if (!$this->need_to_apply_all($fixtures_input[0])) {
            $fixtures = $filtered['apply'];
            $found_fixtures = $this->find_fixtures($fixtures);
            $not_found_fixtures = array_diff($fixtures, $found_fixtures);
            if ($not_found_fixtures !== []) {
                $this->notify_not_found($not_found_fixtures);
            }
        } else {
            $found_fixtures = $this->find_fixtures();
        }
        $fixtures_to_load = array_diff($found_fixtures, $except);
        if (!$found_fixtures) {
            throw new Exception('No files were found for: "' . implode(', ', $fixtures_input) . "\".\n" . "Check that files exist under fixtures path: \n\"" . $this->get_fixture_path() . '".');
        }
        if ($fixtures_to_load === []) {
            $this->notify_nothing_to_load($found_fixtures, $except);
            return Exit_Code::OK;
        }
        if (!$this->confirm_load($fixtures_to_load, $except)) {
            return Exit_Code::OK;
        }
        $fixtures = $this->get_fixtures_config(array_merge($this->global_fixtures, $fixtures_to_load));
        if (!$fixtures) {
            throw new Exception('No fixtures were found in namespace: "' . $this->namespace . '"' . '');
        }
        $fixtures_objects = $this->create_fixtures($fixtures);
        $this->unload_fixtures($fixtures_objects);
        $this->load_fixtures($fixtures_objects);
        $this->notify_loaded($fixtures_objects);
        return Exit_Code::OK;
    }
    /**
     * Unloads the specified fixtures.
     *
     * For example,
     *
     * ```
     * # unload the fixture data specified by User and UserProfile.
     * yii fixture/unload "User, UserProfile"
     *
     * # unload all fixtures found under 'tests\unit\fixtures'
     * yii fixture/unload "*"
     *
     * # unload all fixtures except User and UserProfile
     * yii fixture/unload "*, -User, -UserProfile"
     * ```
     *
     * @return int return code
     * @throws Exception if the specified fixture does not exist.
     */
    public function action_unload(array $fixtures_input = []): int
    {
        if ($fixtures_input === []) {
            $this->print_help_message();
            return Exit_Code::OK;
        }
        $filtered = $this->filter_fixtures($fixtures_input);
        $except = $filtered['except'];
        if (!$this->need_to_apply_all($fixtures_input[0])) {
            $fixtures = $filtered['apply'];
            $found_fixtures = $this->find_fixtures($fixtures);
            $not_found_fixtures = array_diff($fixtures, $found_fixtures);
            if ($not_found_fixtures !== []) {
                $this->notify_not_found($not_found_fixtures);
            }
        } else {
            $found_fixtures = $this->find_fixtures();
        }
        if ($found_fixtures === []) {
            throw new Exception('No files were found for: "' . implode(', ', $fixtures_input) . "\".\n" . "Check that files exist under fixtures path: \n\"" . $this->get_fixture_path() . '".');
        }
        $fixtures_to_unload = array_diff($found_fixtures, $except);
        if ($fixtures_to_unload === []) {
            $this->notify_nothing_to_unload($found_fixtures, $except);
            return Exit_Code::OK;
        }
        if (!$this->confirm_unload($fixtures_to_unload, $except)) {
            return Exit_Code::OK;
        }
        $fixtures = $this->get_fixtures_config(array_merge($this->global_fixtures, $fixtures_to_unload));
        if ($fixtures === []) {
            throw new Exception('No fixtures were found in namespace: ' . $this->namespace . '".');
        }
        $this->unload_fixtures($this->create_fixtures($fixtures));
        $this->notify_unloaded($fixtures);
        return Exit_Code::OK;
    }
    /**
     * Show help message.
     */
    private function print_help_message(): void
    {
        $this->stdout($this->get_help_summary() . "\n");
        $help_command = Console::ansi_format('yii help fixture', [Console::FG_CYAN]);
        $this->stdout("Use {$help_command} to get usage info.\n");
    }
    /**
     * Notifies user that fixtures were successfully loaded.
     * @param Fixture[] $fixtures array of loaded fixtures
     */
    private function notify_loaded($fixtures): void
    {
        $this->stdout("Fixtures were successfully loaded from namespace:\n", Console::FG_YELLOW);
        $this->stdout("\t\"" . Yii::get_alias($this->namespace) . "\"\n\n", Console::FG_GREEN);
        $fixture_class_names = [];
        foreach ($fixtures as $fixture) {
            $fixture_class_names[] = $fixture::class_name();
        }
        $this->output_list($fixture_class_names);
    }
    /**
     * Notifies user that there are no fixtures to load according input conditions.
     * @param array $foundFixtures array of found fixtures
     * @param array $except array of names of fixtures that should not be loaded
     */
    public function notify_nothing_to_load($found_fixtures, $except): void
    {
        $this->stdout("Fixtures to load could not be found according given conditions:\n\n", Console::FG_RED);
        $this->stdout("Fixtures namespace is: \n", Console::FG_YELLOW);
        $this->stdout("\t" . $this->namespace . "\n", Console::FG_GREEN);
        if (count($found_fixtures)) {
            $this->stdout("\nFixtures founded under the namespace:\n\n", Console::FG_YELLOW);
            $this->output_list($found_fixtures);
        }
        if (count($except)) {
            $this->stdout("\nFixtures that will NOT be loaded: \n\n", Console::FG_YELLOW);
            $this->output_list($except);
        }
    }
    /**
     * Notifies user that there are no fixtures to unload according input conditions.
     * @param array $foundFixtures array of found fixtures
     * @param array $except array of names of fixtures that should not be loaded
     */
    public function notify_nothing_to_unload($found_fixtures, $except): void
    {
        $this->stdout("Fixtures to unload could not be found according to given conditions:\n\n", Console::FG_RED);
        $this->stdout("Fixtures namespace is: \n", Console::FG_YELLOW);
        $this->stdout("\t" . $this->namespace . "\n", Console::FG_GREEN);
        if (count($found_fixtures)) {
            $this->stdout("\nFixtures found under the namespace:\n\n", Console::FG_YELLOW);
            $this->output_list($found_fixtures);
        }
        if (count($except)) {
            $this->stdout("\nFixtures that will NOT be unloaded: \n\n", Console::FG_YELLOW);
            $this->output_list($except);
        }
    }
    /**
     * Notifies user that fixtures were successfully unloaded.
     * @param array $fixtures
     */
    private function notify_unloaded($fixtures): void
    {
        $this->stdout("\nFixtures were successfully unloaded from namespace: ", Console::FG_YELLOW);
        $this->stdout(Yii::get_alias($this->namespace) . "\"\n\n", Console::FG_GREEN);
        $this->output_list($fixtures);
    }
    /**
     * Notifies user that fixtures were not found under fixtures path.
     */
    private function notify_not_found(array $fixtures): void
    {
        $this->stdout("Some fixtures were not found under path:\n", Console::BG_RED);
        $this->stdout("\t" . $this->get_fixture_path() . "\n\n", Console::FG_GREEN);
        $this->stdout("Check that they have correct namespace \"{$this->namespace}\" \n", Console::BG_RED);
        $this->output_list($fixtures);
        $this->stdout("\n");
    }
    /**
     * Prompts user with confirmation if fixtures should be loaded.
     * @param array $except
     * @return bool
     */
    private function confirm_load(array $fixtures, $except)
    {
        $this->stdout("Fixtures namespace is: \n", Console::FG_YELLOW);
        $this->stdout("\t" . $this->namespace . "\n\n", Console::FG_GREEN);
        if (count($this->global_fixtures)) {
            $this->stdout("Global fixtures will be used:\n\n", Console::FG_YELLOW);
            $this->output_list($this->global_fixtures);
        }
        if (count($fixtures)) {
            $this->stdout("\nFixtures below will be loaded:\n\n", Console::FG_YELLOW);
            $this->output_list($fixtures);
        }
        if (count($except)) {
            $this->stdout("\nFixtures that will NOT be loaded: \n\n", Console::FG_YELLOW);
            $this->output_list($except);
        }
        $this->stdout("\nBe aware that:\n", Console::BOLD);
        $this->stdout("Applying leads to purging of certain data in the database!\n", Console::FG_RED);
        return $this->confirm("\nLoad above fixtures?");
    }
    /**
     * Prompts user with confirmation for fixtures that should be unloaded.
     * @param array $except
     * @return bool
     */
    private function confirm_unload(array $fixtures, $except)
    {
        $this->stdout("Fixtures namespace is: \n", Console::FG_YELLOW);
        $this->stdout("\t" . $this->namespace . "\n\n", Console::FG_GREEN);
        if (count($this->global_fixtures)) {
            $this->stdout("Global fixtures will be used:\n\n", Console::FG_YELLOW);
            $this->output_list($this->global_fixtures);
        }
        if (count($fixtures)) {
            $this->stdout("\nFixtures below will be unloaded:\n\n", Console::FG_YELLOW);
            $this->output_list($fixtures);
        }
        if (count($except)) {
            $this->stdout("\nFixtures that will NOT be unloaded:\n\n", Console::FG_YELLOW);
            $this->output_list($except);
        }
        return $this->confirm("\nUnload fixtures?");
    }
    /**
     * Outputs data to the console as a list.
     * @param array $data
     */
    private function output_list($data): void
    {
        foreach ($data as $index => $item) {
            $this->stdout("\t" . ($index + 1) . ". {$item}\n", Console::FG_GREEN);
        }
    }
    /**
     * Checks if needed to apply all fixtures.
     * @param string $fixture
     */
    public function need_to_apply_all($fixture): bool
    {
        return $fixture === '*';
    }
    /**
     * Finds fixtures to be loaded, for example "User", if no fixtures were specified then all of them
     * will be searching by suffix "Fixture.php".
     * @param array $fixtures fixtures to be loaded
     * @return array Array of found fixtures. These may differ from input parameter as not all fixtures may exists.
     */
    private function find_fixtures(array $fixtures = []): array
    {
        $fixtures_path = $this->get_fixture_path();
        $files_to_search = ['*Fixture.php'];
        $find_all = $fixtures === [];
        if (!$find_all) {
            $files_to_search = [];
            foreach ($fixtures as $file_name) {
                $files_to_search[] = $file_name . 'Fixture.php';
            }
        }
        $files = File_Helper::find_files($fixtures_path, ['only' => $files_to_search]);
        $found_fixtures = [];
        foreach ($files as $fixture) {
            $found_fixtures[] = $this->get_fixture_relative_name($fixture);
        }
        return $found_fixtures;
    }
    /**
     * Calculates fixture's name
     * Basically, strips [[getFixturePath()]] and `Fixture.php' suffix from fixture's full path.
     * @see getFixturePath()
     * @param string $fullFixturePath Full fixture path
     * @return string Relative fixture name
     */
    private function get_fixture_relative_name($full_fixture_path): string
    {
        $fixtures_path = File_Helper::normalize_path($this->get_fixture_path());
        $full_fixture_path = File_Helper::normalize_path($full_fixture_path);
        $relative_name = substr($full_fixture_path, strlen($fixtures_path) + 1);
        $relative_dir = dirname($relative_name) === '.' ? '' : dirname($relative_name) . '/';
        return $relative_dir . basename($full_fixture_path, 'Fixture.php');
    }
    /**
     * Returns valid fixtures config that can be used to load them.
     * @param array $fixtures fixtures to configure
     */
    private function get_fixtures_config(array $fixtures): array
    {
        $config = [];
        foreach ($fixtures as $fixture) {
            $is_namespaced = strpos($fixture, '\\') !== false;
            // replace linux' path slashes to namespace backslashes, in case if $fixture is non-namespaced relative path
            $fixture = str_replace('/', '\\', $fixture);
            $full_class_name = $is_namespaced ? $fixture : $this->namespace . '\\' . $fixture;
            if (class_exists($full_class_name)) {
                $config[] = $full_class_name;
            } elseif (class_exists($full_class_name . 'Fixture')) {
                $config[] = $full_class_name . 'Fixture';
            } else {
                throw new Exception('Neither fixture "' . $full_class_name . '" nor "' . $full_class_name . 'Fixture" was found.');
            }
        }
        return $config;
    }
    /**
     * Filters fixtures by splitting them in two categories: one that should be applied and not.
     *
     * If fixture is prefixed with "-", for example "-User", that means that fixture should not be loaded,
     * if it is not prefixed it is considered as one to be loaded. Returns array:
     *
     * ```
     * [
     *     'apply' => [
     *         'User',
     *         ...
     *     ],
     *     'except' => [
     *         'Custom',
     *         ...
     *     ],
     * ]
     * ```
     * @return array fixtures array with 'apply' and 'except' elements.
     */
    private function filter_fixtures(array $fixtures): array
    {
        $filtered = ['apply' => [], 'except' => []];
        foreach ($fixtures as $fixture) {
            if (mb_strpos($fixture, '-') !== false) {
                $filtered['except'][] = str_replace('-', '', $fixture);
            } else {
                $filtered['apply'][] = $fixture;
            }
        }
        return $filtered;
    }
    /**
     * Returns fixture path that determined on fixtures namespace.
     * @throws InvalidConfigException if fixture namespace is invalid
     * @return string fixture path
     */
    private function get_fixture_path()
    {
        try {
            return Yii::get_alias('@' . str_replace('\\', '/', $this->namespace));
        } catch (Invalid_Param_Exception $e) {
            throw new Invalid_Config_Exception('Invalid fixture namespace: "' . $this->namespace . '". Please, check your FixtureController::namespace parameter');
        }
    }
}