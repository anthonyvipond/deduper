<?php

namespace DRT;

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
        $this->init($output);

        $remapTable    = $input->getArgument('remapTable');
        $removalsTable = $input->getArgument('removalsTable');
        $foreignKey    = $input->getArgument('foreignKey');
        $startId       = (int) $input->getOption('startId');

        $this->comment('Getting lookup method...');
        $remapMethod = $this->getRemapMethod($removalsTable, $remapTable);
        $this->feedback('Will use ' . $remapMethod . ' lookup method');

        $this->info('Remapping the ' . $remapTable . ' table from ' . $removalsTable . ' on ' . $foreignKey);
        $this->$remapMethod($remapTable, $removalsTable, $foreignKey, $startId);
        $this->feedback('Completed remapping for ' . $remapTable);
    }

    protected function getRemapMethod($removalsTable, $remapTable)
    {
        $removalsTableSize = $this->db->table($removalsTable)->count();
        $remapTableSize = $this->db->table($remapTable)->count();

        return $removalsTableSize / $remapTableSize > 5 ? 'reverseRemap' : 'standardRemap';
    }

    protected function reverseRemap($remapTable, $removalsTable, $foreignKey, $startId)
    {
        $i = is_null($this->db->table($remapTable)->find($startId)) ? $this->db->table($remapTable)->min('id') : $startId;

        while (is_int($i)) {
            $remapRow = keysToLower($this->db->table($remapTable)->find($i));

            $remapRowFk = $remapRow[$foreignKey];

            if ( ! is_null($remapRowFk)) {
                $newId = $this->db->table($removalsTable)->find($remapRowFk)['new_id'];

                if ( ! is_null($newId)) {
                    $affectedRows = $this->db->table($remapTable)->where('id', $i)->update([$foreignKey => $newId]);

                    $this->feedback('Updated ' . $remapTable . '.' . $foreignKey . ' from ' . $i . ' to ' . $newId);

                    $affectedRows ? $this->info($affectedRows . ' affected rows') : $this->comment($affectedRows . ' affected rows');
                } else {
                    $this->feedback($removalsTable . '.' . $foreignKey . ' was null. continuing...');
                }
            } else {
                $this->feedback($remapTable . '.' . $foreignKey . ' was null. continuing...');
            }

            $i = $this->pdo->getNextId($i, $remapTable);
        }
    }

    protected function standardRemap($remapTable, $removalsTable, $foreignKey, $startId)
    {   
        if ( ! $this->pdo->indexExists($remapTable, $foreignKey . '_drt')) {
            $this->comment('Creating index ' . $foreignKey . '_drt on ' . $remapTable . ' to speed remap process...');
            $this->pdo->createIndex($remapTable, $foreignKey, $foreignKey . '_drt');
            $this->info('Created index ' . $foreignKey . '_drt on ' . $remapTable);
        }

        if ( ! $this->pdo->indexExists($removalsTable, 'new_id_drt')) {
            $this->comment('Creating index new_id_drt on ' . $removalsTable . ' to speed lookup process...');
            $this->pdo->createIndex($removalsTable, 'new_id', 'new_id_drt');
            $this->info('Created index new_id_drt on ' . $removalsTable);
        }

        $i = is_null($this->db->table($removalsTable)->find($startId)) ? 1 : $startId;

        while (is_int($i)) {
            $removalRow = keysToLower($this->db->table($removalsTable)->find($i));

            $newId = $removalRow['new_id'];

            $affectedRows = $this->db->table($remapTable)
                                     ->where($foreignKey, $i)
                                     ->update([$foreignKey => $newId]);

            $this->feedback('Updated ' . $remapTable . '.' . $foreignKey . ' from ' . $i . ' to ' . $newId);

            $affectedRows ? $this->info($affectedRows . ' affected rows') : $this->comment($affectedRows . ' affected rows');
            
            $i = $this->pdo->getNextId($i, $removalsTable, 'AND new_id IS NOT NULL');
        }
    }

}