<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class ExtractCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('extract')
             ->setDescription('Extract duplicates from a table and move to removals table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table that needs duplicates extracted')
             ->addArgument('columns', InputArgument::REQUIRED, 'Columns that define row uniqueness');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        if ( ! $this->pdo->tableExists($input->getArgument('table') . '_uniques'))
        {
            $originalTable = $input->getArgument('table');
            $table         = $input->getArgument('table') . '_uniques';
            $removalsTable = $input->getArgument('table') . '_removals';
            $columns       = explode(':', $input->getArgument('columns'));

            $this->info('Counting duplicate rows...');
            $duplicateRows = $this->pdo->getDuplicateRowCount($originalTable, $columns);

            if ($duplicateRows === 0) {
                $this->info('There are no duplicates in ' . $originalTable . ' using: ' . commaSeperate($columns));
                return;
            } else {
                $this->feedback('There are ' . $duplicateRows . ' duplicate rows in ' . $originalTable);
            }

            $this->info('Creating uniques table: ' . $table);
            $this->pdo->statement('CREATE TABLE ' . $table . ' LIKE ' . $originalTable);
            $this->feedback('Created uniques table');

            // $this->info('Creating composite index on ' . $originalTable . ' to speed things up...');
            // $this->pdo->createCompositeIndex($originalTable, $columns);
            // $this->feedback('Created composite index on ' . $originalTable);

            $this->info('Inserted uniques to table: ' . $table);
            $this->insertUniquesToTable($originalTable, $table, $columns);
            $this->feedback('Inserted uniques to: ' . $table);

            $this->info('Creating removals table: ' . $removalsTable);
            $this->pdo->statement('CREATE TABLE ' . $removalsTable . ' LIKE ' . $originalTable);
            $this->feedback('Created removals table');

            $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $table . '...');
            $this->pdo->addIntegerColumn($removalsTable, 'new_id');
            $this->feedback('Added new_id field to ' . $removalsTable);

            $this->info('Inserting duplicate rows to ' . $removalsTable . '...');
            $affectedRows = $this->insertDuplicatesToRemovalsTable($table, $removalsTable, $columns, $originalTable);
            $this->feedback('Inserted ' . number_format($affectedRows) . ' duplicates to ' . $removalsTable);

            if ( ! $this->pdo->indexExists($removalsTable, implode('_', $columns))) {
                $this->info('Adding comp index to ' . $removalsTable . ' (' . commaSeperate($columns) . ') to populate quickly');
                $this->pdo->createCompositeIndex($removalsTable, $columns + ['new_id']);
                $this->feedback('Added comp index for ' . $removalsTable . ' on ' . commaSeperate($columns));
            }

            $this->info('Adding new ids to removals table...');
            $affectedRows = $this->insertNewIdsToRemovalsTable($originalTable, $removalsTable, $columns);
            $this->feedback('Linked ' . $affectedRows . ' records on new_id in ' . $removalsTable);
        } else {
            // $this->info('Counting duplicate rows...');
            // $duplicateRows = $this->pdo->getDuplicateRowCount($table, $columns);

            // if ($duplicateRows === 0) {
            //     $this->info('There are no duplicates in ' . $table . ' using: ' . commaSeperate($columns));
            //     return;
            // }

            // $this->feedback('`' . $table . '` has ' .  number_format($duplicateRows) . ' duplicates');

            // $this->info('Inserting duplicate rows to ' . $removalsTable . '...');
            // $affectedRows = $this->insertDuplicatesToRemovalsTable($table, $removalsTable, $columns);
            // $this->feedback('Inserted ' . number_format($affectedRows) . ' duplicates to ' . $removalsTable);

            // $this->info('Delete duplicate rows in ' . $uniquesTable . '...');
            // $affectedRows = $this->deleteDuplicatesFromUniquesTable($table, $removalsTable);
            // $this->feedback('Deleted ' . number_format($affectedRows) . ' rows ' . $removalsTable);
        }
    }

    protected function insertNewIdsToRemovalsTable($originalTable, $removalsTable, array $columns)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $originalTable . ' ON ';

        foreach ($columns as $column) {
            $sql .= $originalTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column) . ' AND ';
        }

        $sql .= 'new_id IS NULL ';

        $sql .= 'SET ' . $removalsTable . '.new_id = ' . $originalTable . '.id';

        return $this->pdo->statement($sql);
    }

    protected function insertUniquesToTable($originalTable, $uniquesTable, array $columns)
    {
        $sql  = 'INSERT ' . $uniquesTable . ' ';

        $sql .= 'SELECT * FROM ';

        $sql .= '(SELECT * FROM ' . $originalTable . ' GROUP BY ' . tickCommaSeperate($columns) . ') x';

        return $this->pdo->statement($sql);
    }

    protected function insertDuplicatesToRemovalsTable($table, $removalsTable, array $columns, $originalTable = null)
    {
        $sql  = 'INSERT INTO ' . $removalsTable . '(' . tickCommaSeperate($this->pdo->getColumns($table)) . ') ';

        $sql .= 'SELECT * FROM ' . ($originalTable ?: $table) . ' ';

        $sql .= 'WHERE id NOT IN ';

        $sql .= '(SELECT id FROM ' . $table . ' GROUP BY ' . tickCommaSeperate($columns) . ')';

        return $this->pdo->statement($sql);
    }

    protected function deleteDuplicatesFromUniquesTable($table, $removalsTable)
    {
        $sql .= 'DELETE FROM ' . $uniquesTable . ' WHERE id IN ';

        $sql .= '(SELECT id FROM ' . $removalsTable . ')';

        return $this->pdo->statement($sql);
    }

}