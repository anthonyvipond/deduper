<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class LinkCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('link')
             ->setDescription('Add the new_id value to removals table based on the columns')
             ->addArgument('uniquesTable', InputArgument::REQUIRED, 'The table with unique values')
             ->addArgument('dupesTable', InputArgument::REQUIRED, 'The original table with duplicate values')
             ->addArgument('columns', InputArgument::OPTIONAL, 'Colon seperated rows that we are linking new_id on');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $table         = $input->getArgument('uniquesTable');
        $dupesTable    = $input->getArgument('dupesTable');
        $removalsTable = $table . '_removals';
        $columns       = explode(':', $input->getArgument('columns'));

        $this->info('Populating removals table...');
        $this->insertDiffToNewTable($table, $dupesTable, $removalsTable, $columns);
        $this->feedback('Populated removals table');

        $this->info('Adding composite index to ' . $removalsTable . ' (' . commaSeperate($columns) . ') to populate quickly...');
        $this->pdo->createCompositeIndex($removalsTable, $columns + ['new_id']);
        $this->feedback('Added composite index for ' . $removalsTable . ' on ' . commaSeperate($columns));

        $this->info('Adding new ids to removals table...');
        $this->insertNewIdsToRemovalsTable($table, $removalsTable, $columns);
        $this->feedback('Added new ids to removals table');
    }

    protected function insertDiffToNewTable($table, $dupesTable, $removalsTable, array $columns)
    {
        // insert the ids as well
        array_unshift($columns, 'id');

        $columnString = tickCommaSeperate($columns);

        $subQuery = '(SELECT id FROM ' . $table . ')';
        
        $selectSql = 'SELECT ' . $columnString . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN ' . $subQuery;
        
        $sql = 'INSERT INTO ' . $removalsTable . ' (' . $columnString . ') ' . $selectSql;

        $this->pdo->statement($sql);

        // remove id from columns
        array_shift($columns);
    }

    protected function insertNewIdsToRemovalsTable($table, $removalsTable, array $columns)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $table . ' ON ';

        foreach ($columns as $column) {
            $sql .= $table . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column) . ' AND ';
        }

        $sql . 'new_id IS NULL ';

        // $sql = rtrim($sql, 'AND ');
        $sql .= 'SET ' . $removalsTable . '.new_id = ' . $table . '.id';
        $this->pdo->statement($sql);
    }

    // protected function insertNewIdsWhereUnableToMatchOnAllColumns($uniquesTable, $removalsTable, $column)
    // {
    //     $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

    //     $sql .= $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column);

    //     $sql .= ' AND ' . $removalsTable . '.new_id IS NULL';

    //     $sql .= ' SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';

    //     $this->pdo->statement($sql);
    // }

}