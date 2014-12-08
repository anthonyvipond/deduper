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

        $this->initiateDedupe(
            $input->getArgument('table'), 
            explode(':', $input->getArgument('columns')),
            $needBackup
        );
    }

    protected function initiateDedupe($table, array $columns)
    {
        $this->validateColumnsAndSetPurgeMode($columns);

        $duplicateRows = $this->outputDuplicateData($table, $columns);

        if ($duplicateRows === 0) {
            $this->info('There are no duplicate rows in ' . $table . ' using cols: ' . commaSeperate($columns));
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
        $this->feedback($table . ' now has ' . number_format($totalRows) . ' total rows');

        $duplicateRows = $this->pdo->getDuplicateRows($table, $columns);
        $this->feedback($table . ' now has ' . $duplicateRows . ' duplicate rows');
    }

    protected function dedupe($table, array $columns)
    {
        $commaColumns = commaSeperate($columns);
        $tickColumns = tickCommaSeperate($columns);

        if ($this->purgeMode == 'alter') {
            $statement = 'ALTER IGNORE TABLE ' . $table . ' ADD UNIQUE INDEX idx_dedupe (' . $commaColumns . ')';
            $this->pdo->statement($statement);
        } else {
            $this->comment('Creating composite index on ' . $table . ' to speed things up...');
            $this->pdo->createCompositeIndex($table, $columns);
            $this->comment('Created composite index on ' . $table);

            $this->pdo->statement('CREATE TABLE ' . $table . '_deduped LIKE ' . $table);
            $this->pdo->statement('INSERT ' . $table . '_deduped SELECT * FROM ' . $table . ' GROUP BY ' . $tickColumns);
            $this->pdo->statement('RENAME TABLE ' . $table . ' TO ' . $table . '_with_dupes');
            $this->pdo->statement('RENAME TABLE ' .  $table . '_deduped TO ' . $table);

            // the target table is now the one holding duplicates
            $this->tableWithDupes = $table . '_with_dupes';
        }
    }

    protected function validateColumnsAndSetPurgeMode(array $columns)
    {
        foreach ($columns as $column) {
            if ($this->pdo->isMySqlKeyword($column)) {
                $this->comment('`' . $column . '` is a MySQL keyword. Bad column name, buddy.');
                $this->purgeMode = 'groupBy';
            }
        }
    }

}