<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class DedupeCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('dedupe')
             ->setDescription('Dedupe a table and break into keepers and throwaways. Link throwaways to new keeper id')
             ->addArgument('originalTable', InputArgument::REQUIRED, 'The original table with duplicates')
             ->addArgument('columns', InputArgument::REQUIRED, 'Columns that define row uniqueness');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $originalTable = $input->getArgument('originalTable');
        $uniquesTable  = $originalTable . '_uniques';
        $removalsTable = $originalTable . '_removals';
        $columns       = explode(':', $input->getArgument('columns'));
        $firstRun      = ! $this->pdo->tableExists($uniquesTable);

        $this->info('Counting duplicate rows...');

        if ($firstRun) {
            $dupes = $this->pdo->getDuplicateRowCount($originalTable, $columns);
        } else {
            $dupes = $this->pdo->getDuplicateRowCount($uniquesTable, $columns);
        }
        if ($dupes === 0) {
            $this->feedback('There are no duplicates using columns: ' . commaSeperate($columns)); die;
        } else {
            $table = $firstRun ? $originalTable : $uniquesTable;
            $this->feedback('There are ' . pretty($dupes) . ' dupes in ' . $table . ' on ' . commaSeperate($columns));
            unset($table);
        }

        if ($firstRun) {
            $this->info('Creating uniques table: ' . $uniquesTable);
            $this->pdo->statement('CREATE TABLE ' . $uniquesTable . ' LIKE ' . $originalTable);
            $this->feedback('Created uniques table');

            if ( ! $this->pdo->indexExists($originalTable, implode('_', $columns))) {
                $this->info('Creating comp index on ' . $originalTable . ' on ' . commaSeperate($columns) . ' to speed process...');
                $this->pdo->createCompositeIndex($originalTable, $columns);
                $this->feedback('Created composite index on ' . $originalTable);
            }

            $this->info('Creating removals table: ' . $removalsTable);
            $this->pdo->statement('CREATE TABLE ' . $removalsTable . ' LIKE ' . $originalTable);
            $this->feedback('Created removals table');

            $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $uniquesTable . '...');
            $this->pdo->addIntegerColumn($removalsTable, 'new_id');
            $this->feedback('Added new_id field to ' . $removalsTable);

            $this->info('Inserting uniques to ' . $uniquesTable . '...');
            $affectedRows = $this->insertUniquesFromOriginalTableToUniquesTable($originalTable, $uniquesTable, $columns);
            $this->feedback('Inserted ' . pretty($affectedRows) . ' unique rows from ' . $originalTable . ' to ' . $uniquesTable);
        } else {
            $this->info('Inserted current uniques to temp table...');
            $this->pdo->statement('CREATE TEMPORARY TABLE ' . ($tempTable = 'temp_uniques') . ' LIKE ' . $uniquesTable);
            $this->feedback('Created temporary table: ' . $tempTable);

            $this->info('Inserting current unique rows on ' . $uniquesTable . ' to ' . $tempTable . '...');
            $affectedRows = $this->insertCurrentUniqueRowsToTempTable($uniquesTable, $tempTable, $columns);
            $this->feedback('Inserted ' . $affectedRows . ' unique rows from ' . $uniquesTable . ' to ' . $tempTable);
        }

        array_unshift($columns, 'id');
        if ( ! $this->pdo->indexExists($uniquesTable, implode('_', $columns))) {
            $this->info('Creating comp index on ' . $uniquesTable . ' on ' . commaSeperate($columns) . ' to speed process...');
            $this->pdo->createCompositeIndex($uniquesTable, $columns);
            $this->feedback('Created composite index on ' . $uniquesTable);
        }
        array_shift($columns);

        $this->info('Inserting duplicate rows to ' . $removalsTable . '...');
        $undedupedTable = $firstRun ? $originalTable : $uniquesTable;
        $dedupedTable   = $firstRun ? $uniquesTable  : $tempTable;
        $affectedRows   = $this->insertDuplicatesToRemovalsTable($undedupedTable, $dedupedTable, $removalsTable);
        $this->feedback('Inserted ' . pretty($affectedRows) . ' duplicates to ' . $removalsTable);

        array_unshift($columns, 'new_id');
        $this->info('Adding comp index to ' . $removalsTable . ' on ' . commaSeperate($columns) . ' to speed process...');
        $this->pdo->createCompositeIndex($removalsTable, $columns);
        $this->feedback('Added composite index for ' . $removalsTable);
        array_shift($columns);

        if ( ! $firstRun) {
            $this->info('Delete duplicate rows in ' . $uniquesTable . '...');
            $affectedRows = $this->deleteDuplicatesFromUniquesTable($uniquesTable, $removalsTable);
            $this->feedback('Deleted ' . pretty($affectedRows) . ' rows ' . $removalsTable);
        }

        $this->info('Adding new ids to removals table. This may take a while but will succeed :)');
        $affectedRows = $this->insertNewIdsToRemovalsTable($originalTable, $removalsTable, $columns);
        $this->feedback('Linked ' . pretty($affectedRows) . ' records on new_id in ' . $removalsTable);
    }

    protected function insertCurrentUniqueRowsToTempTable($uniquesTable, $tempTable, array $columns)
    {
        $sql  = 'INSERT ' . $tempTable . ' ';

        $sql .= 'SELECT * FROM ' . $uniquesTable . ' GROUP BY ' . tickCommaSeperate($columns);

        return $this->pdo->statement($sql);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $sql  = 'UPDATE ' . $removalsTable . ' LEFT JOIN ' . $uniquesTable . ' ON ';

        foreach ($columns as $column) {
            $sql .= $removalsTable . '.' . ticks($column) . ' = ' . $uniquesTable . '.' . ticks($column) . ' AND ';
        }

        $sql  = rtrim($sql, ' AND ') . ' ';

        $sql .= 'SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id WHERE new_id IS NULL';

        return $this->pdo->statement($sql);
    }

    // protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    // {
    //     $sql  = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON new_id IS NULL AND ';

    //     foreach ($columns as $column) {
    //         $sql .= $removalsTable . '.' . ticks($column) . ' = ' . $uniquesTable . '.' . ticks($column) . ' AND ';
    //     }

    //     $sql  = rtrim($sql, ' AND ') . ' ';

    //     $sql .= 'SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';

    //     return $this->pdo->statement($sql);
    // }

    protected function insertUniquesFromOriginalTableToUniquesTable($originalTable, $uniquesTable, array $columns)
    {
        $sql  = 'INSERT ' . $uniquesTable . ' ';

        $sql .= 'SELECT * FROM ' . $originalTable . ' GROUP BY ' . tickCommaSeperate($columns);

        return $this->pdo->statement($sql);
    }

    protected function insertDuplicatesToRemovalsTable($undedupedTable, $dedupedTable, $removalsTable)
    {
        $sql  = 'INSERT INTO ' . $removalsTable . '(' . tickCommaSeperate($this->pdo->getColumns($undedupedTable)) . ') ';

        $sql .= 'SELECT * FROM ' . $undedupedTable . ' ';

        $sql .= 'WHERE id NOT IN ';

        $sql .= '(SELECT id FROM ' . $dedupedTable . ')';

        return $this->pdo->statement($sql);
    }

    protected function deleteDuplicatesFromUniquesTable($uniquesTable, $removalsTable)
    {
        $sql  = 'DELETE FROM ' . $uniquesTable . ' WHERE id IN ';

        $sql .= '(SELECT id FROM ' . $removalsTable . ')';

        return $this->pdo->statement($sql);
    }

}