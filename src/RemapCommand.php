<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class RemapCommand extends DedupeCommand {

    public function configure()
    {
        $this->setName('remap')
             ->setDescription('De-duplicate a table and remap another one')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addArgument('dupesTable', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('dupesParentKey', null, InputOption::VALUE_REQUIRED, 'The parent key on dupes table. Usually id')
             ->addOption('remapFk', null, InputOption::VALUE_REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('startId', null, InputOption::VALUE_OPTIONAL, 'Where to start remapping from on the junk table', 1)
             ->addOption('backups', null, InputOption::VALUE_OPTIONAL, 'Whether a backup of the tables are needed or not', true)
             ->addOption('stage', null, InputOption::VALUE_REQUIRED, 'Whether to run in test mode or not', 'dedupe');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $needBackup = $input->getOption('backups') === 'false' ? false : true;
        $stage = $input->getOption('stage');

        $this->init($output);

        $dupesTable = $input->getArgument('dupesTable');
        $remapTable = $input->getArgument('remapTable');
        $columns = explode(':', $input->getArgument('columns'));

        $posFileHandle = $this->findOrCreatePositionFile($dupesTable, $remapTable);

        $removalsTable = $dupesTable . '_removals';
        
        if ($stage == 'dedupe') {
            $this->deduplicateTable($dupesTable, $columns);
            
            $this->createTableStructure($dupesTable, $removalsTable, $columns); 
            
            $this->addNewColumn($removalsTable, 'new_id');

            // the dupesTable was deduplicated, so now its the uniques table!
            $uniquesTable = $dupesTable;
            unset($dupesTable);

            $this->insertDiffToNewTable($uniquesTable, $this->tableWithDupes, $removalsTable, $columns);

            $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
        } elseif ($stage === 'removals') {
            // dupes table already been deduped, so rename
            $uniquesTable = $dupesTable;

            $tableWithDupes = $dupesTable . '_with_dupes';

            $this->insertDiffToNewTable($uniquesTable, $tableWithDupes, $removalsTable, $columns);

            $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);
        }

        $this->remapFk($remapTable, $removalsTable);
    }

    protected function remapFk($remapTable, $removalsTable)
    {
        $this->comment('Remapping the ' . $remapTable . ' from ' . $removalsTable);
        // code here
        $this->feedback('Completed remapping for ' . $remapTable);
    }

    protected function insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, array $columns)
    {
        $this->comment('Updating removals table with new ids');

        $i = 1;

        $count = $this->db->table($uniquesTable)->count();

        while ($i < $count && $i !== null) {

            array_unshift($columns, 'id');

            $uniqueRow = $this->db->table($uniquesTable)->select($columns)->where('id', $i)->first();

            if (is_null($uniqueRow)) {
                $i = $this->getNextId($i, $uniquesTable);
            } else {
                // remove the id column and store in id, leaving only the columns
                $id = array_shift($uniqueRow);

                $this->db->table($removalsTable)->where($uniqueRow)->where('new_id', null)->update(['new_id' => $id]);
                $this->info('Updated removals table for ' . $uniquesTable . '.id = ' . $id);
                $i = $this->getNextId($id, $uniquesTable);
            }
        }

        $this->feedback('Completed updating removals table');
    }

    protected function indexTable($table, $col = 'id')
    {
        $this->info('Indexing ' . $table . ' on ' . $col);
        $this->pdo->statement('ALTER TABLE ' . $table . ' ADD PRIMARY KEY(id)');
        $this->comment('Finished indexing.');
    }

    protected function createTableStructure($tableName, $newTableName, $columns = array())
    {
        $sql = 'CREATE TABLE ' . ticks($newTableName) . ' LIKE ' . ticks($tableName);
        
        $this->pdo->statement($sql);
    }

    protected function seedTable($sourceTable, $targetTable, $columns)
    {
        if (is_array($columns)) $columns = tickCommaSeperate($columns);

        $sql = 'INSERT ' . ticks($targetTable) . ' SELECT ' . $columns . ' FROM ' . ticks($sourceTable);

        $this->pdo->statement($sql);
    }

    protected function insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, array $columns)
    {
        $this->comment('Populating removals table');

        $columns = $this->pdo->toTickCommaSeperated($columns);
        
        $selectSql = 'SELECT ' . $columns . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN (SELECT id FROM ' . $uniquesTable . ')';
        
        $sql = 'INSERT INTO ' . $removalsTable . ' (' . $columns . ') ' . $selectSql;

        $this->pdo->statement($sql);

        $this->feedback('Populating of removals table completed');
    }

    protected function findOrCreatePositionFile($dupesTable, $remapTable)
    {
        $this->file = __DIR__ . '/../dedupe_' . $dupesTable . '_remap_' . $remapTable . '_pos.txt';

        if ( ! file_exists($this->file)) {
            return fopen($this->file, 'w+');
        }

        if ( ! is_writable($this->file)) throw new \Exception('Cannot write file to ' . $this->file);
    }

}