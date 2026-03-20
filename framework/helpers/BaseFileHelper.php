<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\Invalid_Config_Exception;
/**
 * BaseFileHelper provides concrete implementation for [[FileHelper]].
 *
 * Do not use BaseFileHelper. Use [[FileHelper]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alex Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class Base_File_Helper
{
    public const PATTERN_NODIR = 1;
    public const PATTERN_ENDSWITH = 4;
    public const PATTERN_MUSTBEDIR = 8;
    public const PATTERN_NEGATIVE = 16;
    public const PATTERN_CASE_INSENSITIVE = 32;
    /**
     * @var string the path (or alias) of a PHP file containing MIME type information.
     */
    public static $mime_magic_file = '@yii/helpers/mimeTypes.php';
    /**
     * @var string the path (or alias) of a PHP file containing MIME aliases.
     * @since 2.0.14
     */
    public static $mime_aliases_file = '@yii/helpers/mimeAliases.php';
    /**
     * @var string the path (or alias) of a PHP file containing extensions per MIME type.
     * @since 2.0.48
     */
    public static $mime_extensions_file = '@yii/helpers/mimeExtensions.php';
    /**
     * Normalizes a file/directory path.
     *
     * The normalization does the following work:
     *
     * - Convert all directory separators into `DIRECTORY_SEPARATOR` (e.g. "\a/b\c" becomes "/a/b/c")
     * - Remove trailing directory separators (e.g. "/a/b/c/" becomes "/a/b/c")
     * - Turn multiple consecutive slashes into a single one (e.g. "/a///b/c" becomes "/a/b/c")
     * - Remove ".." and "." based on their meanings (e.g. "/a/./b/../c" becomes "/a/c")
     *
     * Note: For registered stream wrappers, the consecutive slashes rule
     * and ".."/"." translations are skipped.
     *
     * @param string $path the file/directory path to be normalized
     * @param string $ds the directory separator to be used in the normalized result. Defaults to `DIRECTORY_SEPARATOR`.
     * @return string the normalized file/directory path
     */
    public static function normalize_path($path, string $ds = DIRECTORY_SEPARATOR): string
    {
        $path = rtrim(strtr($path, '/\\', $ds . $ds), $ds);
        if (strpos($ds . $path, "{$ds}.") === false && strpos($path, "{$ds}{$ds}") === false) {
            return $path;
        }
        // fix #17235 stream wrappers
        foreach (stream_get_wrappers() as $protocol) {
            if (strpos($path, "{$protocol}://") === 0) {
                return $path;
            }
        }
        // the path may contain ".", ".." or double slashes, need to clean them up
        if (strpos($path, "{$ds}{$ds}") === 0 && $ds == '\\') {
            $parts = [$ds];
        } else {
            $parts = [];
        }
        foreach (explode($ds, $path) as $part) {
            if ($part === '..' && !empty($parts) && end($parts) !== '..') {
                array_pop($parts);
            } elseif ($part === '.' || $part === '' && !empty($parts)) {
                continue;
            } else {
                $parts[] = $part;
            }
        }
        $path = implode($ds, $parts);
        return $path === '' ? '.' : $path;
    }
    /**
     * Returns the localized version of a specified file.
     *
     * The searching is based on the specified language code. In particular,
     * a file with the same name will be looked for under the subdirectory
     * whose name is the same as the language code. For example, given the file "path/to/view.php"
     * and language code "zh-CN", the localized file will be looked for as
     * "path/to/zh-CN/view.php". If the file is not found, it will try a fallback with just a language code that is
     * "zh" i.e. "path/to/zh/view.php". If it is not found as well the original file will be returned.
     *
     * If the target and the source language codes are the same, the original file will be returned.
     *
     * @param string $file the original file
     * @param string|null $language the target language that the file should be localized to.
     * If not set, the value of [[\yii\base\Application::language]] will be used.
     * @param string|null $sourceLanguage the language that the original file is in.
     * If not set, the value of [[\yii\base\Application::sourceLanguage]] will be used.
     * @return string the matching localized file, or the original file if the localized version is not found.
     * If the target and the source language codes are the same, the original file will be returned.
     */
    public static function localize($file, $language = null, $source_language = null)
    {
        if ($language === null) {
            $language = Yii::$app->language;
        }
        if ($source_language === null) {
            $source_language = Yii::$app->source_language;
        }
        if ($language === $source_language) {
            return $file;
        }
        $desired_file = dirname($file) . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . basename($file);
        if (is_file($desired_file)) {
            return $desired_file;
        }
        $language = substr($language, 0, 2);
        if ($language === $source_language) {
            return $file;
        }
        $desired_file = dirname($file) . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . basename($file);
        return is_file($desired_file) ? $desired_file : $file;
    }
    /**
     * Determines the MIME type of the specified file.
     * This method will first try to determine the MIME type based on
     * [finfo_open](https://www.php.net/manual/en/function.finfo-open.php). If the `fileinfo` extension is not installed,
     * it will fall back to [[getMimeTypeByExtension()]] when `$checkExtension` is true.
     * @param string $file the file name.
     * @param string|null $magicFile name of the optional magic database file (or alias), usually something like `/path/to/magic.mime`.
     * This will be passed as the second parameter to [finfo_open()](https://www.php.net/manual/en/function.finfo-open.php)
     * when the `fileinfo` extension is installed. If the MIME type is being determined based via [[getMimeTypeByExtension()]]
     * and this is null, it will use the file specified by [[mimeMagicFile]].
     * @param bool $checkExtension whether to use the file extension to determine the MIME type in case
     * `finfo_open()` cannot determine it.
     * @return string|null the MIME type (e.g. `text/plain`). Null is returned if the MIME type cannot be determined.
     * @throws InvalidConfigException when the `fileinfo` PHP extension is not installed and `$checkExtension` is `false`.
     */
    public static function get_mime_type($file, $magic_file = null, $check_extension = true)
    {
        if ($magic_file !== null) {
            $magic_file = Yii::get_alias($magic_file);
        }
        if (!extension_loaded('fileinfo')) {
            if ($check_extension) {
                return static::get_mime_type_by_extension($file, $magic_file);
            }
            throw new Invalid_Config_Exception('The fileinfo PHP extension is not installed.');
        }
        $info = finfo_open(FILEINFO_MIME_TYPE, $magic_file);
        if ($info) {
            $result = finfo_file($info, $file);
            // @link https://wiki.php.net/rfc/deprecations_php_8_5#deprecate_finfo_close
            // @link https://github.com/php/php-src/commit/ccb716dcadf60d989db48e4d2963e14d7dc63df3
            if (PHP_VERSION_ID < 80500) {
                finfo_close($info);
            }
            if ($result !== false) {
                return $result;
            }
        }
        return $check_extension ? static::get_mime_type_by_extension($file, $magic_file) : null;
    }
    /**
     * Determines the MIME type based on the extension name of the specified file.
     * This method will use a local map between extension names and MIME types.
     * @param string $file the file name.
     * @param string|null $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return string|null the MIME type. Null is returned if the MIME type cannot be determined.
     */
    public static function get_mime_type_by_extension($file, $magic_file = null)
    {
        $mime_types = static::load_mime_types($magic_file);
        if (($ext = pathinfo($file, PATHINFO_EXTENSION)) !== '') {
            $ext = strtolower($ext);
            if (isset($mime_types[$ext])) {
                return $mime_types[$ext];
            }
        }
        return null;
    }
    /**
     * Determines the extensions by given MIME type.
     * This method will use a local map between extension names and MIME types.
     * @param string $mimeType file MIME type.
     * @param string|null $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the extensions corresponding to the specified MIME type
     */
    public static function get_extensions_by_mime_type($mime_type, $magic_file = null): array
    {
        $aliases = static::load_mime_aliases(static::$mime_aliases_file);
        if (isset($aliases[$mime_type])) {
            $mime_type = $aliases[$mime_type];
        }
        // Note: For backwards compatibility the "MimeTypes" file is used.
        $mime_types = static::load_mime_types($magic_file);
        return array_keys($mime_types, mb_strtolower($mime_type, 'UTF-8'), true);
    }
    /**
     * Determines the most common extension by given MIME type.
     * This method will use a local map between MIME types and extension names.
     * @param string $mimeType file MIME type.
     * @param bool $preferShort return an extension with a maximum of 3 characters.
     * @param string|null $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return string|null the extensions corresponding to the specified MIME type
     * @since 2.0.48
     */
    public static function get_extension_by_mime_type($mime_type, $prefer_short = false, $magic_file = null)
    {
        $aliases = static::load_mime_aliases(static::$mime_aliases_file);
        if (isset($aliases[$mime_type])) {
            $mime_type = $aliases[$mime_type];
        }
        $mime_extensions = static::load_mime_extensions($magic_file);
        if (!array_key_exists($mime_type, $mime_extensions)) {
            return null;
        }
        $extensions = $mime_extensions[$mime_type];
        if (is_array($extensions)) {
            if ($prefer_short) {
                foreach ($extensions as $extension) {
                    if (mb_strlen($extension, 'UTF-8') <= 3) {
                        return $extension;
                    }
                }
            }
            return $extensions[0];
        }
        return $extensions;
    }
    private static array $_mime_types = [];
    /**
     * Loads MIME types from the specified file.
     * @param string|null $magicFile the path (or alias) of the file that contains all available MIME type information.
     * If this is not set, the file specified by [[mimeMagicFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     */
    protected static function load_mime_types($magic_file)
    {
        if ($magic_file === null) {
            $magic_file = static::$mime_magic_file;
        }
        $magic_file = Yii::get_alias($magic_file);
        if (!isset(self::$_mime_types[$magic_file])) {
            self::$_mime_types[$magic_file] = require $magic_file;
        }
        return self::$_mime_types[$magic_file];
    }
    private static array $_mime_aliases = [];
    /**
     * Loads MIME aliases from the specified file.
     * @param string|null $aliasesFile the path (or alias) of the file that contains MIME type aliases.
     * If this is not set, the file specified by [[mimeAliasesFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     * @since 2.0.14
     */
    protected static function load_mime_aliases($aliases_file)
    {
        if ($aliases_file === null) {
            $aliases_file = static::$mime_aliases_file;
        }
        $aliases_file = Yii::get_alias($aliases_file);
        if (!isset(self::$_mime_aliases[$aliases_file])) {
            self::$_mime_aliases[$aliases_file] = require $aliases_file;
        }
        return self::$_mime_aliases[$aliases_file];
    }
    private static array $_mime_extensions = [];
    /**
     * Loads MIME extensions from the specified file.
     * @param string|null $extensionsFile the path (or alias) of the file that contains MIME type aliases.
     * If this is not set, the file specified by [[mimeAliasesFile]] will be used.
     * @return array the mapping from file extensions to MIME types
     * @since 2.0.48
     */
    protected static function load_mime_extensions($extensions_file)
    {
        if ($extensions_file === null) {
            $extensions_file = static::$mime_extensions_file;
        }
        $extensions_file = Yii::get_alias($extensions_file);
        if (!isset(self::$_mime_extensions[$extensions_file])) {
            self::$_mime_extensions[$extensions_file] = require $extensions_file;
        }
        return self::$_mime_extensions[$extensions_file];
    }
    /**
     * Copies a whole directory as another one.
     * The files and sub-directories will also be copied over.
     * @param string $src the source directory
     * @param string $dst the destination directory
     * @param array $options options for directory copy. Valid options are:
     *
     * - dirMode: integer, the permission to be set for newly copied directories. Defaults to 0775.
     * - fileMode:  integer, the permission to be set for newly copied files. Defaults to the current environment setting.
     * - filter: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * true: the directory or file will be copied (the "only" and "except" options will be ignored)
     *   * false: the directory or file will NOT be copied (the "only" and "except" options will be ignored)
     *   * null: the "only" and "except" options will determine whether the directory or file should be copied
     *
     * - only: array, list of patterns that the file paths should match if they want to be copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   For example, '.php' matches all file paths ending with '.php'.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     *   If a file path matches a pattern in both "only" and "except", it will NOT be copied.
     * - except: array, list of patterns that the files or directories should match if they want to be excluded from being copied.
     *   A path matches a pattern if it contains the pattern string at its end.
     *   Patterns ending with '/' apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and '.svn/' matches directory paths ending with '.svn'. Note, the '/' characters in a pattern matches
     *   both '/' and '\' in the paths.
     * - caseSensitive: boolean, whether patterns specified at "only" or "except" should be case sensitive. Defaults to true.
     * - recursive: boolean, whether the files under the subdirectories should also be copied. Defaults to true.
     * - beforeCopy: callback, a PHP callback that is called before copying each sub-directory or file.
     *   If the callback returns false, the copy operation for the sub-directory or file will be cancelled.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file to be copied from, while `$to` is the copy target.
     * - afterCopy: callback, a PHP callback that is called after each sub-directory or file is successfully copied.
     *   The signature of the callback should be: `function ($from, $to)`, where `$from` is the sub-directory or
     *   file copied from, while `$to` is the copy target.
     * - copyEmptyDirectories: boolean, whether to copy empty directories. Set this to false to avoid creating directories
     *   that do not contain files. This affects directories that do not contain files initially as well as directories that
     *   do not contain files at the target destination because files have been filtered via `only` or `except`.
     *   Defaults to true. This option is available since version 2.0.12. Before 2.0.12 empty directories are always copied.
     * @throws InvalidArgumentException if unable to open directory
     */
    public static function copy_directory($src, $dst, $options = []): void
    {
        $src = static::normalize_path($src);
        $dst = static::normalize_path($dst);
        if ($src === $dst || strpos($dst, $src . DIRECTORY_SEPARATOR) === 0) {
            throw new InvalidArgumentException('Trying to copy a directory to itself or a subdirectory.');
        }
        $dst_exists = is_dir($dst);
        if (!$dst_exists && (!isset($options['copyEmptyDirectories']) || $options['copyEmptyDirectories'])) {
            static::create_directory($dst, $options['dirMode'] ?? 0775, true);
            $dst_exists = true;
        }
        $handle = opendir($src);
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open directory: {$src}");
        }
        if (!isset($options['basePath'])) {
            // this should be done only once
            $options['basePath'] = realpath($src);
            $options = static::normalize_options($options);
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.') {
                continue;
            }
            if ($file === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to = $dst . DIRECTORY_SEPARATOR . $file;
            if (static::filter_path($from, $options)) {
                if (isset($options['beforeCopy']) && !call_user_func($options['beforeCopy'], $from, $to)) {
                    continue;
                }
                if (is_file($from)) {
                    if (!$dst_exists) {
                        // delay creation of destination directory until the first file is copied to avoid creating empty directories
                        static::create_directory($dst, $options['dirMode'] ?? 0775, true);
                        $dst_exists = true;
                    }
                    copy($from, $to);
                    if (isset($options['fileMode'])) {
                        @chmod($to, $options['fileMode']);
                    }
                } else if (!isset($options['recursive']) || $options['recursive']) {
                    static::copy_directory($from, $to, $options);
                }
                if (isset($options['afterCopy'])) {
                    call_user_func($options['afterCopy'], $from, $to);
                }
            }
        }
        closedir($handle);
    }
    /**
     * Removes a directory (and all its content) recursively.
     *
     * @param string $dir the directory to be deleted recursively.
     * @param array $options options for directory remove. Valid options are:
     *
     * - traverseSymlinks: boolean, whether symlinks to the directories should be traversed too.
     *   Defaults to `false`, meaning the content of the symlinked directory would not be deleted.
     *   Only symlink would be removed in that default case.
     *
     * @throws ErrorException in case of failure
     */
    public static function remove_directory(string $dir, array $options = []): void
    {
        if (!is_dir($dir)) {
            return;
        }
        if (!empty($options['traverseSymlinks']) || !is_link($dir)) {
            if (!$handle = opendir($dir)) {
                return;
            }
            while (($file = readdir($handle)) !== false) {
                if ($file === '.') {
                    continue;
                }
                if ($file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    static::remove_directory($path, $options);
                } else {
                    static::unlink($path);
                }
            }
            closedir($handle);
        }
        if (is_link($dir)) {
            static::unlink($dir);
        } else {
            rmdir($dir);
        }
    }
    /**
     * Removes a file or symlink in a cross-platform way
     *
     * @param string $path
     *
     * @since 2.0.14
     */
    public static function unlink($path): bool
    {
        $is_windows = DIRECTORY_SEPARATOR === '\\';
        if (!$is_windows) {
            return unlink($path);
        }
        if (is_link($path) && is_dir($path)) {
            return rmdir($path);
        }
        try {
            return unlink($path);
        } catch (ErrorException $e) {
            // last resort measure for Windows
            if (is_dir($path) && count(static::find_files($path)) !== 0) {
                return false;
            }
            if (function_exists('exec') && file_exists($path)) {
                exec('DEL /F/Q ' . escapeshellarg($path));
                return !file_exists($path);
            }
            return false;
        }
    }
    /**
     * Returns the files found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for file searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function ($path)`, where `$path` refers the full path to be filtered.
     *   The callback can return one of the following values:
     *
     *   * `true`: the directory or file will be returned (the `only` and `except` options will be ignored)
     *   * `false`: the directory or file will NOT be returned (the `only` and `except` options will be ignored)
     *   * `null`: the `only` and `except` options will determine whether the directory or file should be returned
     *
     * - `except`: array, list of patterns excluding from the results matching file or directory paths.
     *   Patterns ending with slash ('/') apply to directory paths only, and patterns not ending with '/'
     *   apply to file paths only. For example, '/a/b' matches all file paths ending with '/a/b';
     *   and `.svn/` matches directory paths ending with `.svn`.
     *   If the pattern does not contain a slash (`/`), it is treated as a shell glob pattern
     *   and checked for a match against the pathname relative to `$dir`.
     *   Otherwise, the pattern is treated as a shell glob suitable for consumption by `fnmatch(3)`
     *   with the `FNM_PATHNAME` flag: wildcards in the pattern will not match a `/` in the pathname.
     *   For example, `views/*.php` matches `views/index.php` but not `views/controller/index.php`.
     *   A leading slash matches the beginning of the pathname. For example, `/*.php` matches `index.php` but not `views/start/index.php`.
     *   An optional prefix `!` which negates the pattern; any matching file excluded by a previous pattern will become included again.
     *   If a negated pattern matches, this will override lower precedence patterns sources. Put a backslash (`\`) in front of the first `!`
     *   for patterns that begin with a literal `!`, for example, `\!important!.txt`.
     *   Note, the '/' characters in a pattern matches both '/' and '\' in the paths.
     *   You can find more details about the gitignore pattern format [here](https://git-scm.com/docs/gitignore/en#_pattern_format).
     * - `only`: array, list of patterns that the file paths should match if they are to be returned. Directory paths
     *   are not checked against them. Same pattern matching rules as in the `except` option are used.
     *   If a file path matches a pattern in both `only` and `except`, it will NOT be returned.
     * - `caseSensitive`: boolean, whether patterns specified at `only` or `except` should be case sensitive. Defaults to `true`.
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     * @return array files found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     */
    public static function find_files($dir, $options = []): array
    {
        $dir = self::clear_dir($dir);
        $options = self::set_base_path($dir, $options);
        $list = [];
        $handle = self::open_dir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.') {
                continue;
            }
            if ($file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (static::filter_path($path, $options)) {
                if (is_file($path)) {
                    $list[] = $path;
                } elseif (is_dir($path) && (!isset($options['recursive']) || $options['recursive'])) {
                    $list = array_merge($list, static::find_files($path, $options));
                }
            }
        }
        closedir($handle);
        return $list;
    }
    /**
     * Returns the directories found under the specified directory and subdirectories.
     * @param string $dir the directory under which the files will be looked for.
     * @param array $options options for directory searching. Valid options are:
     *
     * - `filter`: callback, a PHP callback that is called for each directory or file.
     *   The signature of the callback should be: `function (string $path): bool`, where `$path` refers
     *   the full path to be filtered. The callback can return one of the following values:
     *
     *   * `true`: the directory will be returned
     *   * `false`: the directory will NOT be returned
     *
     * - `recursive`: boolean, whether the files under the subdirectories should also be looked for. Defaults to `true`.
     *   See [[findFiles()]] for more options.
     * @return array directories found under the directory, in no particular order. Ordering depends on the files system used.
     * @throws InvalidArgumentException if the dir is invalid.
     * @since 2.0.14
     */
    public static function find_directories($dir, $options = []): array
    {
        $dir = self::clear_dir($dir);
        $options = self::set_base_path($dir, $options);
        $list = [];
        $handle = self::open_dir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file === '.') {
                continue;
            }
            if ($file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path) && static::filter_path($path, $options)) {
                $list[] = $path;
                if (!isset($options['recursive']) || $options['recursive']) {
                    $list = array_merge($list, static::find_directories($path, $options));
                }
            }
        }
        closedir($handle);
        return $list;
    }
    /**
     * @param string $dir
     * @param array $options
     * @return array
     */
    private static function set_base_path($dir, $options)
    {
        if (!isset($options['basePath'])) {
            // this should be done only once
            $options['basePath'] = realpath($dir);
            $options = static::normalize_options($options);
        }
        return $options;
    }
    /**
     * @param string $dir
     * @return resource
     * @throws InvalidArgumentException if unable to open directory
     */
    private static function open_dir($dir)
    {
        $handle = opendir($dir);
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open directory: {$dir}");
        }
        return $handle;
    }
    /**
     * @param string $dir
     * @throws InvalidArgumentException if directory not exists
     */
    private static function clear_dir($dir): string
    {
        if (!is_dir($dir)) {
            throw new InvalidArgumentException("The dir argument must be a directory: {$dir}");
        }
        return rtrim($dir, '\/');
    }
    /**
     * Checks if the given file path satisfies the filtering options.
     * @param string $path the path of the file or directory to be checked
     * @param array $options the filtering options. See [[findFiles()]] for explanations of
     * the supported options.
     * @return bool whether the file or directory satisfies the filtering options.
     */
    public static function filter_path($path, array $options)
    {
        if (isset($options['filter'])) {
            $result = call_user_func($options['filter'], $path);
            if (is_bool($result)) {
                return $result;
            }
        }
        if (empty($options['except']) && empty($options['only'])) {
            return true;
        }
        $path = str_replace('\\', '/', $path);
        if (!empty($options['except']) && ($except = self::last_exclude_matching_from_list($options['basePath'], $path, $options['except'])) !== null) {
            return $except['flags'] & self::PATTERN_NEGATIVE;
        }
        if (!empty($options['only']) && !is_dir($path)) {
            return self::last_exclude_matching_from_list($options['basePath'], $path, $options['only']) !== null;
        }
        return true;
    }
    /**
     * Creates a new directory.
     *
     * This method is similar to the PHP `mkdir()` function except that
     * it uses `chmod()` to set the permission of the created directory
     * in order to avoid the impact of the `umask` setting.
     *
     * @param string $path path of the directory to be created.
     * @param int $mode the permission to be set for the created directory.
     * @param bool $recursive whether to create parent directories if they do not exist.
     * @return bool whether the directory is created successfully
     * @throws \yii\base\Exception if the directory could not be created (i.e. php error due to parallel changes)
     */
    public static function create_directory($path, $mode = 0775, $recursive = true)
    {
        if (is_dir($path)) {
            return true;
        }
        $parent_dir = dirname($path);
        // recurse if parent dir does not exist and we are not at the root of the file system.
        if ($recursive && !is_dir($parent_dir) && $parent_dir !== $path) {
            static::create_directory($parent_dir, $mode, true);
        }
        try {
            if (!mkdir($path, $mode)) {
                return false;
            }
        } catch (\Exception $e) {
            if (!is_dir($path)) {
                // https://github.com/yiisoft/yii2/issues/9288
                throw new \yii\base\Exception("Failed to create directory \"{$path}\": " . $e->get_message(), $e->get_code(), $e);
            }
        }
        try {
            return chmod($path, $mode);
        } catch (\Exception $e) {
            throw new \yii\base\Exception("Failed to change permissions for directory \"{$path}\": " . $e->get_message(), $e->get_code(), $e);
        }
    }
    /**
     * Performs a simple comparison of file or directory names.
     *
     * Based on match_basename() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $baseName file or directory name to compare with the pattern
     * @param string $pattern the pattern that $baseName will be compared against
     * @param int|bool $firstWildcard location of first wildcard character in the $pattern
     * @param int $flags pattern flags
     * @return bool whether the name matches against pattern
     */
    private static function match_basename(string $base_name, $pattern, $first_wildcard, $flags)
    {
        if ($first_wildcard === false) {
            if ($pattern === $base_name) {
                return true;
            }
        } elseif ($flags & self::PATTERN_ENDSWITH) {
            /* "*literal" matching against "fooliteral" */
            $n = String_Helper::byte_length($pattern);
            if (String_Helper::byte_substr($pattern, 1, $n) === String_Helper::byte_substr($base_name, -$n, $n)) {
                return true;
            }
        }
        $match_options = [];
        if ($flags & self::PATTERN_CASE_INSENSITIVE) {
            $match_options['caseSensitive'] = false;
        }
        return String_Helper::match_wildcard($pattern, $base_name, $match_options);
    }
    /**
     * Compares a path part against a pattern with optional wildcards.
     *
     * Based on match_pathname() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $path full path to compare
     * @param string $basePath base of path that will not be compared
     * @param string $pattern the pattern that path part will be compared against
     * @param int|bool $firstWildcard location of first wildcard character in the $pattern
     * @param int $flags pattern flags
     * @return bool whether the path part matches against pattern
     */
    private static function match_pathname($path, $base_path, $pattern, $first_wildcard, $flags)
    {
        // match with FNM_PATHNAME; the pattern has base implicitly in front of it.
        if (strncmp($pattern, '/', 1) === 0) {
            $pattern = String_Helper::byte_substr($pattern, 1, String_Helper::byte_length($pattern));
            if ($first_wildcard !== false && $first_wildcard !== 0) {
                $first_wildcard--;
            }
        }
        $namelen = String_Helper::byte_length($path) - (empty($base_path) ? 0 : String_Helper::byte_length($base_path) + 1);
        $name = String_Helper::byte_substr($path, -$namelen, $namelen);
        if ($first_wildcard !== 0) {
            if ($first_wildcard === false) {
                $first_wildcard = String_Helper::byte_length($pattern);
            }
            // if the non-wildcard part is longer than the remaining pathname, surely it cannot match.
            if ($first_wildcard > $namelen) {
                return false;
            }
            if (strncmp($pattern, $name, $first_wildcard)) {
                return false;
            }
            $pattern = String_Helper::byte_substr($pattern, $first_wildcard, String_Helper::byte_length($pattern));
            $name = String_Helper::byte_substr($name, $first_wildcard, $namelen);
            // If the whole pattern did not have a wildcard, then our prefix match is all we need; we do not need to call fnmatch at all.
            if (empty($pattern) && empty($name)) {
                return true;
            }
        }
        $match_options = ['filePath' => true];
        if ($flags & self::PATTERN_CASE_INSENSITIVE) {
            $match_options['caseSensitive'] = false;
        }
        return String_Helper::match_wildcard($pattern, $name, $match_options);
    }
    /**
     * Scan the given exclude list in reverse to see whether pathname
     * should be ignored.  The first match (i.e. the last on the list), if
     * any, determines the fate.  Returns the element which
     * matched, or null for undecided.
     *
     * Based on last_exclude_matching_from_list() from dir.c of git 1.8.5.3 sources.
     *
     * @param string $basePath
     * @param string $path
     * @param array $excludes list of patterns to match $path against
     * @return array|null null or one of $excludes item as an array with keys: 'pattern', 'flags'
     * @throws InvalidArgumentException if any of the exclude patterns is not a string or an array with keys: pattern, flags, firstWildcard.
     */
    private static function last_exclude_matching_from_list($base_path, $path, $excludes)
    {
        foreach (array_reverse($excludes) as $exclude) {
            if (is_string($exclude)) {
                $exclude = self::parse_exclude_pattern($exclude, false);
            }
            if (!isset($exclude['pattern']) || !isset($exclude['flags']) || !isset($exclude['firstWildcard'])) {
                throw new InvalidArgumentException('If exclude/include pattern is an array it must contain the pattern, flags and firstWildcard keys.');
            }
            if ($exclude['flags'] & self::PATTERN_MUSTBEDIR && !is_dir($path)) {
                continue;
            }
            if ($exclude['flags'] & self::PATTERN_NODIR) {
                if (self::match_basename(basename($path), $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags'])) {
                    return $exclude;
                }
                continue;
            }
            if (self::match_pathname($path, $base_path, $exclude['pattern'], $exclude['firstWildcard'], $exclude['flags'])) {
                return $exclude;
            }
        }
        return null;
    }
    /**
     * Processes the pattern, stripping special characters like / and ! from the beginning and settings flags instead.
     * @param bool $caseSensitive
     * @return array with keys: (string) pattern, (int) flags, (int|bool) firstWildcard
     * @throws InvalidArgumentException
     */
    private static function parse_exclude_pattern(string $pattern, $case_sensitive): array
    {
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Exclude/include pattern must be a string.');
        }
        $result = ['pattern' => $pattern, 'flags' => 0, 'firstWildcard' => false];
        if (!$case_sensitive) {
            $result['flags'] |= self::PATTERN_CASE_INSENSITIVE;
        }
        if (empty($pattern)) {
            return $result;
        }
        if (strncmp($pattern, '!', 1) === 0) {
            $result['flags'] |= self::PATTERN_NEGATIVE;
            $pattern = String_Helper::byte_substr($pattern, 1, String_Helper::byte_length($pattern));
        }
        if (String_Helper::byte_length($pattern) && String_Helper::byte_substr($pattern, -1, 1) === '/') {
            $pattern = String_Helper::byte_substr($pattern, 0, -1);
            $result['flags'] |= self::PATTERN_MUSTBEDIR;
        }
        if (strpos($pattern, '/') === false) {
            $result['flags'] |= self::PATTERN_NODIR;
        }
        $result['firstWildcard'] = self::first_wildcard_in_pattern($pattern);
        if (strncmp($pattern, '*', 1) === 0 && self::first_wildcard_in_pattern(String_Helper::byte_substr($pattern, 1, String_Helper::byte_length($pattern))) === false) {
            $result['flags'] |= self::PATTERN_ENDSWITH;
        }
        $result['pattern'] = $pattern;
        return $result;
    }
    /**
     * Searches for the first wildcard character in the pattern.
     * @param string $pattern the pattern to search in
     * @return int|bool position of first wildcard character or false if not found
     */
    private static function first_wildcard_in_pattern($pattern)
    {
        $wildcards = ['*', '?', '[', '\\'];
        $wildcard_search = function ($r, $c) use ($pattern) {
            $p = strpos($pattern, (string) $c);
            return $r === false ? $p : ($p === false ? $r : min($r, $p));
        };
        return array_reduce($wildcards, $wildcard_search, false);
    }
    /**
     * @param array $options raw options
     * @return array normalized options
     * @since 2.0.12
     */
    protected static function normalize_options(array $options): array
    {
        if (!array_key_exists('caseSensitive', $options)) {
            $options['caseSensitive'] = true;
        }
        if (isset($options['except'])) {
            foreach ($options['except'] as $key => $value) {
                if (is_string($value)) {
                    $options['except'][$key] = self::parse_exclude_pattern($value, $options['caseSensitive']);
                }
            }
        }
        if (isset($options['only'])) {
            foreach ($options['only'] as $key => $value) {
                if (is_string($value)) {
                    $options['only'][$key] = self::parse_exclude_pattern($value, $options['caseSensitive']);
                }
            }
        }
        return $options;
    }
    /**
     * Changes the Unix user and/or group ownership of a file or directory, and optionally the mode.
     * Note: This function will not work on remote files as the file to be examined must be accessible
     * via the server's filesystem.
     * Note: On Windows, this function fails silently when applied on a regular file.
     * @param string $path the path to the file or directory.
     * @param string|array|int|null $ownership the user and/or group ownership for the file or directory.
     * When $ownership is a string, the format is 'user:group' where both are optional. E.g.
     * 'user' or 'user:' will only change the user,
     * ':group' will only change the group,
     * 'user:group' will change both.
     * When $owners is an index array the format is [0 => user, 1 => group], e.g. `[$myUser, $myGroup]`.
     * It is also possible to pass an associative array, e.g. ['user' => $myUser, 'group' => $myGroup].
     * In case $owners is an integer it will be used as user id.
     * If `null`, an empty array or an empty string is passed, the ownership will not be changed.
     * @param int|null $mode the permission to be set for the file or directory.
     * If `null` is passed, the mode will not be changed.
     *
     * @since 2.0.43
     */
    public static function change_ownership(string $path, $ownership, $mode = null): void
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException('Unable to change ownership, "' . $path . '" is not a file or directory.');
        }
        if (empty($ownership) && $ownership !== 0 && $mode === null) {
            return;
        }
        $user = $group = null;
        if (!empty($ownership) || $ownership === 0 || $ownership === '0') {
            if (is_int($ownership)) {
                $user = $ownership;
            } elseif (is_string($ownership)) {
                $owner_parts = explode(':', $ownership);
                $user = $owner_parts[0];
                if (count($owner_parts) > 1) {
                    $group = $owner_parts[1];
                }
            } elseif (is_array($ownership)) {
                $ownership_is_indexed = Array_Helper::is_indexed($ownership);
                $user = Array_Helper::get_value($ownership, $ownership_is_indexed ? 0 : 'user');
                $group = Array_Helper::get_value($ownership, $ownership_is_indexed ? 1 : 'group');
            } else {
                throw new InvalidArgumentException('$ownership must be an integer, string, array, or null.');
            }
        }
        if ($mode !== null) {
            if (!is_int($mode)) {
                throw new InvalidArgumentException('$mode must be an integer or null.');
            }
            if (!chmod($path, $mode)) {
                throw new Exception('Unable to change mode of "' . $path . '" to "0' . decoct($mode) . '".');
            }
        }
        if ($user !== null && $user !== '') {
            if (is_numeric($user)) {
                $user = (int) $user;
            } elseif (!is_string($user)) {
                throw new InvalidArgumentException('The user part of $ownership must be an integer, string, or null.');
            }
            if (!chown($path, $user)) {
                throw new Exception('Unable to change user ownership of "' . $path . '" to "' . $user . '".');
            }
        }
        if ($group !== null && $group !== '') {
            if (is_numeric($group)) {
                $group = (int) $group;
            } elseif (!is_string($group)) {
                throw new InvalidArgumentException('The group part of $ownership must be an integer, string or null.');
            }
            if (!chgrp($path, $group)) {
                throw new Exception('Unable to change group ownership of "' . $path . '" to "' . $group . '".');
            }
        }
    }
}