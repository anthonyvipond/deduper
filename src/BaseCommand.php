<?php namespace DRT;

use Symfony\Component\Console\Command\Command;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use SimplePdo\Exceptions\SimplePdoException as SimplePdoException;
use SimplePdo\SimplePdo;

abstract class BaseCommand extends Command {

    protected $db;
    protected $pdo;
    protected $creds;
    protected $purgeMode = 'alter';

    protected function outputDuplicateData($dupeTable, array $columns)
    {
        $this->info('Counting total rows...');
        $totalRows = $this->pdo->getTotalRows($dupeTable);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($totalRows) . ' total rows');

        $this->info('Counting unique rows...');
        $uniqueRows = $this->pdo->getUniqueRows($dupeTable, $columns);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($uniqueRows) . ' unique rows');

        $this->info('Counting duplicate rows...');
        $duplicateRows = $this->pdo->getDuplicateRows($dupeTable, $columns);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($duplicateRows) . ' duplicate rows');

        return $duplicateRows;
    }

    protected function backup($table, $columns = '*')
    {
        $backupTable = $table . '_with_dupes';

        if ($this->tableExists($backupTable)) {
            $this->comment($this->backupTable . ' already exists. continuing...');
            return;
        }

        $columns === '*' ? '*' : '`id`,' . $this->pdo->toTickCommaSeperated($columns);

        $this->info('Backing up table... (' . $backupTable . ')');
        $this->createTableStructure($table, $backupTable, $columns);

        $this->seedTable($table, $backupTable, $columns);
        $this->feedback('Backed up table: (' . $backupTable . ')');
    }

    protected function feedback($string)
    {
        print $string . PHP_EOL;
    }

    protected function info($string)
    {
        $this->output->writeln('<info>' . $string . '</info>');
    }

    protected function comment($string)
    {
        $this->output->writeln('<comment>' . $string . '</comment>');
    }

    protected function error($string)
    {
        $this->output->writeln('<error>' . $string . '</error>');
    }

    protected function notify($string)
    {
        $this->output->writeln('<question>' . $string . '</question>');
    }

    protected function initDb(Capsule $capsule)
    {
        global $dbCreds;

        $capsule->addConnection($dbCreds);
        $capsule->setEventDispatcher(new Dispatcher);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }

    protected function init($output)
    {
        global $dbCreds;

        // $this->database = $dbCreds['database'];
        
        $this->output = $output;
        $this->pdo = new SimplePdo($dbCreds);
        $this->db = $this->initDb(new Capsule);
    }

}