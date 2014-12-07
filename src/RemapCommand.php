<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class RemapCommand extends DedupeCommand {

    public function configure()
    {
        $this->setName('remap')
             ->setDescription('De-duplicate a table and remap another one')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addArgument('uniquesTable', InputArgument::REQUIRED, 'The table with unique values')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('foreignKey', null, InputOption::VALUE_REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('parentKey', null, InputOption::VALUE_OPTIONAL, 'The parent key on dupes table. Usually id', 'id');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $uniquesTable = $input->getArgument('uniquesTable');
        $remapTable = $input->getArgument('remapTable');
        $columns = explode(':', $input->getArgument('columns'));

        $foreignKey = $input->getOption('foreignKey');
        $parentKey = $input->getOption('parentKey');

        // $posFileHandle = $this->findOrCreatePositionFile($uniquesTable, $remapTable);

        $removalsTable = $uniquesTable . '_removals';

        $dupesTable = $uniquesTable . '_with_dupes';

        $this->info('Creating table ' . $removalsTable . ' like ' . $dupesTable . '...');
        $this->pdo->copyTable($dupesTable, $removalsTable);
        $this->feedback('Created table ' . $removalsTable . ' like ' . $dupesTable);

        $this->info('Populating removals table...');
        $this->insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, $columns);
        $this->feedback('Populated removals table');

        $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $uniquesTable . '...');
        $this->pdo->addIntegerColumn($removalsTable, 'new_id');
        $this->feedback('Added new_id field to ' . $removalsTable);

        $this->info('Adding composite index to ' . $removalsTable . ' (' . commaSeperate($columns) . ') to populate it quickly...');
        $this->pdo->createCompositeIndex($removalsTable, $columns);
        $this->feedback('Added composite index for ' . $removalsTable . ' on ' . commaSeperate($columns));

        $this->info('Adding new ids to removals table...');
        $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
        $this->feedback('Added new ids to removals table');

        $col = $columns[0];

        $this->info('Adding composite index to ' . $removalsTable . ' (' . $col . ', new_id) to populate it quickly...');
        $this->pdo->createCompositeIndex($removalsTable, [$col, 'new_id']);
        $this->feedback('Added composite index for ' . $removalsTable . ' on ' . $col . ',new_id');

        $this->info('Updating rest of removals table on ' . $uniquesTable . '.' . $col . ' = ' . $removalsTable . '.' . $col);
        $this->insertNewIdsWhereUnableToMatchOnAllColumns($uniquesTable, $removalsTable, $col);
        $this->feedback('Updated rest of removals table on ' . $uniquesTable . '.' . $col);

        $this->comment('Remapping the ' . $remapTable . ' from ' . $removalsTable);
        $this->remapForeignKeys($remapTable, $removalsTable);
        $this->feedback('Completed remapping for ' . $remapTable);
    }

    protected function remapForeignKeys($remapTable, $removalsTable)
    {
        // code here
    }

    protected function insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, array $columns)
    {
        // insert the ids as well
        array_unshift($columns, 'id');

        $columnString = tickCommaSeperate($columns);
        
        $selectSql = 'SELECT ' . $columnString . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN (SELECT id FROM ' . $uniquesTable . ')';
        
        $sql = 'INSERT INTO ' . $removalsTable . ' (' . $columnString . ') ' . $selectSql;

        $this->pdo->statement($sql);

        // remove id from columns
        array_shift($columns);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

        foreach ($columns as $column) {
            $sql .= 
                $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column) . ' AND ';
        }

        $sql = rtrim($sql, 'AND ');
        $sql .= ' SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';
        $this->pdo->statement($sql);
    }

    protected function insertNewIdsWhereUnableToMatchOnAllColumns($uniquesTable, $removalsTable, $column)
    {
        // works but too slow on large tables
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

        $sql .= $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column);

        $sql .= ' AND ' . $removalsTable . '.new_id IS NULL';

        $sql .= ' SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';

        $this->pdo->statement($sql);
    }

    // protected function findOrCreatePositionFile($dupesTable, $remapTable)
    // {
    //     $this->file = __DIR__ . '/../dedupe_' . $dupesTable . '_remap_' . $remapTable . '_pos.txt';

    //     if ( ! file_exists($this->file)) {
    //         return fopen($this->file, 'w+');
    //     }

    //     if ( ! is_writable($this->file)) throw new \Exception('Cannot write file to ' . $this->file);
    // }

}