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
        $this->init($output);

        $uniquesTable  = $input->getArgument('uniques');
        $removalsTable = $input->getArgument('removes');
        $columns       = explode(':', $input->getArgument('columns'));

        $this->info('Adding new ids to removals table...');
        $affectedRows = $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
        $this->feedback('Linked ' . pretty($affectedRows) . ' records on new_id in ' . $removalsTable);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $totalAffectedRows = 0;

        $percentFinished = 0.00;

        $totalRows = $this->pdo->getTotalRows($uniquesTable);

        $rowsLooped = 0;

        $i = $this->pdo->getNextId(0, $uniquesTable);

        while ($i) {
            $row = $this->db->table($uniquesTable)->select($columns)->where('id', $i)->first();

            $affectedRows = $this->db->table($removalsTable)->where($row + ['new_id' => null])->update(['new_id' => $i]);

            $totalAffectedRows += $affectedRows;

            if (++$rowsLooped / $totalRows > $percentFinished) {
                $this->feedback('Updated ' . $percentFinished * 100 . '% (' . pretty($totalAffectedRows) . ' rows updated)');
                $percentFinished += 0.02;
            } elseif ($rowsLooped / $totalRows == 1) {
                $this->feedback('Updated 100% (' . pretty($totalAffectedRows) . ' rows updated)');
            }

            $i = $this->pdo->getNextId($i, $uniquesTable);
        }

        return $totalAffectedRows;
    }

}