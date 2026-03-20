<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * DynamicContentAwareTrait implements common methods for classes
 * which support a [[View]] dynamic content feature.
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.14
 */
trait Dynamic_Content_Aware_Trait
{
    /**
     * @var string[] a list of placeholders for dynamic content
     */
    private $_dynamic_placeholders;
    /**
     * Returns the view object that can be used to render views or view files using dynamic contents.
     * @return View the view object that can be used to render views or view files.
     */
    abstract protected function get_view();
    /**
     * {@inheritdoc}
     */
    public function get_dynamic_placeholders()
    {
        return $this->_dynamic_placeholders;
    }
    /**
     * {@inheritdoc}
     */
    public function set_dynamic_placeholders($placeholders): void
    {
        $this->_dynamic_placeholders = $placeholders;
    }
    /**
     * {@inheritdoc}
     */
    public function add_dynamic_placeholder($name, $statements): void
    {
        $this->_dynamic_placeholders[$name] = $statements;
    }
    /**
     * Replaces placeholders in $content with results of evaluated dynamic statements.
     * @param string $content content to be parsed.
     * @param string[] $placeholders placeholders and their values.
     * @param bool $isRestoredFromCache whether content is going to be restored from cache.
     * @return string final content.
     */
    protected function update_dynamic_content($content, $placeholders, $is_restored_from_cache = false)
    {
        if (empty($placeholders) || !is_array($placeholders)) {
            return $content;
        }
        if (count($this->get_view()->get_dynamic_contents()) === 0) {
            // outermost cache: replace placeholder with dynamic content
            foreach ($placeholders as $name => $statements) {
                $placeholders[$name] = $this->get_view()->evaluate_dynamic_content($statements);
            }
            $content = strtr($content, $placeholders);
        }
        if ($is_restored_from_cache) {
            $view = $this->get_view();
            foreach ($placeholders as $name => $statements) {
                $view->add_dynamic_placeholder($name, $statements);
            }
        }
        return $content;
    }
}