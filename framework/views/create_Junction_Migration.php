<?php

/**
 * This view is used by console/controllers/MigrateController.php.
 *
 * The following variables are available in this view:
 * @since 2.0.7
 * @deprecated since 2.0.8
 *
 * @var string $className the new migration class name without namespace
 * @var string $namespace the new migration class namespace
 * @var string $table the name table
 * @var string $field_first the name field first
 * @var string $field_second the name field second
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
?>` which is a junction between
 * table `<?php 
echo $field_first;
?>` and table `<?php 
echo $field_second;
?>`.
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
        $this->createTable('<?php 
echo $table;
?>', [
            '<?php 
echo $field_first;
?>_id' => $this->integer(),
            '<?php 
echo $field_second;
?>_id' => $this->integer(),
            'PRIMARY KEY(<?php 
echo $field_first;
?>_id, <?php 
echo $field_second;
?>_id)',
        ]);

        $this->createIndex(
            'idx-<?php 
echo $table . '-' . $field_first;
?>_id',
            '<?php 
echo $table;
?>',
            '<?php 
echo $field_first;
?>_id'
        );

        $this->createIndex(
            'idx-<?php 
echo $table . '-' . $field_second;
?>_id',
            '<?php 
echo $table;
?>',
            '<?php 
echo $field_second;
?>_id'
        );

        $this->addForeignKey(
            'fk-<?php 
echo $table . '-' . $field_first;
?>_id',
            '<?php 
echo $table;
?>',
            '<?php 
echo $field_first;
?>_id',
            '<?php 
echo $field_first;
?>',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-<?php 
echo $table . '-' . $field_second;
?>_id',
            '<?php 
echo $table;
?>',
            '<?php 
echo $field_second;
?>_id',
            '<?php 
echo $field_second;
?>',
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('<?php 
echo $table;
?>');
    }
}
