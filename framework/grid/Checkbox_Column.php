<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\grid;

use Closure;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Html;
use yii\helpers\Json;
/**
 * CheckboxColumn displays a column of checkboxes in a grid view.
 *
 * To add a CheckboxColumn to the [[GridView]], add it to the [[GridView::columns|columns]] configuration as follows:
 *
 * ```
 * 'columns' => [
 *     // ...
 *     [
 *         'class' => 'yii\grid\CheckboxColumn',
 *         // you may configure additional properties here
 *     ],
 * ]
 * ```
 *
 * Users may click on the checkboxes to select rows of the grid. The selected rows may be
 * obtained by calling the following JavaScript code:
 *
 * ```
 * var keys = $('#grid').yiiGridView('getSelectedRows');
 * // keys is an array consisting of the keys associated with the selected rows
 * ```
 *
 * For more details and usage information on CheckboxColumn, see the [guide article on data widgets](guide:output-data-widgets).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Checkbox_Column extends Column
{
    /**
     * @var string the name of the input checkbox input fields. This will be appended with `[]` to ensure it is an array.
     */
    public $name = 'selection';
    /**
     * @var array|\Closure the HTML attributes for checkboxes. This can either be an array of
     * attributes or an anonymous function ([[Closure]]) that returns such an array.
     * The signature of the function should be the following: `function ($model, $key, $index, $column)`.
     * Where `$model`, `$key`, and `$index` refer to the model, key and index of the row currently being rendered
     * and `$column` is a reference to the [[CheckboxColumn]] object.
     * A function may be used to assign different attributes to different rows based on the data in that row.
     * Specifically if you want to set a different value for the checkbox
     * you can use this option in the following way (in this example using the `name` attribute of the model):
     *
     * ```
     * 'checkboxOptions' => function ($model, $key, $index, $column) {
     *     return ['value' => $model->name];
     * }
     * ```
     *
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $checkbox_options = [];
    /**
     * @var bool whether it is possible to select multiple rows. Defaults to `true`.
     */
    public $multiple = true;
    /**
     * @var string the css class that will be used to find the checkboxes.
     * @since 2.0.9
     */
    public $css_class;
    /**
     * {@inheritdoc}
     * @throws \yii\base\InvalidConfigException if [[name]] is not set.
     */
    public function init(): void
    {
        parent::init();
        if (empty($this->name)) {
            throw new Invalid_Config_Exception('The "name" property must be set.');
        }
        if (substr_compare($this->name, '[]', -2, 2)) {
            $this->name .= '[]';
        }
        $this->register_client_script();
    }
    /**
     * Renders the header cell content.
     * The default implementation simply renders [[header]].
     * This method may be overridden to customize the rendering of the header cell.
     * @return string the rendering result
     */
    protected function render_header_cell_content()
    {
        if ($this->header !== null || !$this->multiple) {
            return parent::render_header_cell_content();
        }
        return Html::checkbox($this->get_header_check_box_name(), false, ['class' => 'select-on-check-all']);
    }
    /**
     * {@inheritdoc}
     */
    protected function render_data_cell_content($model, $key, $index)
    {
        if ($this->content !== null) {
            return parent::render_data_cell_content($model, $key, $index);
        }
        if ($this->checkbox_options instanceof Closure) {
            $options = call_user_func($this->checkbox_options, $model, $key, $index, $this);
        } else {
            $options = $this->checkbox_options;
        }
        if (!isset($options['value'])) {
            $options['value'] = is_array($key) ? Json::encode($key) : $key;
        }
        if ($this->css_class !== null) {
            Html::add_css_class($options, $this->css_class);
        }
        return Html::checkbox($this->name, !empty($options['checked']), $options);
    }
    /**
     * Returns header checkbox name.
     * @return string header checkbox name
     * @since 2.0.8
     */
    protected function get_header_check_box_name(): string
    {
        $name = $this->name;
        if (substr_compare($name, '[]', -2, 2) === 0) {
            $name = substr($name, 0, -2);
        }
        if (substr_compare($name, ']', -1, 1) === 0) {
            $name = substr($name, 0, -1) . '_all]';
        } else {
            $name .= '_all';
        }
        return $name;
    }
    /**
     * Registers the needed JavaScript.
     * @since 2.0.8
     */
    public function register_client_script(): void
    {
        $id = $this->grid->options['id'];
        $options = Json::encode(['name' => $this->name, 'class' => $this->css_class, 'multiple' => $this->multiple, 'checkAll' => $this->grid->show_header ? $this->get_header_check_box_name() : null]);
        $this->grid->get_view()->register_js("jQuery('#{$id}').yiiGridView('setSelectionColumn', {$options});");
    }
}