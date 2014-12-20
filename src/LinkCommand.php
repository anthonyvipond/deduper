<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class LinkCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('link')
             ->setDescription('Link throwaways to new keeper id')
             ->addArgument('uniques', InputArgument::REQUIRED, 'The uniques table')
             ->addArgument('removes', InputArgument::REQUIRED, 'The removes table')
             ->addArgument('columns', InputArgument::REQUIRED, 'Columns to link on');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $uniquesTable  = $input->getArgument('uniques');
        $removalsTable = $input->getArgument('removes');
        $columns       = explode(':', $input->getArgument('columns'));

        $this->info('Adding new ids to removals table...');
        $affectedRows = $this->insertNewIdsToRemovesTable($uniquesTable, $removalsTable, $columns);
        $this->feedback('Linked ' . pretty($affectedRows) . ' records on new_id in ' . $removalsTable);
    }

    protected function insertNewIdsToRemovesTable($uniquesTable, $removalsTable, array $columns)
    {
        $totalRows = $this->pdo->getTotalRows($uniquesTable);

        $i = $this->db->table($uniquesTable)->min('id');

        while ($i) {
            $row = $this->db->table($uniquesTable)->select($columns)->where('id', $i)->first();

            var_dump($row);

            $affectedRows = $this->db->table($removalsTable)->where($row + ['new_id' => null])->update(['new_id' => $i]);

            $feedback = $affectedRows ? 'feedback' : 'info';

            $this->$feedback('Updating from ' . $uniquesTable . '.id = ' . $i . '. ' . $affectedRows . 'rows updated');

            $totalAffectedRows += $affectedRows;

            $i = $this->pdo->getNextId($i, $uniquesTable);
        }

        return $totalAffectedRows;
    }

}