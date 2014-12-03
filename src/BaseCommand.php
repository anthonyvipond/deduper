<?php namespace DRT;

use Symfony\Component\Console\Command\Command;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use SimplePdo\SimplePdo;

abstract class BaseCommand extends Command {

    protected $db;
    protected $pdo;
    protected $creds = __DIR__ . '/../config/database.php';

    protected function outputDuplicateData($dupeTable, $columns)
    {
        $this->info('Counting total rows...');
        $totalRows = $this->db->table($dupeTable)->count();
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($totalRows) . ' total rows');

        $this->info('Counting unique rows');
        $sql = 'DISTINCT ' . $this->ticks($columns);

        $uniqueRows = $this->pdo->select('count(' . $sql . ') as uniques FROM ' . $dupeTable)->fetch()->uniques;
        
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($uniqueRows) . ' unique rows');

        $this->info('Counting duplicate rows');
        $this->duplicateRows = $totalRows - $uniqueRows;
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($this->duplicateRows) . ' duplicate rows');
    }

    protected function noDuplicates($dupeTable, $columns)
    {
        print 'There are no duplicate rows in ' . $dupeTable . ' using these columns: ';
        $this->comment($columns);
        die;
    }

    protected function backup($table, $backupTable = null, $cols = '*', $temp = false)
    {
        if ( ! $backupTable) $backupTable = $table . '_bak_' . time();

        $this->createBackupTable($table, $backupTable, $cols);

        $this->seedBackupTable($table, $backupTable, $cols);

        if ($temp) $this->indexTable($backupTable);
    }

    protected function indexTable($table, $col = 'id')
    {
        $this->info('Indexing ' . $table . ' on ' . $col);
        $this->pdo->statement('ALTER TABLE ' . $table . ' ADD PRIMARY KEY(id)');
        $this->comment('Finished indexing.');
    }

    protected function createBackupTable($table, $backupTable, $cols)
    {
        $this->info('Creating backup table... (' . $backupTable . ')');
        $sql = 'CREATE TABLE ' . $this->ticks($backupTable) . 
               ' SELECT `id`,' . $this->ticks($cols) . ' FROM ' . $this->ticks($table) . ' LIMIT 0';
        $this->pdo->statement($sql);
    }

    protected function seedBackupTable($table, $backupTable, $cols)
    {
        $this->info('Seeding backup table... (' . $backupTable . ')');

        $this->pdo->statement('INSERT ' . $this->ticks($backupTable) . 
                      ' SELECT `id`,' . $this->ticks($cols) . ' FROM ' . $this->ticks($table));
    }

    protected function idExists($id, $table)
    {
        $result = $this->db->selectOne('SELECT exists(SELECT 1 FROM ' . $table . ' where id=' . $id . ') as `exists`');

        return (bool) $result->exists;
    }

    protected function getNextId($id, $table)
    {
        $result = $this->db->table($table)->select($this->db->raw('min(id) as id'))->where('id', '>', $id)->first();

        if (isset($result->id)) {
            return $result->id;
        }

        return null;
    }

    protected function ticks($string)
    {
        $cols = explode(',', $string);

        $string = implode('`,`', $cols);

        return '`' . $string . '`';
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

    protected function initDb(Capsule $capsule)
    {
        $capsule->addConnection(require $this->creds);

        $capsule->setEventDispatcher(new Dispatcher);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }

    protected function init($output)
    {
        $this->output = $output;
        $this->pdo = new SimplePdo(require $this->creds);
        $this->db = $this->initDb(new Capsule);
    }

    // protected function logQueries($needLogging)
    // {
    //     if ($needLogging) {
    //         Event::listen('illuminate.query', function($sql, $bindings, $time) {
    //             $sql = str_replace(array('%', '?'), array('%%', '%s'), $sql);
    //             $sql = vsprintf($sql, $bindings);
    //             $this->comment($sql);
    //         });
    //     }
    // }

}