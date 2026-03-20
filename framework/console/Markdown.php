<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

use cebe\markdown\block\Fenced_Code_Trait;
use cebe\markdown\inline\Code_Trait;
use cebe\markdown\inline\Emph_Strong_Trait;
use cebe\markdown\inline\Strikeout_Trait;
use yii\helpers\Console;
/**
 * A Markdown parser that enhances markdown for reading in console environments.
 *
 * Based on [cebe/markdown](https://github.com/cebe/markdown).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Markdown extends \cebe\markdown\Parser
{
    use Fenced_Code_Trait;
    use Code_Trait;
    use Emph_Strong_Trait;
    use Strikeout_Trait;
    /**
     * @var array these are "escapeable" characters. When using one of these prefixed with a
     * backslash, the character will be outputted without the backslash and is not interpreted
     * as markdown.
     */
    protected $escape_characters = [
        '\\',
        // backslash
        '`',
        // backtick
        '*',
        // asterisk
        '_',
        // underscore
        '~',
    ];
    /**
     * Renders a code block.
     *
     * @param array $block
     * @return string
     */
    protected function render_code($block)
    {
        return Console::ansi_format($block['content'], [Console::NEGATIVE]) . "\n\n";
    }
    /**
     * Render a paragraph block.
     *
     * @param array $block
     * @return string
     */
    protected function render_paragraph($block)
    {
        return rtrim($this->render_absy($block['content'])) . "\n\n";
    }
    /**
     * Renders an inline code span `` ` ``.
     * @param array $element
     * @return string
     */
    protected function render_inline_code($element)
    {
        return Console::ansi_format($element[1], [Console::UNDERLINE]);
    }
    /**
     * Renders empathized elements.
     * @param array $element
     * @return string
     */
    protected function render_emph($element)
    {
        return Console::ansi_format($this->render_absy($element[1]), [Console::ITALIC]);
    }
    /**
     * Renders strong elements.
     * @param array $element
     * @return string
     */
    protected function render_strong($element)
    {
        return Console::ansi_format($this->render_absy($element[1]), [Console::BOLD]);
    }
    /**
     * Renders the strike through feature.
     * @param array $element
     * @return string
     */
    protected function render_strike($element)
    {
        return Console::ansi_format($this->parse_inline($this->render_absy($element[1])), [Console::CROSSED_OUT]);
    }
}