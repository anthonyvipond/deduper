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
        $totalRows = $this->pdo->getTotalRows($dupeTable);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($totalRows) . ' total rows');

        $this->info('Counting unique rows...');
        $uniqueRows = $this->pdo->getUniqueRows($dupeTable, $columns);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($uniqueRows) . ' unique rows');

        $this->info('Counting duplicate rows...');
        $this->duplicateRows = $this->pdo->getDuplicateRows($dupeTable, $columns);
        $this->feedback('`' . $dupeTable . '` has ' .  number_format($this->duplicateRows) . ' duplicate rows');
    }

    protected function notifyNoDuplicates($dupeTable, $columns)
    {
        print 'There are no duplicate rows in ' . $dupeTable . ' using these columns: ';
        $this->comment(commaSeperate($columns));
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
        $sql = 'CREATE TABLE ' . $this->pdo->ticks($newTableName) . ' LIKE ' . $this->pdo->ticks($tableName);
        
        $this->pdo->statement($sql);
    }

    protected function seedTable($sourceTable, $targetTable, $columns)
    {
        if (is_array($columns)) $columns = $this->pdo->toTickCommaSeperated($columns);

        $sql = 'INSERT ' . $this->pdo->ticks($targetTable) . ' SELECT ' . $columns . ' FROM ' . $this->pdo->ticks($sourceTable);

        $this->pdo->statement($sql);
    }

    protected function dedupe($table, array $columns)
    {
        $commaColumns = commaSeperate($columns);
        $tickColumns = tickCommaSeperate($columns);

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

}