<?php

declare (strict_types=1);
/**
 * YiiRequirementChecker allows checking, if current system meets the requirements for running the Yii application.
 * This class allows rendering of the check report for the web and console application interface.
 *
 * Example:
 *
 * ```
 * require_once 'path/to/YiiRequirementChecker.php';
 * $requirementsChecker = new YiiRequirementChecker();
 * $requirements = array(
 *     array(
 *         'name' => 'PHP Some Extension',
 *         'mandatory' => true,
 *         'condition' => extension_loaded('some_extension'),
 *         'by' => 'Some application feature',
 *         'memo' => 'PHP extension "some_extension" required',
 *     ),
 * );
 * $requirementsChecker->checkYii()->check($requirements)->render();
 * ```
 *
 * If you wish to render the report with your own representation, use [[getResult()]] instead of [[render()]]
 *
 * Requirement condition could be in format "eval:PHP expression".
 * In this case specified PHP expression will be evaluated in the context of this class instance.
 * For example:
 *
 * ```
 * $requirements = array(
 *     array(
 *         'name' => 'Upload max file size',
 *         'condition' => 'eval:$this->checkUploadMaxFileSize("5M")',
 *     ),
 * );
 * ```
 *
 * Note: this class definition does not match ordinary Yii style, because it should match PHP 4.3
 * and should not use features from newer PHP versions!
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Yii_Requirement_Checker
{
    /**
     * @var array|null the check results, this property is for internal usage only.
     */
    public $result;
    /**
     * Check the given requirements, collecting results into internal field.
     * This method can be invoked several times checking different requirement sets.
     * Use [[getResult()]] or [[render()]] to get the results.
     * @param array|string $requirements requirements to be checked.
     * If an array, it is treated as the set of requirements;
     * If a string, it is treated as the path of the file, which contains the requirements;
     * @return $this self instance.
     */
    public function check($requirements): self
    {
        if (is_string($requirements)) {
            $requirements = require $requirements;
        }
        if (!is_array($requirements)) {
            $this->usage_error('Requirements must be an array, "' . gettype($requirements) . '" has been given!');
        }
        if (!isset($this->result) || !is_array($this->result)) {
            $this->result = ['summary' => ['total' => 0, 'errors' => 0, 'warnings' => 0], 'requirements' => []];
        }
        foreach ($requirements as $key => $raw_requirement) {
            $requirement = $this->normalize_requirement($raw_requirement, $key);
            $this->result['summary']['total']++;
            if (!$requirement['condition']) {
                if ($requirement['mandatory']) {
                    $requirement['error'] = true;
                    $requirement['warning'] = true;
                    $this->result['summary']['errors']++;
                } else {
                    $requirement['error'] = false;
                    $requirement['warning'] = true;
                    $this->result['summary']['warnings']++;
                }
            } else {
                $requirement['error'] = false;
                $requirement['warning'] = false;
            }
            $this->result['requirements'][] = $requirement;
        }
        return $this;
    }
    /**
     * Performs the check for the Yii core requirements.
     * @return YiiRequirementChecker self instance.
     */
    public function check_yii()
    {
        return $this->check(__DIR__ . DIRECTORY_SEPARATOR . 'requirements.php');
    }
    /**
     * Return the check results.
     * @return array|null check results in format:
     *
     * ```
     * array(
     *     'summary' => array(
     *         'total' => total number of checks,
     *         'errors' => number of errors,
     *         'warnings' => number of warnings,
     *     ),
     *     'requirements' => array(
     *         array(
     *             ...
     *             'error' => is there an error,
     *             'warning' => is there a warning,
     *         ),
     *         ...
     *     ),
     * )
     * ```
     */
    public function get_result()
    {
        return $this->result ?? null;
    }
    /**
     * Renders the requirements check result.
     * The output will vary depending is a script running from web or from console.
     */
    public function render(): void
    {
        if (!isset($this->result)) {
            $this->usage_error('Nothing to render!');
        }
        $base_view_file_path = __DIR__ . DIRECTORY_SEPARATOR . 'views';
        if (!empty($_SERVER['argv'])) {
            $view_file_name = $base_view_file_path . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'index.php';
        } else {
            $view_file_name = $base_view_file_path . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'index.php';
        }
        $this->render_view_file($view_file_name, $this->result);
    }
    /**
     * Checks if the given PHP extension is available and its version matches the given one.
     * @param string $extensionName PHP extension name.
     * @param string $version required PHP extension version.
     * @param string $compare comparison operator, by default '>='
     * @return bool if PHP extension version matches.
     */
    public function check_php_extension_version($extension_name, $version, $compare = '>=')
    {
        if (!extension_loaded($extension_name)) {
            return false;
        }
        $extension_version = phpversion($extension_name);
        if (empty($extension_version)) {
            return false;
        }
        if (strncasecmp($extension_version, 'PECL-', 5) === 0) {
            $extension_version = substr($extension_version, 5);
        }
        return version_compare($extension_version, $version, $compare);
    }
    /**
     * Checks if PHP configuration option (from php.ini) is on.
     * @param string $name configuration option name.
     * @return bool option is on.
     */
    public function check_php_ini_on($name)
    {
        $value = ini_get($name);
        if (empty($value)) {
            return false;
        }
        return (int) $value === 1 || strtolower($value) === 'on';
    }
    /**
     * Checks if PHP configuration option (from php.ini) is off.
     * @param string $name configuration option name.
     * @return bool option is off.
     */
    public function check_php_ini_off($name)
    {
        $value = ini_get($name);
        if (empty($value)) {
            return true;
        }
        return strtolower($value) === 'off';
    }
    /**
     * Compare byte sizes of values given in the verbose representation,
     * like '5M', '15K' etc.
     * @param string $a first value.
     * @param string $b second value.
     * @param string $compare comparison operator, by default '>='.
     * @return bool comparison result.
     */
    public function compare_byte_size($a, $b, string $compare = '>=')
    {
        $compare_expression = '(' . $this->get_byte_size($a) . $compare . $this->get_byte_size($b) . ')';
        return $this->evaluate_expression($compare_expression);
    }
    /**
     * Gets the size in bytes from verbose size representation.
     * For example: '5K' => 5*1024
     * @param string $verboseSize verbose size representation.
     * @return int actual size in bytes.
     */
    public function get_byte_size($verbose_size)
    {
        if (empty($verbose_size)) {
            return 0;
        }
        if (is_numeric($verbose_size)) {
            return (int) $verbose_size;
        }
        $size_unit = trim($verbose_size, '0123456789');
        $size = trim(str_replace($size_unit, '', $verbose_size));
        if (!is_numeric($size)) {
            return 0;
        }
        switch (strtolower($size_unit)) {
            case 'kb':
            case 'k':
                return $size * 1024;
            case 'mb':
            case 'm':
                return $size * 1024 * 1024;
            case 'gb':
            case 'g':
                return $size * 1024 * 1024 * 1024;
            default:
                return 0;
        }
    }
    /**
     * Checks if upload max file size matches the given range.
     * @param string|null $min verbose file size minimum required value, pass null to skip minimum check.
     * @param string|null $max verbose file size maximum required value, pass null to skip maximum check.
     * @return bool success.
     */
    public function check_upload_max_file_size($min = null, $max = null): bool
    {
        $post_max_size = ini_get('post_max_size');
        $upload_max_file_size = ini_get('upload_max_filesize');
        if ($min !== null) {
            $min_check_result = $this->compare_byte_size($post_max_size, $min, '>=') && $this->compare_byte_size($upload_max_file_size, $min, '>=');
        } else {
            $min_check_result = true;
        }
        if ($max !== null) {
            $max_check_result = $this->compare_byte_size($post_max_size, $max, '<=') && $this->compare_byte_size($upload_max_file_size, $max, '<=');
        } else {
            $max_check_result = true;
        }
        return $min_check_result && $max_check_result;
    }
    /**
     * Renders a view file.
     * This method includes the view file as a PHP script
     * and captures the display result if required.
     * @param string $_viewFile_ view file
     * @param array|null $_data_ data to be extracted and made available to the view file
     * @param bool $_return_ whether the rendering result should be returned as a string
     * @return string|null the rendering result. Null if the rendering result is not required.
     */
    public function render_view_file($_view_file_, $_data_ = null, $_return_ = false)
    {
        // we use special variable names here to avoid conflict when extracting data
        if (is_array($_data_)) {
            extract($_data_, EXTR_PREFIX_SAME, 'data');
        } else {
            $data = $_data_;
        }
        if ($_return_) {
            ob_start();
            ob_implicit_flush(false);
            require $_view_file_;
            return ob_get_clean();
        }
        require $_view_file_;
        return null;
    }
    /**
     * Normalizes requirement ensuring it has correct format.
     * @param array $requirement raw requirement.
     * @param int $requirementKey requirement key in the list.
     * @return array normalized requirement.
     */
    public function normalize_requirement($requirement, $requirement_key = 0)
    {
        if (!is_array($requirement)) {
            $this->usage_error('Requirement must be an array!');
        }
        if (!array_key_exists('condition', $requirement)) {
            $this->usage_error("Requirement '{$requirement_key}' has no condition!");
        } else {
            $eval_prefix = 'eval:';
            if (is_string($requirement['condition']) && strpos($requirement['condition'], $eval_prefix) === 0) {
                $expression = substr($requirement['condition'], strlen($eval_prefix));
                $requirement['condition'] = $this->evaluate_expression($expression);
            }
        }
        if (!array_key_exists('name', $requirement)) {
            $requirement['name'] = is_numeric($requirement_key) ? 'Requirement #' . $requirement_key : $requirement_key;
        }
        if (!array_key_exists('mandatory', $requirement)) {
            if (array_key_exists('required', $requirement)) {
                $requirement['mandatory'] = $requirement['required'];
            } else {
                $requirement['mandatory'] = false;
            }
        }
        if (!array_key_exists('by', $requirement)) {
            $requirement['by'] = 'Unknown';
        }
        if (!array_key_exists('memo', $requirement)) {
            $requirement['memo'] = '';
        }
        return $requirement;
    }
    /**
     * Displays a usage error.
     * This method will then terminate the execution of the current application.
     * @param string $message the error message
     */
    public function usage_error($message): void
    {
        echo "Error: {$message}\n\n";
        exit(1);
    }
    /**
     * Evaluates a PHP expression under the context of this class.
     * @param string $expression a PHP expression to be evaluated.
     * @return mixed the expression result.
     */
    public function evaluate_expression(string $expression)
    {
        return eval('return ' . $expression . ';');
    }
    /**
     * Returns the server information.
     * @return string server information.
     */
    public function get_server_info()
    {
        return $_SERVER['SERVER_SOFTWARE'] ?? '';
    }
    /**
     * Returns the now date if possible in string representation.
     * @return string now date.
     */
    public function get_now_date(): string
    {
        return date('Y-m-d H:i');
    }
}