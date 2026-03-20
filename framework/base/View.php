<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
use yii\helpers\File_Helper;
use yii\widgets\Block;
use yii\widgets\Content_Decorator;
use yii\widgets\Fragment_Cache;
/**
 * View represents a view object in the MVC pattern.
 *
 * View provides a set of methods (e.g. [[render()]]) for rendering purpose.
 *
 * For more details and usage information on View, see the [guide article on views](guide:structure-views).
 *
 * @property-read DynamicContentAwareInterface[] $dynamicContents Class instances supporting dynamic contents.
 * @property-read string|bool $viewFile The view file currently being rendered. False if no view file is being
 * rendered.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class View extends Component implements Dynamic_Content_Aware_Interface
{
    /**
     * @event Event an event that is triggered by [[beginPage()]].
     */
    public const EVENT_BEGIN_PAGE = 'beginPage';
    /**
     * @event Event an event that is triggered by [[endPage()]].
     */
    public const EVENT_END_PAGE = 'endPage';
    /**
     * @event ViewEvent an event that is triggered by [[renderFile()]] right before it renders a view file.
     */
    public const EVENT_BEFORE_RENDER = 'beforeRender';
    /**
     * @event ViewEvent an event that is triggered by [[renderFile()]] right after it renders a view file.
     */
    public const EVENT_AFTER_RENDER = 'afterRender';
    /**
     * @var ViewContextInterface the context under which the [[renderFile()]] method is being invoked.
     */
    public $context;
    /**
     * @var array custom parameters that are shared among view templates.
     */
    public $params = [];
    /**
     * @var array|null a list of available renderers indexed by their corresponding supported file extensions.
     * Each renderer may be a view renderer object or the configuration for creating the renderer object.
     * For example, the following configuration enables both Smarty and Twig view renderers:
     *
     * ```
     * [
     *     'tpl' => ['class' => 'yii\smarty\ViewRenderer'],
     *     'twig' => ['class' => 'yii\twig\ViewRenderer'],
     * ]
     * ```
     *
     * If no renderer is available for the given view file, the view file will be treated as a normal PHP
     * and rendered via [[renderPhpFile()]].
     */
    public $renderers;
    /**
     * @var string the default view file extension. This will be appended to view file names if they don't have file extensions.
     */
    public $default_extension = 'php';
    /**
     * @var Theme|array|string|null the theme object or the configuration for creating the theme object.
     * If not set, it means theming is not enabled.
     */
    public $theme;
    /**
     * @var array a list of named output blocks. The keys are the block names and the values
     * are the corresponding block content. You can call [[beginBlock()]] and [[endBlock()]]
     * to capture small fragments of a view. They can be later accessed somewhere else
     * through this property.
     */
    public $blocks;
    /**
     * @var array|DynamicContentAwareInterface[] a list of currently active dynamic content class instances.
     * This property is used internally to implement the dynamic content caching feature. Do not modify it directly.
     * @internal
     * @deprecated Since 2.0.14. Do not use this property directly. Use methods [[getDynamicContents()]],
     * [[pushDynamicContent()]], [[popDynamicContent()]] instead.
     */
    public $cache_stack = [];
    /**
     * @var array a list of placeholders for embedding dynamic contents. This property
     * is used internally to implement the content caching feature. Do not modify it directly.
     * @internal
     * @deprecated Since 2.0.14. Do not use this property directly. Use methods [[getDynamicPlaceholders()]],
     * [[setDynamicPlaceholders()]], [[addDynamicPlaceholder()]] instead.
     */
    public $dynamic_placeholders = [];
    /**
     * @var array the view files currently being rendered. There may be multiple view files being
     * rendered at a moment because one view may be rendered within another.
     */
    private array $_view_files = [];
    /**
     * Initializes the view component.
     */
    public function init(): void
    {
        parent::init();
        if (is_array($this->theme)) {
            if (!isset($this->theme['class'])) {
                $this->theme['class'] = 'yii\base\Theme';
            }
            $this->theme = Yii::create_object($this->theme);
        } elseif (is_string($this->theme)) {
            $this->theme = Yii::create_object($this->theme);
        }
    }
    /**
     * Renders a view.
     *
     * The view to be rendered can be specified in one of the following formats:
     *
     * - [path alias](guide:concept-aliases) (e.g. "@app/views/site/index");
     * - absolute path within application (e.g. "//site/index"): the view name starts with double slashes.
     *   The actual view file will be looked for under the [[Application::viewPath|view path]] of the application.
     * - absolute path within current module (e.g. "/site/index"): the view name starts with a single slash.
     *   The actual view file will be looked for under the [[Module::viewPath|view path]] of the [[Controller::module|current module]].
     * - relative view (e.g. "index"): the view name does not start with `@` or `/`. The corresponding view file will be
     *   looked for under the [[ViewContextInterface::getViewPath()|view path]] of the view `$context`.
     *   If `$context` is not given, it will be looked for under the directory containing the view currently
     *   being rendered (i.e., this happens when rendering a view within another view).
     *
     * @param string $view the view name.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param object|null $context the context to be assigned to the view and can later be accessed via [[context]]
     * in the view. If the context implements [[ViewContextInterface]], it may also be used to locate
     * the view file corresponding to a relative view name.
     * @return string the rendering result
     * @throws ViewNotFoundException if the view file does not exist.
     * @throws InvalidCallException if the view cannot be resolved.
     * @see renderFile()
     */
    public function render($view, $params = [], $context = null)
    {
        $view_file = $this->find_view_file($view, $context);
        return $this->render_file($view_file, $params, $context);
    }
    /**
     * Finds the view file based on the given view name.
     * @param string $view the view name or the [path alias](guide:concept-aliases) of the view file. Please refer to [[render()]]
     * on how to specify this parameter.
     * @param object|null $context the context to be assigned to the view and can later be accessed via [[context]]
     * in the view. If the context implements [[ViewContextInterface]], it may also be used to locate
     * the view file corresponding to a relative view name.
     * @return string the view file path. Note that the file may not exist.
     * @throws InvalidCallException if a relative view name is given while there is no active context to
     * determine the corresponding view file.
     */
    protected function find_view_file(string $view, $context = null)
    {
        if (strncmp($view, '@', 1) === 0) {
            // e.g. "@app/views/main"
            $file = Yii::get_alias($view);
        } elseif (strncmp($view, '//', 2) === 0) {
            // e.g. "//layouts/main"
            $file = Yii::$app->get_view_path() . DIRECTORY_SEPARATOR . ltrim($view, '/');
        } elseif (strncmp($view, '/', 1) === 0) {
            // e.g. "/site/index"
            if (Yii::$app->controller !== null) {
                $file = Yii::$app->controller->module->get_view_path() . DIRECTORY_SEPARATOR . ltrim($view, '/');
            } else {
                throw new Invalid_Call_Exception("Unable to locate view file for view '{$view}': no active controller.");
            }
        } elseif ($context instanceof View_Context_Interface) {
            $file = $context->get_view_path() . DIRECTORY_SEPARATOR . $view;
        } elseif (($current_view_file = $this->get_requested_view_file()) !== false) {
            $file = dirname($current_view_file) . DIRECTORY_SEPARATOR . $view;
        } else {
            throw new Invalid_Call_Exception("Unable to resolve view file for view '{$view}': no active view context.");
        }
        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }
        $path = $file . '.' . $this->default_extension;
        if ($this->default_extension !== 'php' && !is_file($path)) {
            return $file . '.php';
        }
        return $path;
    }
    /**
     * Renders a view file.
     *
     * If [[theme]] is enabled (not null), it will try to render the themed version of the view file as long
     * as it is available.
     *
     * The method will call [[FileHelper::localize()]] to localize the view file.
     *
     * If [[renderers|renderer]] is enabled (not null), the method will use it to render the view file.
     * Otherwise, it will simply include the view file as a normal PHP file, capture its output and
     * return it as a string.
     *
     * @param string $viewFile the view file. This can be either an absolute file path or an alias of it.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param object|null $context the context that the view should use for rendering the view. If null,
     * existing [[context]] will be used.
     * @return string the rendering result
     * @throws ViewNotFoundException if the view file does not exist
     */
    public function render_file($view_file, $params = [], $context = null): string
    {
        $view_file = $requested_file = Yii::get_alias($view_file);
        if ($this->theme !== null) {
            $view_file = $this->theme->apply_to($view_file);
        }
        if (is_file($view_file)) {
            $view_file = File_Helper::localize($view_file);
        } else {
            throw new View_Not_Found_Exception("The view file does not exist: {$view_file}");
        }
        $old_context = $this->context;
        if ($context !== null) {
            $this->context = $context;
        }
        $output = '';
        $this->_view_files[] = ['resolved' => $view_file, 'requested' => $requested_file];
        if ($this->before_render($view_file, $params)) {
            Yii::debug("Rendering view file: {$view_file}", __METHOD__);
            $ext = pathinfo($view_file, PATHINFO_EXTENSION);
            if (isset($this->renderers[$ext])) {
                if (is_array($this->renderers[$ext]) || is_string($this->renderers[$ext])) {
                    $this->renderers[$ext] = Yii::create_object($this->renderers[$ext]);
                }
                /** @var ViewRenderer $renderer */
                $renderer = $this->renderers[$ext];
                $output = $renderer->render($this, $view_file, $params);
            } else {
                $output = $this->render_php_file($view_file, $params);
            }
            $this->after_render($view_file, $params, $output);
        }
        array_pop($this->_view_files);
        $this->context = $old_context;
        return $output;
    }
    /**
     * @return string|bool the view file currently being rendered. False if no view file is being rendered.
     */
    public function get_view_file()
    {
        return empty($this->_view_files) ? false : end($this->_view_files)['resolved'];
    }
    /**
     * @return string|bool the requested view currently being rendered. False if no view file is being rendered.
     * @since 2.0.16
     */
    protected function get_requested_view_file()
    {
        return empty($this->_view_files) ? false : end($this->_view_files)['requested'];
    }
    /**
     * This method is invoked right before [[renderFile()]] renders a view file.
     * The default implementation will trigger the [[EVENT_BEFORE_RENDER]] event.
     * If you override this method, make sure you call the parent implementation first.
     * @param string $viewFile the view file to be rendered.
     * @param array $params the parameter array passed to the [[render()]] method.
     * @return bool whether to continue rendering the view file.
     */
    public function before_render($view_file, $params)
    {
        $event = new View_Event(['viewFile' => $view_file, 'params' => $params]);
        $this->trigger(self::EVENT_BEFORE_RENDER, $event);
        return $event->is_valid;
    }
    /**
     * This method is invoked right after [[renderFile()]] renders a view file.
     * The default implementation will trigger the [[EVENT_AFTER_RENDER]] event.
     * If you override this method, make sure you call the parent implementation first.
     * @param string $viewFile the view file being rendered.
     * @param array $params the parameter array passed to the [[render()]] method.
     * @param string $output the rendering result of the view file. Updates to this parameter
     * will be passed back and returned by [[renderFile()]].
     */
    public function after_render($view_file, $params, &$output): void
    {
        if ($this->has_event_handlers(self::EVENT_AFTER_RENDER)) {
            $event = new View_Event(['viewFile' => $view_file, 'params' => $params]);
            $event->output =& $output;
            $this->trigger(self::EVENT_AFTER_RENDER, $event);
        }
    }
    /**
     * Renders a view file as a PHP script.
     *
     * This method treats the view file as a PHP script and includes the file.
     * It extracts the given parameters and makes them available in the view file.
     * The method captures the output of the included view file and returns it as a string.
     *
     * This method should mainly be called by view renderer or [[renderFile()]].
     *
     * @param string $_file_ the view file.
     * @param array $_params_ the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @return string the rendering result
     * @throws \Throwable
     */
    public function render_php_file($_file_, $_params_ = [])
    {
        $_ob_initial_level_ = ob_get_level();
        ob_start();
        ob_implicit_flush(false);
        extract($_params_, EXTR_OVERWRITE);
        try {
            require $_file_;
            return ob_get_clean();
        } catch (\Exception|\Throwable $e) {
            while (ob_get_level() > $_ob_initial_level_) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        }
    }
    /**
     * Renders dynamic content returned by the given PHP statements.
     * This method is mainly used together with content caching (fragment caching and page caching)
     * when some portions of the content (called *dynamic content*) should not be cached.
     * The dynamic content must be returned by some PHP statements.
     * @param string $statements the PHP statements for generating the dynamic content.
     * @return string the placeholder of the dynamic content, or the dynamic content if there is no
     * active content cache currently.
     *
     * Note that most methods that indirectly modify layout such as registerJS() or registerJSFile() do not
     * work with dynamic rendering.
     *
     * @see https://github.com/yiisoft/yii2/issues/17673
     */
    public function render_dynamic($statements)
    {
        if (!empty($this->cache_stack)) {
            $n = count($this->dynamic_placeholders);
            $placeholder = "<![CDATA[YII-DYNAMIC-{$n}]]>";
            $this->add_dynamic_placeholder($placeholder, $statements);
            return $placeholder;
        }
        return $this->evaluate_dynamic_content($statements);
    }
    /**
     * {@inheritdoc}
     */
    public function get_dynamic_placeholders()
    {
        return $this->dynamic_placeholders;
    }
    /**
     * {@inheritdoc}
     */
    public function set_dynamic_placeholders($placeholders): void
    {
        $this->dynamic_placeholders = $placeholders;
    }
    /**
     * {@inheritdoc}
     */
    public function add_dynamic_placeholder($placeholder, $statements): void
    {
        foreach ($this->cache_stack as $cache) {
            if ($cache instanceof Dynamic_Content_Aware_Interface) {
                $cache->add_dynamic_placeholder($placeholder, $statements);
            } else {
                // TODO: Remove in 2.1
                $cache->dynamic_placeholders[$placeholder] = $statements;
            }
        }
        $this->dynamic_placeholders[$placeholder] = $statements;
    }
    /**
     * Evaluates the given PHP statements.
     * This method is mainly used internally to implement dynamic content feature.
     * @param string $statements the PHP statements to be evaluated.
     * @return mixed the return value of the PHP statements.
     */
    public function evaluate_dynamic_content($statements)
    {
        return eval($statements);
    }
    /**
     * Returns a list of currently active dynamic content class instances.
     * @return DynamicContentAwareInterface[] class instances supporting dynamic contents.
     * @since 2.0.14
     */
    public function get_dynamic_contents()
    {
        return $this->cache_stack;
    }
    /**
     * Adds a class instance supporting dynamic contents to the end of a list of currently active
     * dynamic content class instances.
     * @param DynamicContentAwareInterface $instance class instance supporting dynamic contents.
     * @since 2.0.14
     */
    public function push_dynamic_content(Dynamic_Content_Aware_Interface $instance): void
    {
        $this->cache_stack[] = $instance;
    }
    /**
     * Removes a last class instance supporting dynamic contents from a list of currently active
     * dynamic content class instances.
     * @since 2.0.14
     */
    public function pop_dynamic_content(): void
    {
        array_pop($this->cache_stack);
    }
    /**
     * Begins recording a block.
     *
     * This method is a shortcut to beginning [[Block]].
     * @param string $id the block ID.
     * @param bool $renderInPlace whether to render the block content in place.
     * Defaults to false, meaning the captured block will not be displayed.
     * @return Block the Block widget instance
     */
    public function begin_block($id, $render_in_place = false)
    {
        return Block::begin(['id' => $id, 'renderInPlace' => $render_in_place, 'view' => $this]);
    }
    /**
     * Ends recording a block.
     */
    public function end_block(): void
    {
        Block::end();
    }
    /**
     * Begins the rendering of content that is to be decorated by the specified view.
     *
     * This method can be used to implement nested layout. For example, a layout can be embedded
     * in another layout file specified as '@app/views/layouts/base.php' like the following:
     *
     * ```
     * <?php $this->beginContent('@app/views/layouts/base.php'); ?>
     * //...layout content here...
     * <?php $this->endContent(); ?>
     * ```
     *
     * @param string $viewFile the view file that will be used to decorate the content enclosed by this widget.
     * This can be specified as either the view file path or [path alias](guide:concept-aliases).
     * @param array $params the variables (name => value) to be extracted and made available in the decorative view.
     * @return ContentDecorator the ContentDecorator widget instance
     * @see ContentDecorator
     */
    public function begin_content($view_file, $params = [])
    {
        return Content_Decorator::begin(['viewFile' => $view_file, 'params' => $params, 'view' => $this]);
    }
    /**
     * Ends the rendering of content.
     */
    public function end_content(): void
    {
        Content_Decorator::end();
    }
    /**
     * Begins fragment caching.
     *
     * This method will display cached content if it is available.
     * If not, it will start caching and would expect an [[endCache()]]
     * call to end the cache and save the content into cache.
     * A typical usage of fragment caching is as follows,
     *
     * ```
     * if ($this->beginCache($id)) {
     *     // ...generate content here
     *     $this->endCache();
     * }
     * ```
     *
     * @param string $id a unique ID identifying the fragment to be cached.
     * @param array $properties initial property values for [[FragmentCache]]
     * @return bool whether you should generate the content for caching.
     * False if the cached version is available.
     */
    public function begin_cache($id, array $properties = []): bool
    {
        $properties['id'] = $id;
        $properties['view'] = $this;
        /** @var FragmentCache $cache */
        $cache = Fragment_Cache::begin($properties);
        if ($cache->get_cached_content() !== false) {
            $this->end_cache();
            return false;
        }
        return true;
    }
    /**
     * Ends fragment caching.
     */
    public function end_cache(): void
    {
        Fragment_Cache::end();
    }
    /**
     * Marks the beginning of a page.
     */
    public function begin_page(): void
    {
        ob_start();
        ob_implicit_flush(false);
        $this->trigger(self::EVENT_BEGIN_PAGE);
    }
    /**
     * Marks the ending of a page.
     */
    public function end_page(): void
    {
        $this->trigger(self::EVENT_END_PAGE);
        ob_end_flush();
    }
}