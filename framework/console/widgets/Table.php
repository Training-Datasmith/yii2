<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Array_Helper;
use yii\helpers\Console;
/**
 * Table class displays a table in console.
 *
 * For example,
 *
 * ```
 * $table = new Table();
 *
 * echo $table
 *     ->setHeaders(['test1', 'test2', 'test3'])
 *     ->setRows([
 *         ['col1', 'col2', 'col3'],
 *         ['col1', 'col2', ['col3-0', 'col3-1', 'col3-2']],
 *     ])
 *     ->run();
 * ```
 *
 * or
 *
 * ```
 * echo Table::widget([
 *     'headers' => ['test1', 'test2', 'test3'],
 *     'rows' => [
 *         ['col1', 'col2', 'col3'],
 *         ['col1', 'col2', ['col3-0', 'col3-1', 'col3-2']],
 *     ],
 * ]);
 *
 * @property-write array $chars Table chars.
 * @property-write array $headers Table headers.
 * @property-write string $listPrefix List prefix.
 * @property-write array $rows Table rows.
 * @property-write int $screenWidth Screen width.
 *
 * @author Daniel Gomez Pan <pana_1990@hotmail.com>
 * @since 2.0.13
 */
class Table extends Widget
{
    public const DEFAULT_CONSOLE_SCREEN_WIDTH = 120;
    public const CONSOLE_SCROLLBAR_OFFSET = 3;
    public const CHAR_TOP = 'top';
    public const CHAR_TOP_MID = 'top-mid';
    public const CHAR_TOP_LEFT = 'top-left';
    public const CHAR_TOP_RIGHT = 'top-right';
    public const CHAR_BOTTOM = 'bottom';
    public const CHAR_BOTTOM_MID = 'bottom-mid';
    public const CHAR_BOTTOM_LEFT = 'bottom-left';
    public const CHAR_BOTTOM_RIGHT = 'bottom-right';
    public const CHAR_LEFT = 'left';
    public const CHAR_LEFT_MID = 'left-mid';
    public const CHAR_MID = 'mid';
    public const CHAR_MID_MID = 'mid-mid';
    public const CHAR_RIGHT = 'right';
    public const CHAR_RIGHT_MID = 'right-mid';
    public const CHAR_MIDDLE = 'middle';
    /**
     * @var array table headers
     * @since 2.0.19
     */
    protected $headers = [];
    /**
     * @var array table rows
     * @since 2.0.19
     */
    protected $rows = [];
    /**
     * @var array table chars
     * @since 2.0.19
     */
    protected $chars = [self::CHAR_TOP => '═', self::CHAR_TOP_MID => '╤', self::CHAR_TOP_LEFT => '╔', self::CHAR_TOP_RIGHT => '╗', self::CHAR_BOTTOM => '═', self::CHAR_BOTTOM_MID => '╧', self::CHAR_BOTTOM_LEFT => '╚', self::CHAR_BOTTOM_RIGHT => '╝', self::CHAR_LEFT => '║', self::CHAR_LEFT_MID => '╟', self::CHAR_MID => '─', self::CHAR_MID_MID => '┼', self::CHAR_RIGHT => '║', self::CHAR_RIGHT_MID => '╢', self::CHAR_MIDDLE => '│'];
    /**
     * @var array table column widths
     * @since 2.0.19
     */
    protected $column_widths = [];
    /**
     * @var int screen width
     * @since 2.0.19
     */
    protected $screen_width;
    /**
     * @var string list prefix
     * @since 2.0.19
     */
    protected $list_prefix = '• ';
    /**
     * Set table headers.
     *
     * @param array $headers table headers
     * @return $this
     */
    public function set_headers(array $headers): self
    {
        $this->headers = array_values($headers);
        return $this;
    }
    /**
     * Set table rows.
     *
     * @param array $rows table rows
     * @return $this
     */
    public function set_rows(array $rows): self
    {
        $this->rows = array_map(fn($row) => array_map(fn($value) => empty($value) && !is_numeric($value) ? ' ' : (is_array($value) ? array_values($value) : $value), array_values($row)), $rows);
        return $this;
    }
    /**
     * Set table chars.
     *
     * @param array $chars table chars
     * @return $this
     */
    public function set_chars(array $chars): self
    {
        $this->chars = $chars;
        return $this;
    }
    /**
     * Set screen width.
     *
     * @param int $width screen width
     * @return $this
     */
    public function set_screen_width($width): self
    {
        $this->screen_width = $width;
        return $this;
    }
    /**
     * Set list prefix.
     *
     * @param string $listPrefix list prefix
     * @return $this
     */
    public function set_list_prefix($list_prefix): self
    {
        $this->list_prefix = $list_prefix;
        return $this;
    }
    /**
     * @return string the rendered table
     */
    public function run(): string
    {
        $this->calculate_rows_size();
        $header_count = count($this->headers);
        $buffer = $this->render_separator($this->chars[self::CHAR_TOP_LEFT], $this->chars[self::CHAR_TOP_MID], $this->chars[self::CHAR_TOP], $this->chars[self::CHAR_TOP_RIGHT]);
        // Header
        if ($header_count > 0) {
            $buffer .= $this->render_row($this->headers, $this->chars[self::CHAR_LEFT], $this->chars[self::CHAR_MIDDLE], $this->chars[self::CHAR_RIGHT]);
        }
        // Content
        foreach ($this->rows as $i => $row) {
            if ($i > 0 || $header_count > 0) {
                $buffer .= $this->render_separator($this->chars[self::CHAR_LEFT_MID], $this->chars[self::CHAR_MID_MID], $this->chars[self::CHAR_MID], $this->chars[self::CHAR_RIGHT_MID]);
            }
            $buffer .= $this->render_row($row, $this->chars[self::CHAR_LEFT], $this->chars[self::CHAR_MIDDLE], $this->chars[self::CHAR_RIGHT]);
        }
        return $buffer . $this->render_separator($this->chars[self::CHAR_BOTTOM_LEFT], $this->chars[self::CHAR_BOTTOM_MID], $this->chars[self::CHAR_BOTTOM], $this->chars[self::CHAR_BOTTOM_RIGHT]);
    }
    /**
     * Renders a row of data into a string.
     *
     * @param array $row row of data
     * @param string $spanLeft character for left border
     * @param string $spanMiddle character for middle border
     * @param string $spanRight character for right border
     * @see \yii\console\widgets\Table::render()
     */
    protected function render_row(array $row, string $span_left, string $span_middle, $span_right): string
    {
        $size = $this->column_widths;
        $buffer = '';
        $array_pointer = [];
        $rendered_chunk_texts = [];
        for ($i = 0, ($max = $this->calculate_row_height($row)) ?: $max = 1; $i < $max; $i++) {
            $buffer .= $span_left . ' ';
            foreach ($size as $index => $cell_size) {
                $cell = $row[$index] ?? null;
                $prefix = '';
                if ($index !== 0) {
                    $buffer .= $span_middle . ' ';
                }
                $array_from_multiline_string = false;
                if (is_string($cell)) {
                    $cell_lines = explode(PHP_EOL, $cell);
                    if (count($cell_lines) > 1) {
                        $cell = $cell_lines;
                        $array_from_multiline_string = true;
                    }
                }
                if (is_array($cell)) {
                    if (empty($rendered_chunk_texts[$index])) {
                        $rendered_chunk_texts[$index] = '';
                        $start = 0;
                        $prefix = $array_from_multiline_string ? '' : $this->list_prefix;
                        if (!isset($array_pointer[$index])) {
                            $array_pointer[$index] = 0;
                        }
                    } else {
                        $start = mb_strwidth($rendered_chunk_texts[$index], Yii::$app->charset);
                    }
                    $chunk = Console::ansi_colorized_substr($cell[$array_pointer[$index]], $start, $cell_size - 2 - Console::ansi_strwidth($prefix));
                    $rendered_chunk_texts[$index] .= Console::strip_ansi_format($chunk);
                    $full_chunk_text = Console::strip_ansi_format($cell[$array_pointer[$index]]);
                    if (isset($cell[$array_pointer[$index] + 1]) && $rendered_chunk_texts[$index] === $full_chunk_text) {
                        $array_pointer[$index]++;
                        $rendered_chunk_texts[$index] = '';
                    }
                } else {
                    $chunk = Console::ansi_colorized_substr($cell, $cell_size * $i - $i * 2, $cell_size - 2);
                }
                $chunk = $prefix . $chunk;
                $repeat = $cell_size - Console::ansi_strwidth($chunk) - 1;
                $buffer .= $chunk;
                if ($repeat >= 0) {
                    $buffer .= str_repeat(' ', $repeat);
                }
            }
            $buffer .= "{$span_right}\n";
        }
        return $buffer;
    }
    /**
     * Renders separator.
     *
     * @param string $spanLeft character for left border
     * @param string $spanMid character for middle border
     * @param string $spanMidMid character for middle-middle border
     * @param string $spanRight character for right border
     * @return string the generated separator row
     * @see \yii\console\widgets\Table::render()
     */
    protected function render_separator($span_left, string $span_mid, $span_mid_mid, string $span_right): string
    {
        $separator = $span_left;
        foreach ($this->column_widths as $index => $row_size) {
            if ($index !== 0) {
                $separator .= $span_mid;
            }
            $separator .= str_repeat($span_mid_mid, $row_size);
        }
        return $separator . ($span_right . "\n");
    }
    /**
     * Calculate the size of rows to draw anchor of columns in console.
     *
     * @see \yii\console\widgets\Table::render()
     */
    protected function calculate_rows_size()
    {
        $this->column_widths = $columns = [];
        $total_width = 0;
        $screen_width = $this->get_screen_width() - self::CONSOLE_SCROLLBAR_OFFSET;
        $header_count = count($this->headers);
        if (empty($this->rows)) {
            $row_col_count = 0;
        } else {
            $row_col_count = max(array_map('count', $this->rows));
        }
        $count = max($header_count, $row_col_count);
        for ($i = 0; $i < $count; $i++) {
            $columns[] = Array_Helper::get_column($this->rows, $i);
            if ($i < $header_count) {
                $columns[$i][] = $this->headers[$i];
            }
        }
        foreach ($columns as $column) {
            $column_width = max(array_map(function ($val) {
                if (is_array($val)) {
                    return max(array_map('yii\helpers\Console::ansiStrwidth', $val)) + Console::ansi_strwidth($this->list_prefix);
                }
                if (is_string($val)) {
                    return max(array_map('yii\helpers\Console::ansiStrwidth', explode(PHP_EOL, $val)));
                }
                return Console::ansi_strwidth($val);
            }, $column)) + 2;
            $this->column_widths[] = $column_width;
            $total_width += $column_width;
        }
        if ($total_width > $screen_width) {
            $min_width = 3;
            $fix_widths = [];
            $relative_width = $screen_width / $total_width;
            foreach ($this->column_widths as $j => $width) {
                $scaled_width = (int) ($width * $relative_width);
                if ($scaled_width < $min_width) {
                    $fix_widths[$j] = 3;
                }
            }
            $total_fix_width = array_sum($fix_widths);
            $relative_width = ($screen_width - $total_fix_width) / ($total_width - $total_fix_width);
            foreach ($this->column_widths as $j => $width) {
                if (!array_key_exists($j, $fix_widths)) {
                    $this->column_widths[$j] = (int) ($width * $relative_width);
                }
            }
        }
    }
    /**
     * Calculate the height of a row.
     *
     * @param array $row
     * @return int maximum row per cell
     * @see \yii\console\widgets\Table::render()
     */
    protected function calculate_row_height($row)
    {
        $rows_per_cell = array_map(function ($size, $column_width) {
            if (is_array($column_width)) {
                $rows = 0;
                foreach ($column_width as $width) {
                    $rows += $size == 2 ? 0 : ceil($width / ($size - 2));
                }
                return $rows;
            }
            return $size == 2 || $column_width == 0 ? 0 : ceil($column_width / ($size - 2));
        }, $this->column_widths, array_map(function ($val) {
            if (is_array($val)) {
                return array_map('yii\helpers\Console::ansiStrwidth', $val);
            }
            if (is_string($val)) {
                return array_map('yii\helpers\Console::ansiStrwidth', explode(PHP_EOL, $val));
            }
            return Console::ansi_strwidth($val);
        }, $row));
        return max($rows_per_cell);
    }
    /**
     * Getting screen width.
     * If it is not able to determine screen width, default value `123` will be set.
     *
     * @return int screen width
     */
    protected function get_screen_width()
    {
        if (!$this->screen_width) {
            $size = Console::get_screen_size();
            $this->screen_width = $size[0] ?? self::DEFAULT_CONSOLE_SCREEN_WIDTH + self::CONSOLE_SCROLLBAR_OFFSET;
        }
        return $this->screen_width;
    }
}