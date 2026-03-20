<?php

/**
 * @var \yii\web\View $this
 * @var \Throwable $exception
 * @var \yii\web\ErrorHandler $handler
 */
if ($exception instanceof \yii\web\Http_Exception) {
    $code = $exception->status_code;
} else {
    $code = $exception->get_code();
}
$name = $handler->get_exception_name($exception);
if ($name === null) {
    $name = 'Error';
}
if ($code) {
    $name .= " (#{$code})";
}
if ($exception instanceof \yii\base\User_Exception) {
    $message = $exception->get_message();
} else {
    $message = 'An internal server error occurred.';
}
if (method_exists($this, 'beginPage')) {
    $this->begin_page();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?php 
echo $handler->html_encode($name);
?></title>

    <style>
        body {
            font: normal 9pt "Verdana";
            color: #000;
            background: #fff;
        }

        h1 {
            font: normal 18pt "Verdana";
            color: #f00;
            margin-bottom: .5em;
        }

        h2 {
            font: normal 14pt "Verdana";
            color: #800000;
            margin-bottom: .5em;
        }

        h3 {
            font: bold 11pt "Verdana";
        }

        p {
            font: normal 9pt "Verdana";
            color: #000;
        }

        .version {
            color: gray;
            font-size: 8pt;
            border-top: 1px solid #aaa;
            padding-top: 1em;
            margin-bottom: 1em;
        }
    </style>
</head>

<body>
    <h1><?php 
echo $handler->html_encode($name);
?></h1>
    <h2><?php 
echo nl2br($handler->html_encode($message));
?></h2>
    <p>
        The above error occurred while the Web server was processing your request.
    </p>
    <p>
        Please contact us if you think this is a server error. Thank you.
    </p>
    <div class="version">
        <?php 
echo date('Y-m-d H:i:s');
?>
    </div>
    <?php 
if (method_exists($this, 'endBody')) {
    ?>
        <?php 
    $this->end_body();
    ?>
    <?php 
}
?>
</body>
</html>
<?php 
if (method_exists($this, 'endPage')) {
    ?>
    <?php 
    $this->end_page();
}