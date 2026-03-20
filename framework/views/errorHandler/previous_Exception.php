<?php

/**
 * @var \yii\base\Exception $exception
 * @var \yii\web\ErrorHandler $handler
 */
?>
<div class="previous">
    <span class="arrow">&crarr;</span>
    <h2>
        <span>Caused by:</span>
        <?php 
$name = $handler->get_exception_name($exception);
?>
        <?php 
if ($name !== null) {
    ?>
            <span><?php 
    echo $handler->html_encode($name);
    ?></span> &ndash;
            <?php 
    echo $handler->add_type_links(get_class($exception));
    ?>
        <?php 
} else {
    ?>
            <span><?php 
    echo $handler->html_encode(get_class($exception));
    ?></span>
        <?php 
}
?>
    </h2>
    <h3><?php 
echo nl2br($handler->html_encode($exception->get_message()));
?></h3>
    <p>in <span class="file"><?php 
echo $exception->get_file();
?></span> at line <span class="line"><?php 
echo $exception->get_line();
?></span></p>
    <?php 
if ($exception instanceof \yii\db\Exception && !empty($exception->error_info)) {
    ?>
        <pre>Error Info: <?php 
    echo $handler->html_encode(print_r($exception->error_info, true));
    ?></pre>
    <?php 
}
?>
    <?php 
echo $handler->render_previous_exceptions($exception);
?>
</div>
