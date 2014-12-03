<?php

namespace DRT;

class Dedupe extends Base {

    protected $db;
    protected $pdo;

    public function __construct($db, $pdo, $output)
    {
        $this->db = $db;
        $this->pdo = $pdo;
        $this->output = $output;
    }

    public function dedupe($dupeTable, array $columns, $backup = true)
    {
        $db = $this->db;
        $pdo = $this->pdo;

        $columns = implode(',', $columns);

        $this->outputDuplicateData($dupeTable, $columns);

        if ($this->duplicateRows === 0) {
            $this->noDuplicates($dupeTable, $columns);
        }

        if ($backup !== false) {
            $this->backup($dupeTable, null, $columns);
        }

        $this->info('Removing duplicates from original. Please hold...');
        $statement = 'ALTER IGNORE TABLE ' . $dupeTable . ' ADD UNIQUE INDEX idx_dedupe (' . $columns . ')';

        $pdo->statement($statement);
        $this->feedback('Dedupe completed.');

        $this->info('Restoring original table schema...');
        $pdo->statement('ALTER TABLE ' . $dupeTable . ' DROP INDEX idx_dedupe;');
        $this->feedback('Schema restored.');

        $this->info('Recounting total rows...');
        $total = $db->table($dupeTable)->count();

        print 'There are now ' . $total . ' total rows in ';
        $this->comment($dupeTable);
    }


}