<?php

/**
 * @var array $foreignKeys
 * @var string $table
 */
foreach ($foreign_keys as $column => $fk_data) {
    ?>

        // creates index for column `<?php 
    echo $column;
    ?>`
        $this->createIndex(
            '<?php 
    echo $fk_data['idx'];
    ?>',
            '<?php 
    echo $table;
    ?>',
            '<?php 
    echo $column;
    ?>'
        );

        // add foreign key for table `<?php 
    echo $fk_data['relatedTable'];
    ?>`
        $this->addForeignKey(
            '<?php 
    echo $fk_data['fk'];
    ?>',
            '<?php 
    echo $table;
    ?>',
            '<?php 
    echo $column;
    ?>',
            '<?php 
    echo $fk_data['relatedTable'];
    ?>',
            '<?php 
    echo $fk_data['relatedColumn'];
    ?>',
            'CASCADE'
        );
<?php 
}