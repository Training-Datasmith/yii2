<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\grid;

/**
 * SerialColumn displays a column of row numbers (1-based).
 *
 * To add a SerialColumn to the [[GridView]], add it to the [[GridView::columns|columns]] configuration as follows:
 *
 * ```
 * 'columns' => [
 *     // ...
 *     [
 *         'class' => 'yii\grid\SerialColumn',
 *         // you may configure additional properties here
 *     ],
 * ]
 * ```
 *
 * For more details and usage information on SerialColumn, see the [guide article on data widgets](guide:output-data-widgets).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Serial_Column extends Column
{
    /**
     * {@inheritdoc}
     */
    public $header = '#';
    /**
     * {@inheritdoc}
     */
    protected function render_data_cell_content($model, $key, $index): string
    {
        $pagination = $this->grid->data_provider->get_pagination();
        if ($pagination !== false) {
            return (string) ($pagination->get_offset() + $index + 1);
        }
        return (string) ($index + 1);
    }
}