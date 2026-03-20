<?php

/**
 * Creates a call for the method `yii\db\Migration::createTable()`.
 *
 * @var array $foreignKeys the foreign keys
 */
if (!empty($foreign_keys)) {
    ?>
 * Has foreign keys to the tables:
 *
<?php 
    foreach ($foreign_keys as $fk_data) {
        ?>
 * - `<?php 
        echo $fk_data['relatedTable'];
        ?>`
<?php 
    }
}