<?php

/**
 * @var \yii\web\View $this
 * @var string $table
 * @var array $foreignKeys
 * @var array $fields
 */
echo $this->render('_dropForeignKeys', ['table' => $table, 'foreignKeys' => $foreign_keys]);
foreach ($fields as $field) {
    ?>
        $this->dropColumn('<?php 
    echo $table;
    ?>', '<?php 
    echo $field['property'];
    ?>');
<?php 
}