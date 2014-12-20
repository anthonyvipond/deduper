<?php

namespace DLR;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class LinkCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('link')
             ->setDescription('Store new id on removes table which is used for remapping')
             ->addArgument('uniques', InputArgument::REQUIRED, 'The uniques table')
             ->addArgument('removes', InputArgument::REQUIRED, 'The removes table')
             ->addArgument('columns', InputArgument::REQUIRED, 'Columns to link on')
             ->addOption('fillerMode', null, InputOption::VALUE_OPTIONAL, 'Use to populate unmapped values in removes table', false);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $uniquesTable  = $input->getArgument('uniques');
        $removalsTable = $input->getArgument('removes');
        $columns       = explode(':', $input->getArgument('columns'));
        $fillerMode    = (bool) $input->getOption('fillerMode');

        $this->info('Adding new ids to removals table...');
        $affectedRows = $this->insertNewIdsToRemovesTable($uniquesTable, $removalsTable, $columns, $fillerMode);
        $this->feedback('Linked ' . pretty($affectedRows) . ' records on new_id in ' . $removalsTable);
    }

    protected function insertNewIdsToRemovesTable($uniquesTable, $removalsTable, array $columns, $fillerMode)
    {
        $totalRows = $this->pdo->getTotalRows($uniquesTable);

        $totalAffectedRows = 0;

        $i = $this->db->table($uniquesTable)->min('id');

        while ($i) {
            $record = $this->db->table($uniquesTable)->select($columns)->where('id', $i)->first();

            $query = $this->db->table($removalsTable);

            if ( ! $fillerMode) {
                foreach ($record as $attribute => $value) {
                    if (is_null($value)) {
                        $query = $query->whereNull($attribute);
                    } else {
                        $query = $query->where($attribute, $value);
                    }
                }
            } else {
                $query->where($record)->whereNull('new_id');
            }

            $affectedRows = $query->update(['new_id' => $i]);

            $feedback = $affectedRows ? 'feedback' : 'info';

            $this->$feedback('Updating from ' . $uniquesTable . '.id = ' . $i . ' (' . $affectedRows . ' rows updated)');

            $totalAffectedRows += $affectedRows;

            $i = $this->pdo->getNextId($i, $uniquesTable);
        }

        return $totalAffectedRows;
    }

}