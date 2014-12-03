<?php

namespace DRT;

class Dedupe {

    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function dedupe($table, array $columns)
    {
        $db = $this->db;

        print "$table\n";
    }
}