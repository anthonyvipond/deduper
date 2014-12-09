<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class RemapCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('remap')
             ->setDescription('De-duplicate a table and remap another one')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addArgument('uniquesTable', InputArgument::REQUIRED, 'The table with unique values')
             ->addArgument('columns', InputArgument::OPTIONAL, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('foreignKey', null, InputOption::VALUE_REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('parentKey', null, InputOption::VALUE_OPTIONAL, 'The parent key on dupes table. Usually id', 'id')
             ->addOption('stage', null, InputOption::VALUE_OPTIONAL, 'Optionally pass in "remap" stage to jump to remapping')
             ->addOption('startId', null, InputOption::VALUE_OPTIONAL, 'What id to start mapping from on the removals table', 1);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $uniquesTable = $input->getArgument('uniquesTable');
        $remapTable   = $input->getArgument('remapTable');
        $columns      = explode(':', $input->getArgument('columns'));

        $foreignKey   = $input->getOption('foreignKey');
        $parentKey    = $input->getOption('parentKey');
        $stage        = $input->getOption('stage');
        $startId      = (int) $input->getOption('startId');

        $removalsTable = $uniquesTable . '_removals';

        $dupesTable = $uniquesTable . '_with_dupes';

        if ($stage !== 'remap') {
            $this->info('Creating table ' . $removalsTable . ' like ' . $dupesTable . '...');
            $this->pdo->copyTable($dupesTable, $removalsTable);
            $this->feedback('Created table ' . $removalsTable . ' like ' . $dupesTable);

            $this->info('Populating removals table...');
            $this->insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, $columns);
            $this->feedback('Populated removals table');

            $this->info('Adding new_id field to ' . $removalsTable . ' to store the id from ' . $uniquesTable . '...');
            $this->pdo->addIntegerColumn($removalsTable, 'new_id');
            $this->feedback('Added new_id field to ' . $removalsTable);

            $this->info('Adding composite index to ' . $removalsTable . ' (' . commaSeperate($columns) . ') to populate quickly...');
            $this->pdo->createCompositeIndex($removalsTable, $columns);
            $this->feedback('Added composite index for ' . $removalsTable . ' on ' . commaSeperate($columns));

            $this->info('Adding new ids to removals table...');
            $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
            $this->feedback('Added new ids to removals table');

            $col = $columns[0];

            $this->info('Adding composite index to ' . $removalsTable . ' (' . $col . ', new_id) to populate quickly...');
            $this->pdo->createCompositeIndex($removalsTable, [$col, 'new_id']);
            $this->feedback('Added composite index for ' . $removalsTable . ' on ' . $col . ',new_id');

            $this->info('Updating rest of removals table on ' . $uniquesTable . '.' . $col . ' = ' . $removalsTable . '.' . $col);
            $this->insertNewIdsWhereUnableToMatchOnAllColumns($uniquesTable, $removalsTable, $col);
            $this->feedback('Updated rest of removals table on ' . $uniquesTable . '.' . $col);
        }

        $this->comment('Creating index on ' . $remapTable . ' for ' . $foreignKey . ' to populate quickly...');
        $this->pdo->createIndex($remapTable, $foreignKey);

        $this->comment('Getting lookup method...');
        $remapMethod = $this->getRemapMethod($removalsTable, $remapTable);
        $this->feedback('Will use ' . $remapMethod . ' lookup method');

        $this->comment('Remapping the ' . $remapTable . 'table from ' . $removalsTable . ' on ' . $foreignKey);
        $this->$remapMethod($remapTable, $removalsTable, $foreignKey, $startId);
        $this->feedback('Completed remapping for ' . $remapTable);
    }

    protected function getRemapMethod($removalsTable, $remapTable)
    {
        $removalsTableSize = $this->db->table($removalsTable)->count();
        $remapTableSize = $this->db->table($remapTable)->count();

        return ($removalsTableSize / $remapTableSize) > 5 ? 'reverseRemapForeignKeys' : 'remapForeignKeys';
    }

    protected function reverseRemapForeignKeys($remapTable, $removalsTable, $foreignKey, $startId)
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

    protected function remapForeignKeys($remapTable, $removalsTable, $foreignKey, $startId)
    {   
        $i = is_null($this->db->table($removalsTable)->find($startId)) ? 1 : $startId;

        while (is_int($i)) {
            $removalRow = keysToLower($this->db->table($removalsTable)->find($i));

            $newId = $removalRow['new_id'];

            $affectedRows = $this->db->table($remapTable)
                                     ->where($foreignKey, $i)
                                     ->update([$foreignKey => $newId]);

            $this->feedback('Updated ' . $remapTable . '.' . $foreignKey . ' from ' . $i . ' to ' . $newId);

            $affectedRows ? $this->info($affectedRows . ' affected rows') : $this->comment($affectedRows . ' affected rows');
            
            $i = $this->pdo->getNextId($i, $removalsTable);
        }
    }

    protected function insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, array $columns)
    {
        // insert the ids as well
        array_unshift($columns, 'id');

        $columnString = tickCommaSeperate($columns);
        
        $selectSql = 'SELECT ' . $columnString . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN (SELECT id FROM ' . $uniquesTable . ')';
        
        $sql = 'INSERT INTO ' . $removalsTable . ' (' . $columnString . ') ' . $selectSql;

        $this->pdo->statement($sql);

        // remove id from columns
        array_shift($columns);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

        foreach ($columns as $column) {
            $sql .= 
                $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column) . ' AND ';
        }

        $sql = rtrim($sql, 'AND ');
        $sql .= ' SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';
        $this->pdo->statement($sql);
    }

    protected function insertNewIdsWhereUnableToMatchOnAllColumns($uniquesTable, $removalsTable, $column)
    {
        $sql = 'UPDATE ' . $removalsTable . ' JOIN ' . $uniquesTable . ' ON ';

        $sql .= $uniquesTable . '.' . ticks($column) . ' = ' . $removalsTable . '.' . ticks($column);

        $sql .= ' AND ' . $removalsTable . '.new_id IS NULL';

        $sql .= ' SET ' . $removalsTable . '.new_id = ' . $uniquesTable . '.id';

        $this->pdo->statement($sql);
    }

}