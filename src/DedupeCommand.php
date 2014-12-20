<?php

namespace DLR;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class DedupeCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('dedupe')
             ->setDescription('Dedupe a table and break into keepers and throwaways.')
             ->addArgument('originalTable', InputArgument::REQUIRED, 'The original table with duplicates')
             ->addArgument('columns', InputArgument::REQUIRED, 'Columns that define row uniqueness');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $originalTable = $input->getArgument('originalTable');
        $uniquesTable  = $originalTable . '_uniques';
        $removalsTable = $originalTable . '_removes';
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
            $undedupedTable = $firstRun ? $originalTable : $uniquesTable;
            $this->feedback('There are ' . pretty($dupes) . ' dupes in ' . $undedupedTable . ' on ' . commaSeperate($columns));
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
        } else {
            $this->info('Creating temp_uniques table...');
            $this->pdo->statement('CREATE TEMPORARY TABLE ' . ($tempTable = 'temp_uniques') . ' LIKE ' . $uniquesTable);
            $this->feedback('Created temporary table: ' . $tempTable);
        }

        if ( ! $this->pdo->indexExists($uniquesTable, implode('_', $columns))) {
            $this->info('Creating comp index on ' . $uniquesTable . ' on ' . commaSeperate($columns) . ' to speed process...');
            $this->pdo->createCompositeIndex($uniquesTable, $columns);
            $this->feedback('Created composite index on ' . $uniquesTable);
        }

        $dedupedTable = $firstRun ? $uniquesTable : $tempTable;

        $this->info('Inserting current unique rows on ' . $undedupedTable . ' to ' . $dedupedTable . '...');
        $affectedRows = $this->insertUniquesToTable($undedupedTable, $dedupedTable, $columns);
        $this->feedback('Inserted ' . pretty($affectedRows) . ' unique rows from ' . $undedupedTable . ' to ' . $dedupedTable);

        $this->info('Inserting duplicate rows to ' . $removalsTable . '...');
        $affectedRows = $this->insertDuplicatesToRemovalsTable($undedupedTable, $dedupedTable, $removalsTable);
        $this->feedback('Inserted ' . pretty($affectedRows) . ' duplicates to ' . $removalsTable);

        if ( ! $this->pdo->indexExists($removalsTable, implode('_', $columns))) {
            $this->info('Adding comp index to ' . $removalsTable . ' on ' . commaSeperate($columns) . ' to speed process...');
            $this->pdo->createCompositeIndex($removalsTable, $columns);
            $this->feedback('Added composite index for ' . $removalsTable);
        }

        if ( ! $firstRun) {
            $this->info('Deleting duplicate rows in ' . $uniquesTable . '...');
            $affectedRows = $this->deleteDuplicatesFromUniquesTable($uniquesTable, $removalsTable);
            $this->feedback('Deleted ' . pretty($affectedRows) . ' rows ' . $removalsTable);
        }
    }

    protected function insertUniquesToTable($undedupedTable, $dedupedTable, array $columns)
    {
        $sql  = 'INSERT ' . $dedupedTable . ' ';

        $sql .= 'SELECT * FROM ' . $undedupedTable . ' GROUP BY ' . tickCommaSeperate($columns);

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