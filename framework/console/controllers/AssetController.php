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
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\helpers\Console;
use yii\helpers\File_Helper;
use yii\helpers\Var_Dumper;
use yii\web\Asset_Bundle;
/**
 * Allows you to combine and compress your JavaScript and CSS files.
 *
 * Usage:
 *
 * 1. Create a configuration file using the `template` action:
 *
 *    yii asset/template /path/to/myapp/config.php
 *
 * 2. Edit the created config file, adjusting it for your web application needs.
 * 3. Run the 'compress' action, using created config:
 *
 *    yii asset /path/to/myapp/config.php /path/to/myapp/config/assets_compressed.php
 *
 * 4. Adjust your web application config to use compressed assets.
 *
 * Note: in the console environment some [path aliases](guide:concept-aliases) like `@webroot` and `@web` may not exist,
 * so corresponding paths inside the configuration should be specified directly.
 *
 * Note: by default this command relies on an external tools to perform actual files compression,
 * check [[jsCompressor]] and [[cssCompressor]] for more details.
 *
 * @property \yii\web\AssetManager $assetManager Asset manager instance. Note that the type of this property
 * differs in getter and setter. See [[getAssetManager()]] and [[setAssetManager()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
class Asset_Controller extends Controller
{
    /**
     * @var string controller default action ID.
     */
    public $default_action = 'compress';
    /**
     * @var array list of asset bundles to be compressed.
     */
    public $bundles = [];
    /**
     * @var array list of asset bundles, which represents output compressed files.
     * You can specify the name of the output compressed file using 'css' and 'js' keys:
     * For example:
     *
     * ```
     * 'app\config\AllAsset' => [
     *     'js' => 'js/all-{hash}.js',
     *     'css' => 'css/all-{hash}.css',
     *     'depends' => [ ... ],
     * ]
     * ```
     *
     * File names can contain placeholder "{hash}", which will be filled by the hash of the resulting file.
     *
     * You may specify several target bundles in order to compress different groups of assets.
     * In this case you should use 'depends' key to specify, which bundles should be covered with particular
     * target bundle. You may leave 'depends' to be empty for single bundle, which will compress all remaining
     * bundles in this case.
     * For example:
     *
     * ```
     * 'allShared' => [
     *     'js' => 'js/all-shared-{hash}.js',
     *     'css' => 'css/all-shared-{hash}.css',
     *     'depends' => [
     *         // Include all assets shared between 'backend' and 'frontend'
     *         'yii\web\YiiAsset',
     *         'app\assets\SharedAsset',
     *     ],
     * ],
     * 'allBackEnd' => [
     *     'js' => 'js/all-{hash}.js',
     *     'css' => 'css/all-{hash}.css',
     *     'depends' => [
     *         // Include only 'backend' assets:
     *         'app\assets\AdminAsset'
     *     ],
     * ],
     * 'allFrontEnd' => [
     *     'js' => 'js/all-{hash}.js',
     *     'css' => 'css/all-{hash}.css',
     *     'depends' => [], // Include all remaining assets
     * ],
     * ```
     */
    public $targets = [];
    /**
     * @var string|callable JavaScript file compressor.
     * If a string, it is treated as shell command template, which should contain
     * placeholders {from} - source file name - and {to} - output file name.
     * Otherwise, it is treated as PHP callback, which should perform the compression.
     *
     * Default value relies on usage of "Closure Compiler"
     * @see https://developers.google.com/closure/compiler/
     */
    public $js_compressor = 'java -jar compiler.jar --js {from} --js_output_file {to}';
    /**
     * @var string|callable CSS file compressor.
     * If a string, it is treated as shell command template, which should contain
     * placeholders {from} - source file name - and {to} - output file name.
     * Otherwise, it is treated as PHP callback, which should perform the compression.
     *
     * Default value relies on usage of "YUI Compressor"
     * @see https://github.com/yui/yuicompressor/
     */
    public $css_compressor = 'java -jar yuicompressor.jar --type css {from} -o {to}';
    /**
     * @var bool whether to delete asset source files after compression.
     * This option affects only those bundles, which have [[\yii\web\AssetBundle::sourcePath]] is set.
     * @since 2.0.10
     */
    public $delete_source = false;
    /**
     * @var array|\yii\web\AssetManager [[\yii\web\AssetManager]] instance or its array configuration, which will be used
     * for assets processing.
     */
    private $_asset_manager = [];
    /**
     * Returns the asset manager instance.
     * @throws \yii\console\Exception on invalid configuration.
     * @return \yii\web\AssetManager asset manager instance.
     */
    public function get_asset_manager()
    {
        if (!is_object($this->_asset_manager)) {
            $options = $this->_asset_manager;
            if (!isset($options['class'])) {
                $options['class'] = 'yii\web\AssetManager';
            }
            if (!isset($options['basePath'])) {
                throw new Exception("Please specify 'basePath' for the 'assetManager' option.");
            }
            if (!isset($options['baseUrl'])) {
                throw new Exception("Please specify 'baseUrl' for the 'assetManager' option.");
            }
            if (!isset($options['forceCopy'])) {
                $options['forceCopy'] = true;
            }
            $this->_asset_manager = Yii::create_object($options);
        }
        return $this->_asset_manager;
    }
    /**
     * Sets asset manager instance or configuration.
     * @param \yii\web\AssetManager|array $assetManager asset manager instance or its array configuration.
     * @throws \yii\console\Exception on invalid argument type.
     */
    public function set_asset_manager($asset_manager): void
    {
        if (is_scalar($asset_manager)) {
            throw new Exception('"' . get_class($this) . '::assetManager" should be either object or array - "' . gettype($asset_manager) . '" given.');
        }
        $this->_asset_manager = $asset_manager;
    }
    /**
     * Combines and compresses the asset files according to the given configuration.
     * During the process new asset bundle configuration file will be created.
     * You should replace your original asset bundle configuration with this file in order to use compressed files.
     * @param string $configFile configuration file name.
     * @param string $bundleFile output asset bundles configuration file name.
     */
    public function action_compress($config_file, $bundle_file): void
    {
        $this->load_configuration($config_file);
        $bundles = $this->load_bundles($this->bundles);
        $targets = $this->load_targets($this->targets, $bundles);
        foreach ($targets as $name => $target) {
            $this->stdout("Creating output bundle '{$name}':\n");
            if (!empty($target->js)) {
                $this->build_target($target, 'js', $bundles);
            }
            if (!empty($target->css)) {
                $this->build_target($target, 'css', $bundles);
            }
            $this->stdout("\n");
        }
        $targets = $this->adjust_dependency($targets, $bundles);
        $this->save_targets($targets, $bundle_file);
        if ($this->delete_source) {
            $this->delete_published_assets($bundles);
        }
    }
    /**
     * Applies configuration from the given file to self instance.
     * @param string $configFile configuration file name.
     * @throws \yii\console\Exception on failure.
     */
    protected function load_configuration($config_file)
    {
        $this->stdout("Loading configuration from '{$config_file}'...\n");
        $config = require $config_file;
        foreach ($config as $name => $value) {
            if (property_exists($this, $name) || $this->can_set_property($name)) {
                $this->{$name} = $value;
            } else {
                throw new Exception("Unknown configuration option: {$name}");
            }
        }
        $this->get_asset_manager();
        // check if asset manager configuration is correct
    }
    /**
     * Creates full list of source asset bundles.
     * @param string[] $bundles list of asset bundle names
     * @return \yii\web\AssetBundle[] list of source asset bundles.
     */
    protected function load_bundles($bundles): array
    {
        $this->stdout("Collecting source bundles information...\n");
        $am = $this->get_asset_manager();
        $result = [];
        foreach ($bundles as $name) {
            $result[$name] = $am->get_bundle($name);
        }
        foreach ($result as $bundle) {
            $this->load_dependency($bundle, $result);
        }
        return $result;
    }
    /**
     * Loads asset bundle dependencies recursively.
     * @param \yii\web\AssetBundle $bundle bundle instance
     * @param array $result already loaded bundles list.
     * @throws Exception on failure.
     */
    protected function load_dependency($bundle, array &$result)
    {
        $am = $this->get_asset_manager();
        foreach ($bundle->depends as $name) {
            if (!isset($result[$name])) {
                $dependency_bundle = $am->get_bundle($name);
                $result[$name] = false;
                $this->load_dependency($dependency_bundle, $result);
                $result[$name] = $dependency_bundle;
            } elseif ($result[$name] === false) {
                throw new Exception("A circular dependency is detected for bundle '{$name}': " . $this->compose_circular_dependency_trace($name, $result) . '.');
            }
        }
    }
    /**
     * Creates full list of output asset bundles.
     * @param array $targets output asset bundles configuration.
     * @param \yii\web\AssetBundle[] $bundles list of source asset bundles.
     * @return \yii\web\AssetBundle[] list of output asset bundles.
     * @throws Exception on failure.
     */
    protected function load_targets(array $targets, $bundles): array
    {
        // build the dependency order of bundles
        $registered = [];
        foreach ($bundles as $name => $bundle) {
            $this->register_bundle($bundles, $name, $registered);
        }
        $bundle_orders = array_combine(array_keys($registered), range(0, count($bundles) - 1));
        // fill up the target which has empty 'depends'.
        $referenced = [];
        foreach ($targets as $name => $target) {
            if (empty($target['depends'])) {
                if (!isset($all)) {
                    $all = $name;
                } else {
                    throw new Exception("Only one target can have empty 'depends' option. Found two now: {$all}, {$name}");
                }
            } else {
                foreach ($target['depends'] as $bundle) {
                    if (!isset($referenced[$bundle])) {
                        $referenced[$bundle] = $name;
                    } else {
                        throw new Exception("Target '{$referenced[$bundle]}' and '{$name}' cannot contain the bundle '{$bundle}' at the same time.");
                    }
                }
            }
        }
        if (isset($all)) {
            $targets[$all]['depends'] = array_diff(array_keys($registered), array_keys($referenced));
        }
        // adjust the 'depends' order for each target according to the dependency order of bundles
        // create an AssetBundle object for each target
        foreach ($targets as $name => $target) {
            if (!isset($target['basePath'])) {
                throw new Exception("Please specify 'basePath' for the '{$name}' target.");
            }
            if (!isset($target['baseUrl'])) {
                throw new Exception("Please specify 'baseUrl' for the '{$name}' target.");
            }
            usort($target['depends'], fn($a, $b) => $bundle_orders[$a] <=> $bundle_orders[$b]);
            if (!isset($target['class'])) {
                $target['class'] = $name;
            }
            $targets[$name] = Yii::create_object($target);
        }
        return $targets;
    }
    /**
     * Builds output asset bundle.
     * @param \yii\web\AssetBundle $target output asset bundle
     * @param string $type either 'js' or 'css'.
     * @param \yii\web\AssetBundle[] $bundles source asset bundles.
     * @throws Exception on failure.
     */
    protected function build_target($target, $type, array $bundles)
    {
        $input_files = [];
        foreach ($target->depends as $name) {
            if (isset($bundles[$name])) {
                if (!$this->is_bundle_external($bundles[$name])) {
                    foreach ($bundles[$name]->{$type} as $file) {
                        if (is_array($file)) {
                            $input_files[] = $bundles[$name]->base_path . '/' . $file[0];
                        } else {
                            $input_files[] = $bundles[$name]->base_path . '/' . $file;
                        }
                    }
                }
            } else {
                throw new Exception("Unknown bundle: '{$name}'");
            }
        }
        if (empty($input_files)) {
            $target->{$type} = [];
        } else {
            File_Helper::create_directory($target->base_path, $this->get_asset_manager()->dir_mode);
            $temp_file = $target->base_path . '/' . strtr($target->{$type}, ['{hash}' => 'temp']);
            if ($type === 'js') {
                $this->compress_js_files($input_files, $temp_file);
            } else {
                $this->compress_css_files($input_files, $temp_file);
            }
            $target_file = strtr($target->{$type}, ['{hash}' => md5_file($temp_file)]);
            $output_file = $target->base_path . '/' . $target_file;
            rename($temp_file, $output_file);
            $target->{$type} = [$target_file];
        }
    }
    /**
     * Adjust dependencies between asset bundles in the way source bundles begin to depend on output ones.
     * @param \yii\web\AssetBundle[] $targets output asset bundles.
     * @param \yii\web\AssetBundle[] $bundles source asset bundles.
     * @return \yii\web\AssetBundle[] output asset bundles.
     */
    protected function adjust_dependency(array $targets, array $bundles): array
    {
        $this->stdout("Creating new bundle configuration...\n");
        $map = [];
        foreach ($targets as $name => $target) {
            foreach ($target->depends as $bundle) {
                $map[$bundle] = $name;
            }
        }
        foreach ($targets as $name => $target) {
            $depends = [];
            foreach ($target->depends as $bn) {
                foreach ($bundles[$bn]->depends as $bundle) {
                    $depends[$map[$bundle]] = true;
                }
            }
            unset($depends[$name]);
            $target->depends = array_keys($depends);
        }
        // detect possible circular dependencies
        foreach ($targets as $name => $target) {
            $registered = [];
            $this->register_bundle($targets, $name, $registered);
        }
        foreach ($map as $bundle => $target) {
            $source_bundle = $bundles[$bundle];
            $depends = $source_bundle->depends;
            if (!$this->is_bundle_external($source_bundle)) {
                $depends[] = $target;
            }
            $target_bundle = clone $source_bundle;
            $target_bundle->depends = $depends;
            $targets[$bundle] = $target_bundle;
        }
        return $targets;
    }
    /**
     * Registers asset bundles including their dependencies.
     * @param \yii\web\AssetBundle[] $bundles asset bundles list.
     * @param string $name bundle name.
     * @param array $registered stores already registered names.
     * @throws Exception if circular dependency is detected.
     */
    protected function register_bundle(array $bundles, $name, array &$registered)
    {
        if (!isset($registered[$name])) {
            $registered[$name] = false;
            $bundle = $bundles[$name];
            foreach ($bundle->depends as $depend) {
                $this->register_bundle($bundles, $depend, $registered);
            }
            unset($registered[$name]);
            $registered[$name] = $bundle;
        } elseif ($registered[$name] === false) {
            throw new Exception("A circular dependency is detected for target '{$name}': " . $this->compose_circular_dependency_trace($name, $registered) . '.');
        }
    }
    /**
     * Saves new asset bundles configuration.
     * @param \yii\web\AssetBundle[] $targets list of asset bundles to be saved.
     * @param string $bundleFile output file name.
     * @throws \yii\console\Exception on failure.
     */
    protected function save_targets($targets, $bundle_file)
    {
        $array = [];
        foreach ($targets as $name => $target) {
            if (isset($this->targets[$name])) {
                $array[$name] = array_merge($this->targets[$name], ['class' => get_class($target), 'sourcePath' => null, 'basePath' => $this->targets[$name]['basePath'], 'baseUrl' => $this->targets[$name]['baseUrl'], 'js' => $target->js, 'css' => $target->css, 'depends' => []]);
            } else if ($this->is_bundle_external($target)) {
                $array[$name] = $this->compose_bundle_config($target);
            } else {
                $array[$name] = ['sourcePath' => null, 'js' => [], 'css' => [], 'depends' => $target->depends];
            }
        }
        $array = Var_Dumper::export($array);
        $version = date('Y-m-d H:i:s');
        $bundle_file_content = <<<EOD
        <?php
        /**
         * This file is generated by the "yii {$this->id}" command.
         * DO NOT MODIFY THIS FILE DIRECTLY.
         * @version {$version}
         */
        return {$array};
        EOD;
        if (!file_put_contents($bundle_file, $bundle_file_content, LOCK_EX)) {
            throw new Exception("Unable to write output bundle configuration at '{$bundle_file}'.");
        }
        $this->stdout("Output bundle configuration created at '{$bundle_file}'.\n", Console::FG_GREEN);
    }
    /**
     * Compresses given JavaScript files and combines them into the single one.
     * @param array $inputFiles list of source file names.
     * @param string $outputFile output file name.
     * @throws \yii\console\Exception on failure
     */
    protected function compress_js_files($input_files, string $output_file)
    {
        if (empty($input_files)) {
            return;
        }
        $this->stdout("  Compressing JavaScript files...\n");
        if (is_string($this->js_compressor)) {
            $tmp_file = $output_file . '.tmp';
            $this->combine_js_files($input_files, $tmp_file);
            $this->stdout((string) shell_exec(strtr($this->js_compressor, ['{from}' => escapeshellarg($tmp_file), '{to}' => escapeshellarg($output_file)])));
            @unlink($tmp_file);
        } else {
            call_user_func($this->js_compressor, $this, $input_files, $output_file);
        }
        if (!file_exists($output_file)) {
            throw new Exception("Unable to compress JavaScript files into '{$output_file}'.");
        }
        $this->stdout("  JavaScript files compressed into '{$output_file}'.\n");
    }
    /**
     * Compresses given CSS files and combines them into the single one.
     * @param array $inputFiles list of source file names.
     * @param string $outputFile output file name.
     * @throws \yii\console\Exception on failure
     */
    protected function compress_css_files($input_files, string $output_file)
    {
        if (empty($input_files)) {
            return;
        }
        $this->stdout("  Compressing CSS files...\n");
        if (is_string($this->css_compressor)) {
            $tmp_file = $output_file . '.tmp';
            $this->combine_css_files($input_files, $tmp_file);
            $this->stdout((string) shell_exec(strtr($this->css_compressor, ['{from}' => escapeshellarg($tmp_file), '{to}' => escapeshellarg($output_file)])));
            @unlink($tmp_file);
        } else {
            call_user_func($this->css_compressor, $this, $input_files, $output_file);
        }
        if (!file_exists($output_file)) {
            throw new Exception("Unable to compress CSS files into '{$output_file}'.");
        }
        $this->stdout("  CSS files compressed into '{$output_file}'.\n");
    }
    /**
     * Combines JavaScript files into a single one.
     * @param array $inputFiles source file names.
     * @param string $outputFile output file name.
     * @throws \yii\console\Exception on failure.
     */
    public function combine_js_files($input_files, $output_file): void
    {
        $content = '';
        foreach ($input_files as $file) {
            // Add a semicolon to source code if trailing semicolon missing.
            // Notice: It needs a new line before `;` to avoid affection of line comment. (// ...;)
            $file_content = rtrim(file_get_contents($file));
            if (substr($file_content, -1) !== ';') {
                $file_content .= "\n;";
            }
            $content .= "/*** BEGIN FILE: {$file} ***/\n" . $file_content . "\n" . "/*** END FILE: {$file} ***/\n";
        }
        if (!file_put_contents($output_file, $content)) {
            throw new Exception("Unable to write output JavaScript file '{$output_file}'.");
        }
    }
    /**
     * Combines CSS files into a single one.
     * @param array $inputFiles source file names.
     * @param string $outputFile output file name.
     * @throws \yii\console\Exception on failure.
     */
    public function combine_css_files($input_files, $output_file): void
    {
        $content = '';
        $output_file_path = dirname($this->find_real_path($output_file));
        foreach ($input_files as $file) {
            $content .= "/*** BEGIN FILE: {$file} ***/\n" . $this->adjust_css_url(file_get_contents($file), dirname($this->find_real_path($file)), $output_file_path) . "/*** END FILE: {$file} ***/\n";
        }
        if (!file_put_contents($output_file, $content)) {
            throw new Exception("Unable to write output CSS file '{$output_file}'.");
        }
    }
    /**
     * Adjusts CSS content allowing URL references pointing to the original resources.
     * @param string $cssContent source CSS content.
     * @param string $inputFilePath input CSS file name.
     * @param string $outputFilePath output CSS file name.
     * @return string adjusted CSS content.
     */
    protected function adjust_css_url($css_content, $input_file_path, $output_file_path): ?string
    {
        $input_file_path = str_replace('\\', '/', $input_file_path);
        $output_file_path = str_replace('\\', '/', $output_file_path);
        $shared_path_parts = [];
        $input_file_path_parts = explode('/', $input_file_path);
        $input_file_path_parts_count = count($input_file_path_parts);
        $output_file_path_parts = explode('/', $output_file_path);
        $output_file_path_parts_count = count($output_file_path_parts);
        for ($i = 0; $i < $input_file_path_parts_count && $i < $output_file_path_parts_count; $i++) {
            if ($input_file_path_parts[$i] == $output_file_path_parts[$i]) {
                $shared_path_parts[] = $input_file_path_parts[$i];
            } else {
                break;
            }
        }
        $shared_path = implode('/', $shared_path_parts);
        $input_file_relative_path = trim(str_replace($shared_path, '', $input_file_path), '/');
        $output_file_relative_path = trim(str_replace($shared_path, '', $output_file_path), '/');
        if (empty($input_file_relative_path)) {
            $input_file_relative_path_parts = [];
        } else {
            $input_file_relative_path_parts = explode('/', $input_file_relative_path);
        }
        if (empty($output_file_relative_path)) {
            $output_file_relative_path_parts = [];
        } else {
            $output_file_relative_path_parts = explode('/', $output_file_relative_path);
        }
        $callback = function ($matches) use ($input_file_relative_path_parts, $output_file_relative_path_parts) {
            $full_match = $matches[0];
            $input_url = $matches[1];
            if (strncmp($input_url, '/', 1) === 0 || strncmp($input_url, '#', 1) === 0 || preg_match('/^https?:\/\//i', $input_url) || preg_match('/^data:/i', $input_url)) {
                return $full_match;
            }
            if ($input_file_relative_path_parts === $output_file_relative_path_parts) {
                return $full_match;
            }
            if (empty($output_file_relative_path_parts)) {
                $output_url_parts = [];
            } else {
                $output_url_parts = array_fill(0, count($output_file_relative_path_parts), '..');
            }
            $output_url_parts = array_merge($output_url_parts, $input_file_relative_path_parts);
            if (strpos($input_url, '/') !== false) {
                $input_url_parts = explode('/', $input_url);
                foreach ($input_url_parts as $key => $input_url_part) {
                    if ($input_url_part === '..') {
                        array_pop($output_url_parts);
                        unset($input_url_parts[$key]);
                    }
                }
                $output_url_parts[] = implode('/', $input_url_parts);
            } else {
                $output_url_parts[] = $input_url;
            }
            $output_url = implode('/', $output_url_parts);
            return str_replace($input_url, $output_url, $full_match);
        };
        return preg_replace_callback('/url\(["\']?([^)^"\']*)["\']?\)/i', $callback, $css_content);
    }
    /**
     * Creates template of configuration file for [[actionCompress]].
     * @param string $configFile output file name.
     * @return int CLI exit code
     * @throws \yii\console\Exception on failure.
     */
    public function action_template($config_file): int
    {
        $js_compressor = Var_Dumper::export($this->js_compressor);
        $css_compressor = Var_Dumper::export($this->css_compressor);
        $template = <<<EOD
        <?php
        /**
         * Configuration file for the "yii asset" console command.
         */
        
        // In the console environment, some path aliases may not exist. Please define these:
        // Yii::setAlias('@webroot', __DIR__ . '/../web');
        // Yii::setAlias('@web', '/');
        
        return [
            // Adjust command/callback for JavaScript files compressing:
            'jsCompressor' => {$js_compressor},
            // Adjust command/callback for CSS files compressing:
            'cssCompressor' => {$css_compressor},
            // Whether to delete asset source after compression:
            'deleteSource' => false,
            // The list of asset bundles to compress:
            'bundles' => [
                // 'app\\assets\\AppAsset',
                // 'yii\\web\\YiiAsset',
                // 'yii\\web\\JqueryAsset',
            ],
            // Asset bundle for compression output:
            'targets' => [
                'all' => [
                    'class' => 'yii\\web\\AssetBundle',
                    'basePath' => '@webroot/assets',
                    'baseUrl' => '@web/assets',
                    'js' => 'js/all-{hash}.js',
                    'css' => 'css/all-{hash}.css',
                ],
            ],
            // Asset manager configuration:
            'assetManager' => [
                //'basePath' => '@webroot/assets',
                //'baseUrl' => '@web/assets',
            ],
        ];
        EOD;
        if (file_exists($config_file)) {
            if (!$this->confirm("File '{$config_file}' already exists. Do you wish to overwrite it?")) {
                return Exit_Code::OK;
            }
        }
        if (!file_put_contents($config_file, $template, LOCK_EX)) {
            throw new Exception("Unable to write template file '{$config_file}'.");
        }
        $this->stdout("Configuration file template created at '{$config_file}'.\n\n", Console::FG_GREEN);
        return Exit_Code::OK;
    }
    /**
     * Returns canonicalized absolute pathname.
     * Unlike regular `realpath()` this method does not expand symlinks and does not check path existence.
     * @param string $path raw path
     * @return string canonicalized absolute pathname
     */
    private function find_real_path($path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path_parts = explode(DIRECTORY_SEPARATOR, $path);
        $real_path_parts = [];
        foreach ($path_parts as $path_part) {
            if ($path_part === '..') {
                array_pop($real_path_parts);
            } else {
                $real_path_parts[] = $path_part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $real_path_parts);
    }
    /**
     * @param AssetBundle $bundle
     * @return bool whether asset bundle external or not.
     */
    private function is_bundle_external($bundle): bool
    {
        return empty($bundle->source_path) && empty($bundle->base_path);
    }
    /**
     * @param AssetBundle $bundle asset bundle instance.
     * @return array bundle configuration.
     */
    private function compose_bundle_config($bundle)
    {
        $config = Yii::get_object_vars($bundle);
        $config['class'] = get_class($bundle);
        return $config;
    }
    /**
     * Composes trace info for bundle circular dependency.
     * @param string $circularDependencyName name of the bundle, which have circular dependency
     * @param array $registered list of bundles registered while detecting circular dependency.
     * @return string bundle circular dependency trace string.
     */
    private function compose_circular_dependency_trace($circular_dependency_name, array $registered): string
    {
        $dependency_trace = [];
        $start_found = false;
        foreach ($registered as $name => $value) {
            if ($name === $circular_dependency_name) {
                $start_found = true;
            }
            if ($start_found && $value === false) {
                $dependency_trace[] = $name;
            }
        }
        $dependency_trace[] = $circular_dependency_name;
        return implode(' -> ', $dependency_trace);
    }
    /**
     * Deletes bundle asset files, which have been published from `sourcePath`.
     * @param \yii\web\AssetBundle[] $bundles asset bundles to be processed.
     * @since 2.0.10
     */
    private function delete_published_assets($bundles): void
    {
        $this->stdout("Deleting source files...\n");
        if ($this->get_asset_manager()->link_assets) {
            $this->stdout("`AssetManager::linkAssets` option is enabled. Deleting of source files canceled.\n", Console::FG_YELLOW);
            return;
        }
        foreach ($bundles as $bundle) {
            if ($bundle->source_path !== null) {
                foreach ($bundle->js as $js_file) {
                    @unlink($bundle->base_path . DIRECTORY_SEPARATOR . $js_file);
                }
                foreach ($bundle->css as $css_file) {
                    @unlink($bundle->base_path . DIRECTORY_SEPARATOR . $css_file);
                }
            }
        }
        $this->stdout("Source files deleted.\n", Console::FG_GREEN);
    }
}