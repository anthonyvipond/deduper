<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DedupeCommand extends BaseCommand {

    protected $needBackup;

    public function configure()
    {
        $this->setName('dedupe')
             ->setDescription('De-duplicate a table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('backups', null, InputOption::VALUE_OPTIONAL, 'Whether a backup of the table is needed or not', true);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $needBackup = $input->getOption('backups') === 'false' ? false : true;

        $this->deduplicateTable(
            $input->getArgument('table'), 
            explode(':', $input->getArgument('columns')),
            $needBackup
        );
    }

    public function deduplicateTable($table, array $columns)
    {
        $this->validateColumnsAndSetPurgeMode($columns);

        $this->outputDuplicateData($table, $columns);

        if ($this->duplicateRows === 0) {
            $this->notifyNoDuplicates($table, $columns);
            return;
        }

        // if purge mode isn't alter, a backup will be created anyways
        if ($this->purgeMode == 'alter') $this->backup($table);

        $this->info('Removing duplicates from original. Please hold...');

        $this->dedupe($table, $columns);
        
        $this->feedback('Dedupe completed.');

        if ($this->purgeMode == 'alter') {
            $this->info('Restoring original table schema...');
            $this->pdo->statement('ALTER TABLE ' . $table . ' DROP INDEX idx_dedupe;');
            $this->feedback('Schema restored.');
        }

        $this->info('Recounting total rows...');

        $totalRows = $this->pdo->getTotalRows($table);
        $this->feedback($table . ' now has ' . $totalRows . ' total rows');

        $duplicateRows = $this->pdo->getDuplicateRows($table, $columns);
        $this->feedback($table . ' now has ' . $duplicateRows . ' duplicate rows');

    }

}