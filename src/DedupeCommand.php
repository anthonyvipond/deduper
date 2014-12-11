<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DedupeCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('dedupe')
             ->setDescription('De-duplicate a table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addArgument('acceptableDiff', InputArgument::OPTIONAL, 'Acceptable difference on int columns to mark as the same');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $this->initiateDedupe(
            $input->getArgument('table'), 
            explode(':', $input->getArgument('columns')),
            explode(':', $input->getArgument('acceptableDiff'))
        );
    }

    protected function initiateDedupe($table, array $columns, $acceptableDiff = [])
    {
        $duplicateRows = $this->outputDuplicateData($table, $columns);

        if ($duplicateRows === 0) {
            $this->info('There are no duplicate rows in ' . $table . ' using cols: ' . commaSeperate($columns));
            return;
        }

        $this->dedupe($table, $columns);
        
        $this->info('Dedupe completed. Recounting total rows in ' . $table);
        $totalRows = $this->pdo->getTotalRows($table);
        $this->feedback($table . ' now has ' . number_format($totalRows) . ' total rows');

        $this->info('Counting duplicate rows for ' . $table . ' on: ' . commaSeperate($columns));
        $duplicateRows = $this->pdo->getDuplicateRows($table, $columns);
        $this->feedback($table . ' now has ' . $duplicateRows . ' duplicate rows on: ' . commaSeperate($columns));
    }

    protected function dedupe($table, array $columns)
    {
        $commaColumns = commaSeperate($columns);
        $tickColumns  = tickCommaSeperate($columns);
        $indexName    = implode('_', $columns);

        if ( ! $this->pdo->indexExists($table, $indexName)) {
            $this->info('Creating composite index on ' . $table . ' to speed things up...');
            $this->pdo->createCompositeIndex($table, $columns);
            $this->feedback('Created composite index on ' . $table);
        }

        $originalTable = $table . '_original_' . $time = time();
        $dedupedTable  = $table . '_deduped_' . $time;
        $removalsTable = $table . '_removals';

        $this->info('Deduping ' . $table . '. Backup table is named: ' . $originalTable . '. Please hold...');
        $this->pdo->statement('CREATE TABLE ' . $dedupedTable . ' LIKE ' . $table);
        $this->pdo->statement('INSERT ' . $dedupedTable . ' SELECT * FROM ' . $table . ' GROUP BY ' . $tickColumns);
        $this->pdo->statement('RENAME TABLE ' . $table . ' TO ' . $originalTable);
        $this->pdo->statement('RENAME TABLE ' .  $dedupedTable . ' TO ' . $table);
        $this->feedback('Deduped ' . $table);

        if ( ! $this->pdo->tableExists($removalsTable)) {
            $this->info('Creating removals table: ' . $removalsTable);
            $this->pdo->statement('CREATE TABLE ' . $removalsTable . ' LIKE ' . $table);
        }

        $this->comment('Inserting removed rows to removal table');
        $affectedRows = $this->insertRemovedRowsToRemovalsTable($table, $originalTable, $removalsTable);
        $this->feedback('Inserted ' . number_format($affectedRows) . ' rows to ' . $removalsTable);

        if ( ! $this->pdo->columnExists('new_id', $removalsTable)) {
            $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $table . '...');
            $this->pdo->addIntegerColumn($removalsTable, 'new_id');
            $this->feedback('Added new_id field to ' . $removalsTable);
        }
    }

    protected function insertRemovedRowsToRemovalsTable($table, $originalTable, $removalsTable)
    {
        $originalColumns = tickCommaSeperate($this->pdo->getColumns($table));

        $subQuery = '(SELECT id FROM ' . $table . ')';
        $selectClause = 'SELECT ' . $originalColumns . ' FROM ' . $originalTable . ' WHERE id NOT IN ' . $subQuery;

        $sql = 'INSERT INTO ' . $removalsTable . '(' . $originalColumns . ') ' . $selectClause;

        return $this->pdo->statement($sql);
    }

}