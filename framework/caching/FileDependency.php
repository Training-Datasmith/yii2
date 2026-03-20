<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

use Yii;
use yii\base\Invalid_Config_Exception;
/**
 * FileDependency represents a dependency based on a file's last modification time.
 *
 * If the last modification time of the file specified via [[fileName]] is changed,
 * the dependency is considered as changed.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class File_Dependency extends Dependency
{
    /**
     * @var string the file path or [path alias](guide:concept-aliases) whose last modification time is used to
     * check if the dependency has been changed.
     */
    public $file_name;
    /**
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the file's last modification time.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     * @throws InvalidConfigException if [[fileName]] is not set
     */
    protected function generate_dependency_data($cache)
    {
        if ($this->file_name === null) {
            throw new Invalid_Config_Exception('FileDependency::fileName must be set');
        }
        $file_name = Yii::get_alias($this->file_name);
        clearstatcache(false, $file_name);
        return @filemtime($file_name);
    }
}