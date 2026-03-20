<?php

/**
 * This view is used by console/controllers/MigrateController.php.
 *
 * The following variables are available in this view:
 *
 * @var \yii\web\View $this
 * @var string $className the new migration class name without namespace
 * @var string $namespace the new migration class namespace
 * @var string $table the name table
 * @var string $tableComment the comment table
 * @var array $fields the fields
 * @var array $foreignKeys the foreign keys
 */
echo "<?php\n";
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

use yii\db\Migration;

/**
 * Handles the creation of table `<?php 
echo $table;
?>`.
<?php 
echo $this->render('_foreignTables', ['foreignKeys' => $foreign_keys]);
?>
 */
class <?php 
echo $class_name;
?> extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
<?php 
echo $this->render('_createTable', ['table' => $table, 'fields' => $fields, 'foreignKeys' => $foreign_keys]);
if (!empty($table_comment)) {
    echo $this->render('_addComments', ['table' => $table, 'tableComment' => $table_comment]);
}
?>
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
<?php 
echo $this->render('_dropTable', ['table' => $table, 'foreignKeys' => $foreign_keys]);
?>
    }
}
