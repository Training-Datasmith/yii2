<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\grid;

use Closure;
use yii\base\Base_Object;
use yii\helpers\Html;
/**
 * Column is the base class of all [[GridView]] column classes.
 *
 * For more details and usage information on Column, see the [guide article on data widgets](guide:output-data-widgets).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Column extends Base_Object
{
    /**
     * @var GridView the grid view object that owns this column.
     */
    public $grid;
    /**
     * @var string|null the header cell content. Note that it will not be HTML-encoded.
     */
    public $header;
    /**
     * @var string the footer cell content. Note that it will not be HTML-encoded.
     */
    public $footer;
    /**
     * @var callable This is a callable that will be used to generate the content of each cell.
     * The signature of the function should be the following: `function ($model, $key, $index, $column)`.
     * Where `$model`, `$key`, and `$index` refer to the model, key and index of the row currently being rendered
     * and `$column` is a reference to the [[Column]] object.
     */
    public $content;
    /**
     * @var bool whether this column is visible. Defaults to true.
     */
    public $visible = true;
    /**
     * @var array the HTML attributes for the column group tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = [];
    /**
     * @var array the HTML attributes for the header cell tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $header_options = [];
    /**
     * @var array|\Closure the HTML attributes for the data cell tag. This can either be an array of
     * attributes or an anonymous function ([[Closure]]) that returns such an array.
     * The signature of the function should be the following: `function ($model, $key, $index, $column)`.
     * Where `$model`, `$key`, and `$index` refer to the model, key and index of the row currently being rendered
     * and `$column` is a reference to the [[Column]] object.
     * A function may be used to assign different attributes to different rows based on the data in that row.
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $content_options = [];
    /**
     * @var array the HTML attributes for the footer cell tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $footer_options = [];
    /**
     * @var array the HTML attributes for the filter cell tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $filter_options = [];
    /**
     * Renders the header cell.
     */
    public function render_header_cell()
    {
        return Html::tag('th', $this->render_header_cell_content(), $this->header_options);
    }
    /**
     * Renders the footer cell.
     */
    public function render_footer_cell()
    {
        return Html::tag('td', $this->render_footer_cell_content(), $this->footer_options);
    }
    /**
     * Renders a data cell.
     * @param mixed $model the data model being rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data item among the item array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     */
    public function render_data_cell($model, $key, $index)
    {
        if ($this->content_options instanceof Closure) {
            $options = call_user_func($this->content_options, $model, $key, $index, $this);
        } else {
            $options = $this->content_options;
        }
        return Html::tag('td', $this->render_data_cell_content($model, $key, $index), $options);
    }
    /**
     * Renders the filter cell.
     */
    public function render_filter_cell()
    {
        return Html::tag('td', $this->render_filter_cell_content(), $this->filter_options);
    }
    /**
     * Renders the header cell content.
     * The default implementation simply renders [[header]].
     * This method may be overridden to customize the rendering of the header cell.
     * @return string the rendering result
     */
    protected function render_header_cell_content()
    {
        return $this->header !== null && trim($this->header) !== '' ? $this->header : $this->get_header_cell_label();
    }
    /**
     * Returns header cell label.
     * This method may be overridden to customize the label of the header cell.
     * @return string label
     * @since 2.0.8
     */
    protected function get_header_cell_label()
    {
        return $this->grid->empty_cell;
    }
    /**
     * Renders the footer cell content.
     * The default implementation simply renders [[footer]].
     * This method may be overridden to customize the rendering of the footer cell.
     * @return string the rendering result
     */
    protected function render_footer_cell_content()
    {
        return $this->footer !== null && trim($this->footer) !== '' ? $this->footer : $this->grid->empty_cell;
    }
    /**
     * Renders the data cell content.
     * @param mixed $model the data model
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data model among the models array returned by [[GridView::dataProvider]].
     * @return string the rendering result
     */
    protected function render_data_cell_content($model, $key, $index)
    {
        if ($this->content !== null) {
            return call_user_func($this->content, $model, $key, $index, $this);
        }
        return $this->grid->empty_cell;
    }
    /**
     * Renders the filter cell content.
     * The default implementation simply renders a space.
     * This method may be overridden to customize the rendering of the filter cell (if any).
     * @return string the rendering result
     */
    protected function render_filter_cell_content()
    {
        return $this->grid->empty_cell;
    }
}