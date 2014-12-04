<?php namespace DRT;

use Symfony\Component\Console\Command\Command;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use SimplePdo\Exceptions\SimplePdoException as SimplePdoException;
use SimplePdo\SimplePdo;

abstract class BaseCommand extends Command {

    protected $db;
    protected $pdo;
    protected $creds = __DIR__ . '/../config/database.php';
    protected $purgeMode = 'alter';

    protected function outputDuplicateData($dupeTable, array $columns)
    {
        $this->info('Counting total rows...');
        $this->totalRows = $this->db->table($dupeTable)->count();
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($this->totalRows) . ' total rows');

        $this->info('Counting unique rows');
        $uniqueRows = $this->countUniqueRows($dupeTable, $columns);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($uniqueRows) . ' unique rows');

        $this->duplicateRows = $this->totalRows - $uniqueRows;
    }

    protected function countUniqueRows($dupeTable, $columns)
    {
        $sql = 'DISTINCT ' . $this->pdo->toTickCommaSeperated($columns);
        
        return $this->pdo->select('count(' . $sql . ') as uniques FROM ' . $dupeTable)->fetch()->uniques;
    }

    protected function notifyNoDuplicates($dupeTable, $columns)
    {
        print 'There are no duplicate rows in ' . $dupeTable . ' using these columns: ';
        $this->comment($this->pdo->toCommaSeperated($columns));
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

    protected function indexTable($table, $col = 'id')
    {
        $this->info('Indexing ' . $table . ' on ' . $col);
        $this->pdo->statement('ALTER TABLE ' . $table . ' ADD PRIMARY KEY(id)');
        $this->comment('Finished indexing.');
    }

    protected function createTableStructure($tableName, $newTableName, $columns = array())
    {
        // if ( ! empty($columns)) {
        //     $columns = $this->pdo->toTickCommaSeperated($columns);
        // }

        // $sql = 'CREATE TABLE ' . $this->pdo->ticks($newTableName) . 
        //        ' SELECT ' . $columns  . ' FROM ' . $this->pdo->ticks($tableName) . ' LIMIT 0';

        $sql = 'CREATE TABLE ' . $this->pdo->ticks($newTableName) . ' LIKE ' . $this->pdo->ticks($tableName);
        
        $this->pdo->statement($sql);
    }

    protected function seedTable($sourceTable, $targetTable, $columns)
    {
        if (is_array($columns)) $columns = $this->pdo->toTickCommaSeperated($columns);

        $sql = 'INSERT ' . $this->pdo->ticks($targetTable) . ' SELECT ' . $columns . ' FROM ' . $this->pdo->ticks($sourceTable);

        $this->pdo->statement($sql);
    }

    protected function idExists($id, $table)
    {
        $result = $this->db->selectOne('SELECT exists(SELECT 1 FROM ' . $table . ' where id=' . $id . ') as `exists`');

        return (bool) $result->exists;
    }

    protected function tableExists($table)
    {
        return is_int($this->pdo->tableExists('count(*) as rows FROM ' . $this->pdo->ticks($table)));
    }

    protected function getNextId($id, $table)
    {
        $result = $this->db->table($table)->select($this->db->raw('min(id) as id'))->where('id', '>', $id)->first();

        return isset($result->id) ? $result->id : null;
    }

    protected function addNewColumn($table, $column)
    {
        $sql = 'ALTER TABLE ' . $table . ' ADD ' . $column . ' int(11)';

        $this->pdo->statement($sql);
    }

    protected function dedupe($table, array $columns)
    {
        $commaColumns = $this->pdo->toCommaSeperated($columns);
        $tickColumns = $this->pdo->toTickCommaSeperated($columns);

        if ($this->purgeMode == 'alter') {
            $statement = 'ALTER IGNORE TABLE ' . $table . ' ADD UNIQUE INDEX idx_dedupe (' . $commaColumns . ')';
            $this->pdo->statement($statement);
        } else {
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