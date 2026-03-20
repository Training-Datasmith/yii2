<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use Yii;
use yii\base\Model;
use yii\console\Markdown as ConsoleMarkdown;
/**
 * BaseConsole provides concrete implementation for [[Console]].
 *
 * Do not use BaseConsole. Use [[Console]] instead.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Base_Console
{
    // foreground color control codes
    public const FG_BLACK = 30;
    public const FG_RED = 31;
    public const FG_GREEN = 32;
    public const FG_YELLOW = 33;
    public const FG_BLUE = 34;
    public const FG_PURPLE = 35;
    public const FG_CYAN = 36;
    public const FG_GREY = 37;
    // background color control codes
    public const BG_BLACK = 40;
    public const BG_RED = 41;
    public const BG_GREEN = 42;
    public const BG_YELLOW = 43;
    public const BG_BLUE = 44;
    public const BG_PURPLE = 45;
    public const BG_CYAN = 46;
    public const BG_GREY = 47;
    // fonts style control codes
    public const RESET = 0;
    public const NORMAL = 0;
    public const BOLD = 1;
    public const ITALIC = 3;
    public const UNDERLINE = 4;
    public const BLINK = 5;
    public const NEGATIVE = 7;
    public const CONCEALED = 8;
    public const CROSSED_OUT = 9;
    public const FRAMED = 51;
    public const ENCIRCLED = 52;
    public const OVERLINED = 53;
    /**
     * Moves the terminal cursor up by sending ANSI control code CUU to the terminal.
     * If the cursor is already at the edge of the screen, this has no effect.
     * @param int $rows number of rows the cursor should be moved up
     */
    public static function move_cursor_up($rows = 1): void
    {
        echo "\x1b[" . (int) $rows . 'A';
    }
    /**
     * Moves the terminal cursor down by sending ANSI control code CUD to the terminal.
     * If the cursor is already at the edge of the screen, this has no effect.
     * @param int $rows number of rows the cursor should be moved down
     */
    public static function move_cursor_down($rows = 1): void
    {
        echo "\x1b[" . (int) $rows . 'B';
    }
    /**
     * Moves the terminal cursor forward by sending ANSI control code CUF to the terminal.
     * If the cursor is already at the edge of the screen, this has no effect.
     * @param int $steps number of steps the cursor should be moved forward
     */
    public static function move_cursor_forward($steps = 1): void
    {
        echo "\x1b[" . (int) $steps . 'C';
    }
    /**
     * Moves the terminal cursor backward by sending ANSI control code CUB to the terminal.
     * If the cursor is already at the edge of the screen, this has no effect.
     * @param int $steps number of steps the cursor should be moved backward
     */
    public static function move_cursor_backward($steps = 1): void
    {
        echo "\x1b[" . (int) $steps . 'D';
    }
    /**
     * Moves the terminal cursor to the beginning of the next line by sending ANSI control code CNL to the terminal.
     * @param int $lines number of lines the cursor should be moved down
     */
    public static function move_cursor_next_line($lines = 1): void
    {
        echo "\x1b[" . (int) $lines . 'E';
    }
    /**
     * Moves the terminal cursor to the beginning of the previous line by sending ANSI control code CPL to the terminal.
     * @param int $lines number of lines the cursor should be moved up
     */
    public static function move_cursor_prev_line($lines = 1): void
    {
        echo "\x1b[" . (int) $lines . 'F';
    }
    /**
     * Moves the cursor to an absolute position given as column and row by sending ANSI control code CUP or CHA to the terminal.
     * @param int $column 1-based column number, 1 is the left edge of the screen.
     * @param int|null $row 1-based row number, 1 is the top edge of the screen. if not set, will move cursor only in current line.
     */
    public static function move_cursor_to($column, $row = null): void
    {
        if ($row === null) {
            echo "\x1b[" . (int) $column . 'G';
        } else {
            echo "\x1b[" . (int) $row . ';' . (int) $column . 'H';
        }
    }
    /**
     * Scrolls whole page up by sending ANSI control code SU to the terminal.
     * New lines are added at the bottom. This is not supported by ANSI.SYS used in windows.
     * @param int $lines number of lines to scroll up
     */
    public static function scroll_up($lines = 1): void
    {
        echo "\x1b[" . (int) $lines . 'S';
    }
    /**
     * Scrolls whole page down by sending ANSI control code SD to the terminal.
     * New lines are added at the top. This is not supported by ANSI.SYS used in windows.
     * @param int $lines number of lines to scroll down
     */
    public static function scroll_down($lines = 1): void
    {
        echo "\x1b[" . (int) $lines . 'T';
    }
    /**
     * Saves the current cursor position by sending ANSI control code SCP to the terminal.
     * Position can then be restored with [[restoreCursorPosition()]].
     */
    public static function save_cursor_position(): void
    {
        echo "\x1b[s";
    }
    /**
     * Restores the cursor position saved with [[saveCursorPosition()]] by sending ANSI control code RCP to the terminal.
     */
    public static function restore_cursor_position(): void
    {
        echo "\x1b[u";
    }
    /**
     * Hides the cursor by sending ANSI DECTCEM code ?25l to the terminal.
     * Use [[showCursor()]] to bring it back.
     * Do not forget to show cursor when your application exits. Cursor might stay hidden in terminal after exit.
     */
    public static function hide_cursor(): void
    {
        echo "\x1b[?25l";
    }
    /**
     * Will show a cursor again when it has been hidden by [[hideCursor()]]  by sending ANSI DECTCEM code ?25h to the terminal.
     */
    public static function show_cursor(): void
    {
        echo "\x1b[?25h";
    }
    /**
     * Clears entire screen content by sending ANSI control code ED with argument 2 to the terminal.
     * Cursor position will not be changed.
     * **Note:** ANSI.SYS implementation used in windows will reset cursor position to upper left corner of the screen.
     */
    public static function clear_screen(): void
    {
        echo "\x1b[2J";
    }
    /**
     * Clears text from cursor to the beginning of the screen by sending ANSI control code ED with argument 1 to the terminal.
     * Cursor position will not be changed.
     */
    public static function clear_screen_before_cursor(): void
    {
        echo "\x1b[1J";
    }
    /**
     * Clears text from cursor to the end of the screen by sending ANSI control code ED with argument 0 to the terminal.
     * Cursor position will not be changed.
     */
    public static function clear_screen_after_cursor(): void
    {
        echo "\x1b[0J";
    }
    /**
     * Clears the line, the cursor is currently on by sending ANSI control code EL with argument 2 to the terminal.
     * Cursor position will not be changed.
     */
    public static function clear_line(): void
    {
        echo "\x1b[2K";
    }
    /**
     * Clears text from cursor position to the beginning of the line by sending ANSI control code EL with argument 1 to the terminal.
     * Cursor position will not be changed.
     */
    public static function clear_line_before_cursor(): void
    {
        echo "\x1b[1K";
    }
    /**
     * Clears text from cursor position to the end of the line by sending ANSI control code EL with argument 0 to the terminal.
     * Cursor position will not be changed.
     */
    public static function clear_line_after_cursor(): void
    {
        echo "\x1b[0K";
    }
    /**
     * Returns the ANSI format code.
     *
     * @param array $format An array containing formatting values.
     * You can pass any of the `FG_*`, `BG_*` and `TEXT_*` constants
     * and also [[xtermFgColor]] and [[xtermBgColor]] to specify a format.
     * @return string The ANSI format code according to the given formatting constants.
     */
    public static function ansi_format_code($format): string
    {
        return "\x1b[" . implode(';', $format) . 'm';
    }
    /**
     * Echoes an ANSI format code that affects the formatting of any text that is printed afterwards.
     *
     * @param array $format An array containing formatting values.
     * You can pass any of the `FG_*`, `BG_*` and `TEXT_*` constants
     * and also [[xtermFgColor]] and [[xtermBgColor]] to specify a format.
     * @see ansiFormatCode()
     * @see endAnsiFormat()
     */
    public static function begin_ansi_format($format): void
    {
        echo "\x1b[" . implode(';', $format) . 'm';
    }
    /**
     * Resets any ANSI format set by previous method [[beginAnsiFormat()]]
     * Any output after this will have default text format.
     * This is equal to calling.
     *
     * ```
     * echo Console::ansiFormatCode([Console::RESET])
     * ```
     */
    public static function end_ansi_format(): void
    {
        echo "\x1b[0m";
    }
    /**
     * Will return a string formatted with the given ANSI style.
     *
     * @param string $string the string to be formatted
     * @param array $format An array containing formatting values.
     * You can pass any of the `FG_*`, `BG_*` and `TEXT_*` constants
     * and also [[xtermFgColor]] and [[xtermBgColor]] to specify a format.
     */
    public static function ansi_format(string $string, $format = []): string
    {
        $code = implode(';', $format);
        return "\x1b[0m" . ($code !== '' ? "\x1b[" . $code . 'm' : '') . $string . "\x1b[0m";
    }
    /**
     * Returns the ansi format code for xterm foreground color.
     *
     * You can pass the return value of this to one of the formatting methods:
     * [[ansiFormat]], [[ansiFormatCode]], [[beginAnsiFormat]].
     *
     * @param int $colorCode xterm color code
     * @see https://en.wikipedia.org/wiki/Talk:ANSI_escape_code#xterm-256colors
     */
    public static function xterm_fg_color($color_code): string
    {
        return '38;5;' . $color_code;
    }
    /**
     * Returns the ansi format code for xterm background color.
     *
     * You can pass the return value of this to one of the formatting methods:
     * [[ansiFormat]], [[ansiFormatCode]], [[beginAnsiFormat]].
     *
     * @param int $colorCode xterm color code
     * @see https://en.wikipedia.org/wiki/Talk:ANSI_escape_code#xterm-256colors
     */
    public static function xterm_bg_color($color_code): string
    {
        return '48;5;' . $color_code;
    }
    /**
     * Strips ANSI control codes from a string.
     *
     * @param string $string String to strip
     * @return string
     */
    public static function strip_ansi_format($string): ?string
    {
        return preg_replace(self::ansi_codes_pattern(), '', (string) $string);
    }
    /**
     * Returns the length of the string without ANSI color codes.
     * @param string $string the string to measure
     * @return int the length of the string not counting ANSI format characters
     */
    public static function ansi_strlen($string): int
    {
        return mb_strlen(static::strip_ansi_format($string));
    }
    /**
     * Returns the width of the string without ANSI color codes.
     * @param string $string the string to measure
     * @return int the width of the string not counting ANSI format characters
     * @since 2.0.36
     */
    public static function ansi_strwidth($string): int
    {
        return mb_strwidth(static::strip_ansi_format($string), Yii::$app->charset);
    }
    /**
     * Returns the portion with ANSI color codes of string specified by the start and length parameters.
     * If string has color codes, then will be return "TEXT_COLOR + TEXT_STRING + DEFAULT_COLOR",
     * else will be simple "TEXT_STRING".
     * @param string $string
     * @param int $start
     * @param int $length
     */
    public static function ansi_colorized_substr($string, $start, $length): string
    {
        if ($start < 0 || $length <= 0) {
            return '';
        }
        $text_items = preg_split(self::ansi_codes_pattern(), (string) $string);
        preg_match_all(self::ansi_codes_pattern(), (string) $string, $colors);
        $colors = count($colors) ? $colors[0] : [];
        array_unshift($colors, '');
        $result = '';
        $cur_pos = 0;
        $in_range = false;
        foreach ($text_items as $k => $text_item) {
            $color = $colors[$k];
            if ($cur_pos <= $start && $start < $cur_pos + Console::ansi_strwidth($text_item)) {
                $text = mb_substr($text_item, $start - $cur_pos, null, Yii::$app->charset);
                $in_range = true;
            } else {
                $text = $text_item;
            }
            if ($in_range) {
                $result .= $color . $text;
                $diff = $length - Console::ansi_strwidth($result);
                if ($diff <= 0) {
                    if ($diff < 0) {
                        $result = mb_substr($result, 0, $diff, Yii::$app->charset);
                    }
                    $default_color = static::render_colored_string('%n');
                    if ($color && $color != $default_color) {
                        $result .= $default_color;
                    }
                    break;
                }
            }
            $cur_pos += mb_strlen($text_item, Yii::$app->charset);
        }
        return $result;
    }
    private static function ansi_codes_pattern(): string
    {
        return '/\033\[[\d;?]*\w/';
    }
    /**
     * Converts an ANSI formatted string to HTML.
     *
     * Note: xTerm 256 bit colors are currently not supported.
     *
     * @param string $string the string to convert.
     * @param array $styleMap an optional mapping of ANSI control codes such as
     * FG\_*COLOR* or [[BOLD]] to a set of css style definitions.
     * The CSS style definitions are represented as an array where the array keys correspond
     * to the css style attribute names and the values are the css values.
     * values may be arrays that will be merged and imploded with `' '` when rendered.
     * @return string HTML representation of the ANSI formatted string
     */
    public static function ansi_to_html($string, $style_map = [])
    {
        $style_map = [
            // https://www.w3.org/TR/CSS2/syndata.html#value-def-color
            self::FG_BLACK => ['color' => 'black'],
            self::FG_BLUE => ['color' => 'blue'],
            self::FG_CYAN => ['color' => 'aqua'],
            self::FG_GREEN => ['color' => 'lime'],
            self::FG_GREY => ['color' => 'silver'],
            // https://meyerweb.com/eric/thoughts/2014/06/19/rebeccapurple/
            // https://drafts.csswg.org/css-color/#valuedef-rebeccapurple
            self::FG_PURPLE => ['color' => 'rebeccapurple'],
            self::FG_RED => ['color' => 'red'],
            self::FG_YELLOW => ['color' => 'yellow'],
            self::BG_BLACK => ['background-color' => 'black'],
            self::BG_BLUE => ['background-color' => 'blue'],
            self::BG_CYAN => ['background-color' => 'aqua'],
            self::BG_GREEN => ['background-color' => 'lime'],
            self::BG_GREY => ['background-color' => 'silver'],
            self::BG_PURPLE => ['background-color' => 'rebeccapurple'],
            self::BG_RED => ['background-color' => 'red'],
            self::BG_YELLOW => ['background-color' => 'yellow'],
            self::BOLD => ['font-weight' => 'bold'],
            self::ITALIC => ['font-style' => 'italic'],
            self::UNDERLINE => ['text-decoration' => ['underline']],
            self::OVERLINED => ['text-decoration' => ['overline']],
            self::CROSSED_OUT => ['text-decoration' => ['line-through']],
            self::BLINK => ['text-decoration' => ['blink']],
            self::CONCEALED => ['visibility' => 'hidden'],
        ] + $style_map;
        $tags = 0;
        $result = preg_replace_callback('/\033\[([\d;]+)m/', function (array $ansi) use (&$tags, $style_map) {
            $style = [];
            $reset = false;
            $negative = false;
            foreach (explode(';', $ansi[1]) as $control_code) {
                if ($control_code == 0) {
                    $style = [];
                    $reset = true;
                } elseif ($control_code == self::NEGATIVE) {
                    $negative = true;
                } elseif (isset($style_map[$control_code])) {
                    $style[] = $style_map[$control_code];
                }
            }
            $return = '';
            while ($reset && $tags > 0) {
                $return .= '</span>';
                $tags--;
            }
            if (empty($style)) {
                return $return;
            }
            $current_style = [];
            foreach ($style as $content) {
                $current_style = Array_Helper::merge($current_style, $content);
            }
            // if negative is set, invert background and foreground
            if ($negative) {
                if (isset($current_style['color'])) {
                    $fg_color = $current_style['color'];
                    unset($current_style['color']);
                }
                if (isset($current_style['background-color'])) {
                    $bg_color = $current_style['background-color'];
                    unset($current_style['background-color']);
                }
                if (isset($fg_color)) {
                    $current_style['background-color'] = $fg_color;
                }
                if (isset($bg_color)) {
                    $current_style['color'] = $bg_color;
                }
            }
            $style_string = '';
            foreach ($current_style as $name => $value) {
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }
                $style_string .= "{$name}: {$value};";
            }
            $tags++;
            return "{$return}<span style=\"{$style_string}\">";
        }, $string);
        while ($tags > 0) {
            $result .= '</span>';
            $tags--;
        }
        return $result;
    }
    /**
     * Converts Markdown to be better readable in console environments by applying some ANSI format.
     * @param string $markdown the markdown string.
     * @return string the parsed result as ANSI formatted string.
     */
    public static function markdown_to_ansi($markdown)
    {
        $parser = new Console_Markdown();
        return $parser->parse($markdown);
    }
    /**
     * Converts a string to ansi formatted by replacing patterns like %y (for yellow) with ansi control codes.
     *
     * Uses almost the same syntax as https://github.com/pear/Console_Color2/blob/master/Console/Color2.php
     * The conversion table is: ('bold' meaning 'light' on some
     * terminals). It's almost the same conversion table irssi uses.
     * <pre>
     *                  text      text            background
     *      ------------------------------------------------
     *      %k %K %0    black     dark grey       black
     *      %r %R %1    red       bold red        red
     *      %g %G %2    green     bold green      green
     *      %y %Y %3    yellow    bold yellow     yellow
     *      %b %B %4    blue      bold blue       blue
     *      %m %M %5    magenta   bold magenta    magenta
     *      %p %P       magenta (think: purple)
     *      %c %C %6    cyan      bold cyan       cyan
     *      %w %W %7    white     bold white      white
     *
     *      %F     Blinking, Flashing
     *      %U     Underline
     *      %8     Reverse
     *      %_,%9  Bold
     *
     *      %n     Resets the color
     *      %%     A single %
     * </pre>
     * First param is the string to convert, second is an optional flag if
     * colors should be used. It defaults to true, if set to false, the
     * color codes will just be removed (And %% will be transformed into %)
     *
     * @param string $string String to convert
     * @param bool $colored Should the string be colored?
     * @return string
     */
    public static function render_colored_string($string, $colored = true): ?string
    {
        // TODO rework/refactor according to https://github.com/yiisoft/yii2/issues/746
        static $conversions = [
            '%y' => [self::FG_YELLOW],
            '%g' => [self::FG_GREEN],
            '%b' => [self::FG_BLUE],
            '%r' => [self::FG_RED],
            '%p' => [self::FG_PURPLE],
            '%m' => [self::FG_PURPLE],
            '%c' => [self::FG_CYAN],
            '%w' => [self::FG_GREY],
            '%k' => [self::FG_BLACK],
            '%n' => [0],
            // reset
            '%Y' => [self::FG_YELLOW, self::BOLD],
            '%G' => [self::FG_GREEN, self::BOLD],
            '%B' => [self::FG_BLUE, self::BOLD],
            '%R' => [self::FG_RED, self::BOLD],
            '%P' => [self::FG_PURPLE, self::BOLD],
            '%M' => [self::FG_PURPLE, self::BOLD],
            '%C' => [self::FG_CYAN, self::BOLD],
            '%W' => [self::FG_GREY, self::BOLD],
            '%K' => [self::FG_BLACK, self::BOLD],
            '%N' => [0, self::BOLD],
            '%3' => [self::BG_YELLOW],
            '%2' => [self::BG_GREEN],
            '%4' => [self::BG_BLUE],
            '%1' => [self::BG_RED],
            '%5' => [self::BG_PURPLE],
            '%6' => [self::BG_CYAN],
            '%7' => [self::BG_GREY],
            '%0' => [self::BG_BLACK],
            '%F' => [self::BLINK],
            '%U' => [self::UNDERLINE],
            '%8' => [self::NEGATIVE],
            '%9' => [self::BOLD],
            '%_' => [self::BOLD],
        ];
        if ($colored) {
            $string = str_replace('%%', '% ', $string);
            foreach ($conversions as $key => $value) {
                $string = str_replace($key, static::ansi_format_code($value), $string);
            }
            return str_replace('% ', '%', $string);
        }
        return preg_replace('/%((%)|.)/', '$2', $string);
    }
    /**
     * Escapes % so they don't get interpreted as color codes when
     * the string is parsed by [[renderColoredString]].
     *
     * @param string $string String to escape
     */
    public static function escape($string): string
    {
        // TODO rework/refactor according to https://github.com/yiisoft/yii2/issues/746
        return str_replace('%', '%%', $string);
    }
    /**
     * Returns true if the stream supports colorization. ANSI colors are disabled if not supported by the stream.
     *
     * - windows without ansicon
     * - not tty consoles
     *
     * @param mixed $stream
     * @return bool true if the stream supports ANSI colors, otherwise false.
     */
    public static function stream_supports_ansi_colors($stream): bool
    {
        return DIRECTORY_SEPARATOR === '\\' ? getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' : function_exists('posix_isatty') && @posix_isatty($stream);
    }
    /**
     * Returns true if the console is running on windows.
     */
    public static function is_running_on_windows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
    /**
     * Returns terminal screen size.
     *
     * Usage:
     *
     * ```
     * list($width, $height) = ConsoleHelper::getScreenSize();
     * ```
     *
     * @param bool $refresh whether to force checking and not re-use cached size value.
     * This is useful to detect changing window size while the application is running but may
     * not get up to date values on every terminal.
     * @return array|bool An array of ($width, $height) or false when it was not able to determine size.
     */
    public static function get_screen_size($refresh = false)
    {
        static $size;
        static $exec_disabled;
        if ($size !== null && ($exec_disabled || !$refresh)) {
            return $size;
        }
        if ($exec_disabled === null) {
            $exec_disabled = !function_exists('ini_get') || preg_match('/(\bexec\b)/i', ini_get('disable_functions'));
            if ($exec_disabled) {
                return $size = false;
            }
        }
        if (static::is_running_on_windows()) {
            $output = [];
            exec('mode con', $output);
            if (isset($output[1]) && strpos($output[1], 'CON') !== false) {
                return $size = [(int) preg_replace('~\D~', '', $output[4]), (int) preg_replace('~\D~', '', $output[3])];
            }
        } else {
            // try stty if available
            $stty = [];
            if (exec('stty -a 2>&1', $stty)) {
                $stty = implode(' ', $stty);
                // Linux stty output
                if (preg_match('/rows\s+(\d+);\s*columns\s+(\d+);/mi', $stty, $matches)) {
                    return $size = [(int) $matches[2], (int) $matches[1]];
                }
                // MacOS stty output
                if (preg_match('/(\d+)\s+rows;\s*(\d+)\s+columns;/mi', $stty, $matches)) {
                    return $size = [(int) $matches[2], (int) $matches[1]];
                }
            }
            // fallback to tput, which may not be updated on terminal resize
            if (($width = (int) exec('tput cols 2>&1')) > 0 && ($height = (int) exec('tput lines 2>&1')) > 0) {
                return $size = [$width, $height];
            }
            // fallback to ENV variables, which may not be updated on terminal resize
            if (($width = (int) getenv('COLUMNS')) > 0 && ($height = (int) getenv('LINES')) > 0) {
                return $size = [$width, $height];
            }
        }
        return $size = false;
    }
    /**
     * Word wrap text with indentation to fit the screen size.
     *
     * If screen size could not be detected, or the indentation is greater than the screen size, the text will not be wrapped.
     *
     * The first line will **not** be indented, so `Console::wrapText("Lorem ipsum dolor sit amet.", 4)` will result in the
     * following output, given the screen width is 16 characters:
     *
     * ```
     * Lorem ipsum
     *     dolor sit
     *     amet.
     * ```
     *
     * @param string $text the text to be wrapped
     * @param int $indent number of spaces to use for indentation.
     * @param bool $refresh whether to force refresh of screen size.
     * This will be passed to [[getScreenSize()]].
     * @return string the wrapped text.
     * @since 2.0.4
     */
    public static function wrap_text($text, $indent = 0, $refresh = false)
    {
        $size = static::get_screen_size($refresh);
        if ($size === false || $size[0] <= $indent) {
            return $text;
        }
        $pad = str_repeat(' ', $indent);
        $lines = explode("\n", wordwrap($text, $size[0] - $indent, "\n"));
        $first = true;
        foreach ($lines as $i => $line) {
            if ($first) {
                $first = false;
                continue;
            }
            $lines[$i] = $pad . $line;
        }
        return implode("\n", $lines);
    }
    /**
     * Gets input from STDIN and returns a string right-trimmed for EOLs.
     *
     * @param bool $raw If set to true, returns the raw string without trimming
     * @return string the string read from stdin
     */
    public static function stdin($raw = false)
    {
        return $raw ? fgets(\STDIN) : rtrim(fgets(\STDIN), PHP_EOL);
    }
    /**
     * Prints a string to STDOUT.
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public static function stdout($string)
    {
        return fwrite(\STDOUT, $string);
    }
    /**
     * Prints a string to STDERR.
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public static function stderr($string)
    {
        return fwrite(\STDERR, $string);
    }
    /**
     * Asks the user for input. Ends when the user types a carriage return (PHP_EOL). Optionally, It also provides a
     * prompt.
     *
     * @param string|null $prompt the prompt to display before waiting for input (optional)
     * @return string the user's input
     */
    public static function input($prompt = null)
    {
        if (isset($prompt)) {
            static::stdout($prompt);
        }
        return static::stdin();
    }
    /**
     * Prints text to STDOUT appended with a carriage return (PHP_EOL).
     *
     * @param string|null $string the text to print
     * @return int|bool number of bytes printed or false on error.
     */
    public static function output($string = null)
    {
        return static::stdout($string . PHP_EOL);
    }
    /**
     * Prints text to STDERR appended with a carriage return (PHP_EOL).
     *
     * @param string|null $string the text to print
     * @return int|bool number of bytes printed or false on error.
     */
    public static function error($string = null)
    {
        return static::stderr($string . PHP_EOL);
    }
    /**
     * Prompts the user for input and validates it.
     *
     * @param string $text prompt string
     * @param array $options the options to validate the input:
     *
     * - `required`: whether it is required or not
     * - `default`: default value if no input is inserted by the user
     * - `pattern`: regular expression pattern to validate user input
     * - `validator`: a callable function to validate input. The function must accept two parameters:
     * - `input`: the user input to validate
     * - `error`: the error value passed by reference if validation failed.
     *
     * @return string the user input
     */
    public static function prompt($text, $options = [])
    {
        $options = Array_Helper::merge(['required' => false, 'default' => null, 'pattern' => null, 'validator' => null, 'error' => 'Invalid input.'], $options);
        $error = null;
        top:
        $input = $options['default'] ? static::input("{$text} [" . $options['default'] . '] ') : static::input("{$text} ");
        if ($input === '') {
            if (isset($options['default'])) {
                $input = $options['default'];
            } elseif ($options['required']) {
                static::output($options['error']);
                goto top;
            }
        } elseif ($options['pattern'] && !preg_match($options['pattern'], $input)) {
            static::output($options['error']);
            goto top;
        } elseif ($options['validator'] && !call_user_func_array($options['validator'], [$input, &$error])) {
            static::output($error ?? $options['error']);
            goto top;
        }
        return $input;
    }
    /**
     * Asks user to confirm by typing y or n.
     *
     * A typical usage looks like the following:
     *
     * ```
     * if (Console::confirm("Are you sure?")) {
     *     echo "user typed yes\n";
     * } else {
     *     echo "user typed no\n";
     * }
     * ```
     *
     * @param string $message to print out before waiting for user input
     * @param bool $default this value is returned if no selection is made.
     * @return bool whether user confirmed
     */
    public static function confirm(string $message, $default = false)
    {
        while (true) {
            static::stdout($message . ' (yes|no) [' . ($default ? 'yes' : 'no') . ']:');
            $input = trim(static::stdin());
            if (empty($input)) {
                return $default;
            }
            if (!strcasecmp($input, 'y') || !strcasecmp($input, 'yes')) {
                return true;
            }
            if (!strcasecmp($input, 'n') || !strcasecmp($input, 'no')) {
                return false;
            }
        }
    }
    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array $options Key-value array of options to choose from. Key is what is inputed and used, value is
     * what's displayed to end user by help command.
     * @param string|null $default value to use when the user doesn't provide an option.
     * If the default is `null`, the user is required to select an option.
     *
     * @return string An option character the user chose
     * @since 2.0.49 Added the $default argument
     */
    public static function select($prompt, $options = [], $default = null)
    {
        top:
        static::stdout("{$prompt} (" . implode(',', array_keys($options)) . ',?)' . ($default !== null ? '[' . $default . ']' : '') . ': ');
        $input = static::stdin();
        if ($input === '?') {
            foreach ($options as $key => $value) {
                static::output(" {$key} - {$value}");
            }
            static::output(' ? - Show help');
            goto top;
        } elseif ($default !== null && $input === '') {
            return $default;
        } elseif (!array_key_exists($input, $options)) {
            goto top;
        }
        return $input;
    }
    private static ?int $_progress_start = null;
    private static $_progress_width;
    private static $_progress_prefix;
    private static $_progress_eta;
    private static $_progress_eta_last_done = 0;
    private static ?int $_progress_eta_last_update = null;
    /**
     * Starts display of a progress bar on screen.
     *
     * This bar will be updated by [[updateProgress()]] and may be ended by [[endProgress()]].
     *
     * The following example shows a simple usage of a progress bar:
     *
     * ```
     * Console::startProgress(0, 1000);
     * for ($n = 1; $n <= 1000; $n++) {
     *     usleep(1000);
     *     Console::updateProgress($n, 1000);
     * }
     * Console::endProgress();
     * ```
     *
     * Git clone like progress (showing only status information):
     *
     * ```
     * Console::startProgress(0, 1000, 'Counting objects: ', false);
     * for ($n = 1; $n <= 1000; $n++) {
     *     usleep(1000);
     *     Console::updateProgress($n, 1000);
     * }
     * Console::endProgress("done." . PHP_EOL);
     * ```
     *
     * @param int $done the number of items that are completed.
     * @param int $total the total value of items that are to be done.
     * @param string $prefix an optional string to display before the progress bar.
     * Default to empty string which results in no prefix to be displayed.
     * @param int|float|bool|null $width optional width of the progressbar. This can be an integer representing
     * the number of characters to display for the progress bar or a float between 0 and 1 representing the
     * percentage of screen with the progress bar may take. It can also be set to false to disable the
     * bar and only show progress information like percent, number of items and ETA.
     * If not set, the bar will be as wide as the screen. Screen size will be detected using [[getScreenSize()]].
     * @see startProgress
     * @see updateProgress
     * @see endProgress
     */
    public static function start_progress($done, $total, $prefix = '', $width = null): void
    {
        self::$_progress_start = time();
        self::$_progress_width = $width;
        self::$_progress_prefix = $prefix;
        self::$_progress_eta = null;
        self::$_progress_eta_last_done = 0;
        self::$_progress_eta_last_update = time();
        static::update_progress($done, $total);
    }
    /**
     * Updates a progress bar that has been started by [[startProgress()]].
     *
     * @param int $done the number of items that are completed.
     * @param int $total the total value of items that are to be done.
     * @param string|null $prefix an optional string to display before the progress bar.
     * Defaults to null meaning the prefix specified by [[startProgress()]] will be used.
     * If prefix is specified it will update the prefix that will be used by later calls.
     * @see startProgress
     * @see endProgress
     */
    public static function update_progress($done, $total, $prefix = null): void
    {
        if ($prefix === null) {
            $prefix = self::$_progress_prefix;
        } else {
            self::$_progress_prefix = $prefix;
        }
        $width = static::get_progressbar_width($prefix);
        $percent = $total == 0 ? 1 : $done / $total;
        $info = sprintf('%d%% (%d/%d)', $percent * 100, $done, $total);
        self::set_eta($done, $total);
        $info .= self::$_progress_eta === null ? ' ETA: n/a' : sprintf(' ETA: %d sec.', self::$_progress_eta);
        // Number extra characters outputted. These are opening [, closing ], and space before info
        // Since Windows uses \r\n\ for line endings, there's one more in the case
        $extra_chars = static::is_running_on_windows() ? 4 : 3;
        $width -= $extra_chars + static::ansi_strlen($info);
        // skipping progress bar on very small display or if forced to skip
        if ($width < 5) {
            static::stdout("\r{$prefix}{$info}   ");
        } else {
            if ($percent < 0) {
                $percent = 0;
            } elseif ($percent > 1) {
                $percent = 1;
            }
            $bar = floor($percent * $width);
            $status = str_repeat('=', $bar);
            if ($bar < $width) {
                $status .= '>';
                $status .= str_repeat(' ', $width - $bar - 1);
            }
            static::stdout("\r{$prefix}" . "[{$status}] {$info}");
        }
        flush();
    }
    /**
     * Return width of the progressbar
     * @param string $prefix an optional string to display before the progress bar.
     * @see updateProgress
     * @return int screen width
     * @since 2.0.14
     */
    private static function get_progressbar_width($prefix)
    {
        $width = self::$_progress_width;
        if ($width === false) {
            return 0;
        }
        $screen_size = static::get_screen_size(true);
        if ($screen_size === false && $width < 1) {
            return 0;
        }
        if ($width === null) {
            $width = $screen_size[0];
        } elseif ($width > 0 && $width < 1) {
            $width = floor($screen_size[0] * $width);
        }
        return $width - static::ansi_strlen($prefix);
    }
    /**
     * Calculate $_progressEta, $_progressEtaLastUpdate and $_progressEtaLastDone
     * @param int $done the number of items that are completed.
     * @param int $total the total value of items that are to be done.
     * @see updateProgress
     * @since 2.0.14
     */
    private static function set_eta($done, $total): void
    {
        if ($done > $total || $done == 0) {
            self::$_progress_eta = null;
            self::$_progress_eta_last_update = time();
            return;
        }
        if ($done < $total && (time() - self::$_progress_eta_last_update > 1 && $done > self::$_progress_eta_last_done)) {
            $rate = (time() - (self::$_progress_eta_last_update ?: self::$_progress_start)) / ($done - self::$_progress_eta_last_done);
            self::$_progress_eta = $rate * ($total - $done);
            self::$_progress_eta_last_update = time();
            self::$_progress_eta_last_done = $done;
        }
    }
    /**
     * Ends a progress bar that has been started by [[startProgress()]].
     *
     * @param string|bool $remove This can be `false` to leave the progress bar on screen and just print a newline.
     * If set to `true`, the line of the progress bar will be cleared. This may also be a string to be displayed instead
     * of the progress bar.
     * @param bool $keepPrefix whether to keep the prefix that has been specified for the progressbar when progressbar
     * gets removed. Defaults to true.
     * @see startProgress
     * @see updateProgress
     */
    public static function end_progress($remove = false, $keep_prefix = true): void
    {
        if ($remove === false) {
            static::stdout(PHP_EOL);
        } else {
            if (static::stream_supports_ansi_colors(STDOUT)) {
                static::clear_line();
            }
            static::stdout("\r" . ($keep_prefix ? self::$_progress_prefix : '') . (is_string($remove) ? $remove : ''));
        }
        flush();
        self::$_progress_start = null;
        self::$_progress_width = null;
        self::$_progress_prefix = '';
        self::$_progress_eta = null;
        self::$_progress_eta_last_done = 0;
        self::$_progress_eta_last_update = null;
    }
    /**
     * Generates a summary of the validation errors.
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *
     * @return string the generated error summary
     * @since 2.0.14
     */
    public static function error_summary($models, $options = []): string
    {
        $show_all_errors = Array_Helper::remove($options, 'showAllErrors', false);
        $lines = self::collect_errors($models, $show_all_errors);
        return implode(PHP_EOL, $lines);
    }
    /**
     * Return array of the validation errors
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param $showAllErrors boolean, if set to true every error message for each attribute will be shown otherwise
     * only the first error message for each attribute will be shown.
     * @return array of the validation errors
     * @since 2.0.14
     */
    private static function collect_errors($models, $show_all_errors): array
    {
        $lines = [];
        if (!is_array($models)) {
            $models = [$models];
        }
        foreach ($models as $model) {
            $lines = array_unique(array_merge($lines, $model->get_error_summary($show_all_errors)));
        }
        return $lines;
    }
}