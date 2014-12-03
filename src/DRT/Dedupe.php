<?php

namespace DRT;

class Dedupe extends Base {

    protected $db;

    public function __construct($db, $output)
    {
        $this->db = $db;
        $this->output = $output;
    }

    public function dedupe($dupeTable, array $columns, $backup = false)
    {
        $db = $this->db;

        $columns = implode(',', $columns);

        $this->outputDuplicateData($dupeTable, $columns);

        if ($duplicateRows === 0) {
            $this->noDuplicates($dupeTable, $columns);
        }

        if ($backup !== false) {
            $this->backup($dupeTable);
        }

        $this->info('Removing duplicates from original. Please hold...');
        $statement = 'ALTER IGNORE TABLE ' . $dupeTable . ' ADD UNIQUE INDEX idx_dedupe (' . $columns . ')';

        $db->statement($statement);
        $this->feedback('Dedupe completed.');

        $this->info('Restoring original table schema...');
        $db->statement('ALTER TABLE ' . $dupeTable . ' DROP INDEX idx_dedupe;');
        $this->feedback('Schema restored.');

        $this->info('Recounting total rows...');
        $total = $db->table($dupeTable)->count();

        print 'There are now ' . $total . ' total rows in ';
        $this->comment($table);
    }


}