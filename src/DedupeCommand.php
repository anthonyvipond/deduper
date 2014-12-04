<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DedupeCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('dedupe')
             ->setDescription('De-duplicate a table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('nobackups', InputOption::VALUE_OPTIONAL, null, 'Whether a backup of the tables are needed or not');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);
        
        $table = $input->getArgument('table');

        $columns = explode(':', $input->getArgument('columns'));

        $this->outputDuplicateData($table, $columns);

        if ($this->duplicateRows === 0) {
            $this->notifyNoDuplicates($table, $columns);
            return;
        }

        if ( ! $input->getOption('nobackups')) $this->backup($table);

        $this->info('Removing duplicates from original. Please hold...');

        $this->dedupe($table, $columns);
        
        $this->feedback('Dedupe completed.');

        $this->info('Restoring original table schema...');

        $this->pdo->statement('ALTER TABLE ' . $table . ' DROP INDEX idx_dedupe;');
        $this->feedback('Schema restored.');

        $this->info('Recounting total rows...');
        $total = $this->db->table($table)->count();

        print 'There are now ' . $total . ' total rows in ' . $this->comment($table);
    }

}