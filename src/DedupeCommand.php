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
        $duplicateRows = $this->outputDuplicateData($table, $columns);

        if ($duplicateRows === 0) {
            $this->info('There are no duplicate rows in ' . $table . ' using cols: ' . commaSeperate($columns));
            return;
        }

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

        $this->indexOriginalTable($table, $columns);

        $originalTable = $table . '_original';

        $this->pdo->statement('CREATE TABLE ' . $table . '_deduped LIKE ' . $table);
        $this->pdo->statement('INSERT ' . $table . '_deduped SELECT * FROM ' . $table . ' GROUP BY ' . $tickColumns);
        $this->pdo->statement('RENAME TABLE ' . $table . ' TO ' . $originalTable);
        $this->pdo->statement('RENAME TABLE ' .  $table . '_deduped TO ' . $table);

        $this->insertRemovedRowsToRemovalsTable($table, $originalTable);

        $this->tableWithDupes = $table . '_with_dupes';
    }

    protected function insertRemovedRowsToRemovalsTable($table, $originalTable)
    {
        $this->pdo->statement('CREATE TABLE ' . $table . '_removals LIKE ' . $table);
        $subQuery = '(SELECT id FROM ' . $table . ')';
        $this->pdo->statement('INSERT ' . $table . '_removals SELECT * FROM ' . $originalTable . ' WHERE id NOT IN ' . $subQuery);
    }

    protected function indexOriginalTable($table, $columns)
    {
        $this->comment('Creating composite index on ' . $table . ' to speed things up...');
        $this->pdo->createCompositeIndex($table, $columns);
        $this->feedback('Created composite index on ' . $table);
    }

}