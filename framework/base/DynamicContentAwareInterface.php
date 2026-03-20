<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * DynamicContentAwareInterface is the interface that should be implemented by classes
 * which support a [[View]] dynamic content feature.
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.14
 */
interface Dynamic_Content_Aware_Interface
{
    /**
     * Returns a list of placeholders for dynamic content. This method
     * is used internally to implement the content caching feature.
     * @return array a list of placeholders.
     */
    public function get_dynamic_placeholders();
    /**
     * Sets a list of placeholders for dynamic content. This method
     * is used internally to implement the content caching feature.
     * @param array $placeholders a list of placeholders.
     */
    public function set_dynamic_placeholders($placeholders);
    /**
     * Adds a placeholder for dynamic content.
     * This method is used internally to implement the content caching feature.
     * @param string $name the placeholder name.
     * @param string $statements the PHP statements for generating the dynamic content.
     */
    public function add_dynamic_placeholder($name, $statements);
}