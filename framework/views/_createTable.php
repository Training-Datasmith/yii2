<?php

/**
 * Creates a call for the method `yii\db\Migration::createTable()`.
 *
 * @var \yii\web\View $this
 * @var string $table the name table
 * @var array $fields the fields
 * @var array $foreignKeys the foreign keys
 */
?>        $this->createTable('<?php 
echo $table;
?>', [
<?php 
foreach ($fields as $field) {
    if (empty($field['decorators'])) {
        ?>
            '<?php 
        echo $field['property'];
        ?>',
<?php 
    } else {
        ?>
            <?php 
        echo "'{$field['property']}' => \$this->{$field['decorators']}";
        ?>,
<?php 
    }
}
?>
        ]);
<?php 
echo $this->render('_addForeignKeys', ['table' => $table, 'foreignKeys' => $foreign_keys]);