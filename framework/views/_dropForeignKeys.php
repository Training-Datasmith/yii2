<?php

/**
 * @var array $foreignKeys
 * @var string $table
 */
foreach ($foreign_keys as $column => $fk_data) {
    ?>
        // drops foreign key for table `<?php 
    echo $fk_data['relatedTable'];
    ?>`
        $this->dropForeignKey(
            '<?php 
    echo $fk_data['fk'];
    ?>',
            '<?php 
    echo $table;
    ?>'
        );

        // drops index for column `<?php 
    echo $column;
    ?>`
        $this->dropIndex(
            '<?php 
    echo $fk_data['idx'];
    ?>',
            '<?php 
    echo $table;
    ?>'
        );

<?php 
}