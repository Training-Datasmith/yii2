<?php

/**
 * @var string|null $file
 * @var int|null $line
 * @var string|null $class
 * @var string|null $method
 * @var int $index
 * @var string[] $lines
 * @var int $begin
 * @var int $end
 * @var array $args
 * @var \yii\web\ErrorHandler $handler
 */
$html = <<<HTML
IDE
<svg class="icon icon--new-window" focusable="false" aria-hidden="true" width="16" height="16">
    <use href="#new-window"></use>
</svg>
HTML;
?>
<li class="<?php 
echo $index === 1 || !$handler->is_core_file($file) ? 'application' : '';
?> call-stack-item"
    data-line="<?php 
echo (int) ($line - $begin);
?>">
    <div class="element-wrap">
        <div class="element">
            <span class="item-number"><?php 
echo (int) $index;
?>.</span>
            <span class="text"><?php 
echo $file !== null ? 'in ' . $handler->html_encode($file) : '';
?></span>
            <?php 
if ($handler->trace_line !== '{html}') {
    ?>
                <span> &ndash; </span>
                <?php 
    echo strtr($handler->trace_line, ['{file}' => $file, '{line}' => $line + 1, '{html}' => $html]);
    ?>
            <?php 
}
?>
            <span class="at">
                <?php 
echo $line !== null ? 'at line' : '';
?>
                <span class="line"><?php 
echo $line !== null ? $line + 1 : '';
?></span>
            </span>
            <?php 
if ($method !== null) {
    ?>
                <span class="call">
                    <?php 
    echo $file !== null ? '&ndash;' : '';
    ?>
                    <?php 
    echo ($class !== null ? $handler->add_type_links("{$class}::{$method}") : $handler->html_encode($method)) . '(' . $handler->arguments_to_string($args) . ')';
    ?>
                </span>
            <?php 
}
?>
        </div>
    </div>
    <?php 
if (!empty($lines)) {
    ?>
        <div class="code-wrap">
            <div class="error-line"></div>
            <?php 
    for ($i = $begin; $i <= $end; ++$i) {
        ?><div class="hover-line"></div><?php 
    }
    ?>
            <div class="code">
                <?php 
    for ($i = $begin; $i <= $end; ++$i) {
        ?><span class="lines-item"><?php 
        echo (int) ($i + 1);
        ?></span><?php 
    }
    ?>
                <pre><?php 
    // fill empty lines with a whitespace to avoid rendering problems in opera
    for ($i = $begin; $i <= $end; ++$i) {
        echo trim($lines[$i]) === '' ? " \n" : $handler->html_encode($lines[$i]);
    }
    ?></pre>
            </div>
        </div>
    <?php 
}
?>
</li>
