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

        $uniquesTable  = $input->getArgument('uniquesTable');
        $dupesTable    = $input->getArgument('dupesTable');
        $removalsTable = $uniquesTable . '_removals';
        $columns       = explode(':', $input->getArgument('columns'));
        $indexName     = implode('_', $columns);

        if ( ! $this->pdo->indexExists($removalsTable, $indexName)) {
            $this->info('Adding composite index to ' . $removalsTable . ' (' . commaSeperate($columns) . ') to populate quickly...');
            $this->pdo->createCompositeIndex($removalsTable, $columns + ['new_id']);
            $this->feedback('Added composite index for ' . $removalsTable . ' on ' . commaSeperate($columns));
        }

        $this->info('Adding new ids to removals table...');
        $affectedRows = $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
        $this->feedback('Linked ' . $affectedRows . ' records on new_id in ' . $removalsTable);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

        foreach ($columns as $column) {
            $sql .= $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column) . ' AND ';
        }

        $sql .= 'new_id IS NULL ';

        $sql .= 'SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';

        return $this->pdo->statement($sql);
    }

}