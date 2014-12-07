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
             ->addArgument('uniquesTable', InputArgument::REQUIRED, 'The table with unique values')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('foreignKey', null, InputOption::VALUE_REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('parentKey', null, InputOption::VALUE_OPTIONAL, 'The parent key on dupes table. Usually id', 'id')
             ->addOption('startId', null, InputOption::VALUE_OPTIONAL, 'Where to start remapping from on the junk table', 1)
             ->addOption('backups', null, InputOption::VALUE_OPTIONAL, 'Whether a backup of the tables are needed or not', true);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $needBackup = $input->getOption('backups') === 'false' ? false : true;

        $this->init($output);

        $uniquesTable = $input->getArgument('uniquesTable');
        $remapTable = $input->getArgument('remapTable');
        $columns = explode(':', $input->getArgument('columns'));

        $foreignKey = $input->getOption('foreignKey');
        $parentKey = $input->getOption('parentKey');
        $startId = $input->getOption('startId');

        $posFileHandle = $this->findOrCreatePositionFile($uniquesTable, $remapTable);

        $removalsTable = $uniquesTable . '_removals';

        $dupesTable = $uniquesTable . '_with_dupes';

        $this->pdo->copyTable($dupesTable, $removalsTable);

        $this->insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, $columns);

        $this->pdo->addIntegerColumn($removalsTable, 'new_id');

        $this->insertNewIdsToRemovalsTable($uniquesTable, $removalsTable, $columns);

        // $this->remapFk($remapTable, $removalsTable);
    }

    protected function remapFk($remapTable, $removalsTable)
    {
        $this->comment('Remapping the ' . $remapTable . ' from ' . $removalsTable);
        // code here
        $this->feedback('Completed remapping for ' . $remapTable);
    }

    protected function insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, array $columns)
    {
        $this->comment('Populating removals table');

        // insert the ids as well
        array_unshift($columns, 'id');

        $columnString = tickCommaSeperate($columns);
        
        $selectSql = 'SELECT ' . $columnString . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN (SELECT id FROM ' . $uniquesTable . ')';
        
        $sql = 'INSERT INTO ' . $removalsTable . ' (' . $columnString . ') ' . $selectSql;

        $this->pdo->statement($sql);

        // remove id from columns
        array_shift($columns);

        $this->feedback('Populating of removals table completed');
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
                $i = $this->pdo->getNextId($i, $uniquesTable);
            } else {
                // remove the id column and store in id, leaving only the columns
                $id = array_shift($uniqueRow);

                $this->db->table($removalsTable)->where($uniqueRow)->where('new_id', null)->update(['new_id' => $id]);
                $this->info('Updated removals table for ' . $uniquesTable . '.id = ' . $id);
                $i = $this->pdo->getNextId($id, $uniquesTable);
            }
        }

        $this->feedback('Completed updating removals table');
    }

    // protected function indexTable($table, $col = 'id')
    // {
    //     $this->info('Indexing ' . $table . ' on ' . $col);
    //     $this->pdo->statement('ALTER TABLE ' . $table . ' ADD PRIMARY KEY(id)');
    //     $this->comment('Finished indexing.');
    // }

    

    // protected function seedTable($sourceTable, $targetTable, $columns)
    // {
    //     if (is_array($columns)) $columns = tickCommaSeperate($columns);

    //     $sql = 'INSERT ' . ticks($targetTable) . ' SELECT ' . $columns . ' FROM ' . ticks($sourceTable);

    //     $this->pdo->statement($sql);
    // }

    protected function findOrCreatePositionFile($dupesTable, $remapTable)
    {
        $this->file = __DIR__ . '/../dedupe_' . $dupesTable . '_remap_' . $remapTable . '_pos.txt';

        if ( ! file_exists($this->file)) {
            return fopen($this->file, 'w+');
        }

        if ( ! is_writable($this->file)) throw new \Exception('Cannot write file to ' . $this->file);
    }

}