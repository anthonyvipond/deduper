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

        $this->extractDuplicates(
            $input->getArgument('table'), 
            $input->getArgument('table') . '_removals',
            explode(':', $input->getArgument('columns'))
        );
    }

    protected function extractDuplicates($table, $removalsTable, array $columns)
    {
        $this->info('Counting duplicate rows...');
        $duplicateRows = $this->pdo->getDuplicateRowCount($table, $columns);

        if ($duplicateRows === 0) {
            $this->info('There are no duplicates in ' . $table . ' using: ' . commaSeperate($columns));
            return;
        }

        $this->feedback('`' . $table . '` has ' .  number_format($duplicateRows) . ' duplicates');

        if ( ! $this->pdo->indexExists($table, implode('_', $columns))) {
            $this->info('Creating composite index on ' . $table . ' to speed things up...');
            $this->pdo->createCompositeIndex($table, $columns);
            $this->feedback('Created composite index on ' . $table);
        }

        if ( ! $this->pdo->tableExists($removalsTable)) {
            $this->info('Creating removals table: ' . $removalsTable);
            $this->pdo->statement('CREATE TABLE ' . $removalsTable . ' LIKE ' . $table);
            $this->feedback('Created removals table');
        }

        if ( ! $this->pdo->columnExists('new_id', $removalsTable)) {
            $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $table . '...');
            $this->pdo->addIntegerColumn($removalsTable, 'new_id');
            $this->feedback('Added new_id field to ' . $removalsTable);
        }

        $this->info('Inserting duplicate rows to ' . $removalsTable . '...');
        $affectedRows = $this->insertDuplicatesToRemovalsTable($table, $removalsTable, $columns);
        $this->feedback('Inserted ' . number_format($affectedRows) . ' duplicates to ' . $removalsTable);
    }

    protected function insertDuplicatesToRemovalsTable($table, $removalsTable, array $columns)
    {
        $columns = tickCommaSeperate($this->pdo->getColumns($table));

        $subQuery = '(SELECT * FROM ' . $table . ' GROUP BY ' . tickCommaSeperate($columns) . ')';

        $sql = 'INSERT INTO ' . $removalsTable . '(' . $columns . ') WHERE id NOT IN ' . $subQuery;

        return $this->pdo->statement($sql);
    }

}