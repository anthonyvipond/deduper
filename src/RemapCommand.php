<?php

namespace DLR;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RemapCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('remap')
             ->setDescription('Remap a table based off the removals table')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addArgument('removalsTable', InputArgument::REQUIRED, 'The removals table containing the new ids')
             ->addArgument('foreignKey', InputArgument::REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('startId', null, InputOption::VALUE_OPTIONAL, 'What id to start mapping from on the removals table', 1);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $remapTable    = $input->getArgument('remapTable');
        $removalsTable = $input->getArgument('removalsTable');
        $foreignKey    = $input->getArgument('foreignKey');
        $startId       = (int) $input->getOption('startId');

        $this->info('Getting lookup method...');
        $remapMethod = $this->getRemapMethod($removalsTable, $remapTable);
        $this->feedback('Will use ' . $remapMethod . ' remap method');

        $remapMethod .= 'Remap';
        $affectedRows = $this->$remapMethod($remapTable, $removalsTable, $foreignKey, $startId);

        $this->feedback('Remapped ' . $affectedRows . ' rows on ' . $remapTable);
    }

    protected function getRemapMethod($removalsTable, $remapTable)
    {
        $removalsTableSize = $this->db->table($removalsTable)->count();
        $remapTableSize = $this->db->table($remapTable)->count();

        return $removalsTableSize / $remapTableSize > 5 ? 'reverse' : 'standard';
    }

    protected function reverseRemap($remapTable, $removalsTable, $foreignKey, $startId)
    {
        $totalAffectedRows = 0;

        $totalRows = $this->pdo->getTotalRows($removalsTable);

        $i = is_null($this->db->table($remapTable)->find($startId)) ? $this->db->table($remapTable)->min('id') : $startId;

        while (is_int($i)) {
            $remapRow = keysToLower($this->db->table($remapTable)->find($i));

            $remapRowFk = $remapRow[$foreignKey];

            if ( ! is_null($remapRowFk)) {
                $newId = $this->db->table($removalsTable)->find($remapRowFk)['new_id'];

                if ( ! is_null($newId)) {
                    $affectedRows = $this->db->table($remapTable)->where('id', $i)->update([$foreignKey => $newId]);

                    $totalAffectedRows += $affectedRows;

                    $this->feedback('Remapped ' . pretty($totalAffectedRows) . ' rows');
                } else {
                    $this->feedback($removalsTable . '.' . $foreignKey . ' was null. continuing...');
                }
            } else {
                $this->feedback($remapTable . '.' . $foreignKey . ' was null. continuing...');
            }

            $i = $this->pdo->getNextId($i, $remapTable);
        }

        return $totalAffectedRows;
    }

    protected function standardRemap($remapTable, $removalsTable, $foreignKey, $startId)
    {   
        $totalAffectedRows = 0;

        $totalRows = $this->pdo->getTotalRows($removalsTable);

        if (is_null($this->db->table($removalsTable)->find($startId))) {
            $i = $this->pdo->getNextId(1, $removalsTable);
        } else {
            $i = $startId;
        }

        while ($i) {
            $removalRow = keysToLower($this->db->table($removalsTable)->find($i));

            $newId = $removalRow['new_id'];

            $affectedRows = $this->db->table($remapTable)
                                     ->where($foreignKey, $i)
                                     ->update([$foreignKey => $newId]);

            $feedback = $affectedRows ? 'feedback' : 'info';

            $this->$feedback('Updated from ' . $removalsTable . '.id = ' . $i . ' (' . $affectedRows . ' rows updated)');

            $totalAffectedRows += $affectedRows;

            $i = $this->pdo->getNextId($i, $removalsTable);
        }

        return $totalAffectedRows;
    }

}