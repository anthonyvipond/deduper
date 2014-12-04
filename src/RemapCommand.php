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
             ->addOption('backups', null, InputOption::VALUE_OPTIONAL, 'Whether a backup of the tables are needed or not', true);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $needBackup = $input->getOption('backups') === 'false' ? false : true;

        $this->init($output);

        $dupesTable = $input->getArgument('dupesTable');
        $remapTable = $input->getArgument('remapTable');
        $columns = explode(':', $input->getArgument('columns'));

        $posFileHandle = $this->findOrCreatePositionFile($dupesTable, $remapTable);

        $this->deduplicateTable($dupesTable, $columns);

        $removalsTable = $dupesTable . '_removals';
        $this->createTableStructure($dupesTable, $removalsTable, array_unshift($columns, 'id')); 

        // the dupesTable was deduplicated, so now its the uniques table!
        $uniquesTable = $dupesTable;
        unset($dupesTable);

        // insert the diff to the $removalsTable
        $this->comment('Populating removals table');
        $this->insertDiffToNewTable($uniquesTable, $this->tableWithDupes, $removalsTable, $columns);
        $this->comment('Populating of removals table completed');
    }

    protected function insertDiffToNewTable($uniquesTable, $dupesTable, $removalsTable, array $columns)
    {
        $columns = $this->pdo->toTickCommaSeperated($columns);
        
        $selectSql = 'SELECT ' . $columns . ' FROM ' . $dupesTable . ' WHERE `id` NOT IN (SELECT id FROM ' . $uniquesTable . ')';
        
        $sql = 'INSERT INTO ' . $removalsTable . ' ' . $selectSql;

        $this->pdo->statement($sql);
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