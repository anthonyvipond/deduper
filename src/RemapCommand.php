<?php

namespace DRT;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Capsule\Manager as DB;
use SimplePdo\SimplePdo;

class RemapCommand extends BaseCommand {

    protected $db;
    protected $pdo;
    protected $creds = __DIR__ . '/../config/creds.php';

    public function configure()
    {
        $this->setName('remap')
             ->setDescription('De-duplicate a table and remap another one')
             ->addArgument('dupesTable', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addArgument('columns', InputArgument::REQUIRED, 'Colon seperated rows that define the uniqueness of a row')
             ->addOption('dupesParentKey', null, InputOption::VALUE_REQUIRED, 'The parent key on dupes table. Usually id')
             ->addOption('remapFk', null, InputOption::VALUE_REQUIRED, 'The foreign key on the remap table getting remapped')
             ->addOption('startId', null, InputOption::VALUE_OPTIONAL, 'Where to start remapping from on the junk table', 1)
             ->addOption('nobackups', null, InputOption::VALUE_NONE, 'Whether a backup of the tables are needed or not');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $dupesTable = $input->getArgument('dupesTable');
        $remapTable = $input->getArgument('remapTable');

        $this->findOrCreatePositionFile($dupesTable, $remapTable);


    }

    protected function findOrCreatePositionFile($dupesTable, $remapTable)
    {
        $this->file = __DIR__ . '/dedupe_' . $dupesTable . '_remap_' . $remapTable . '_pos.txt';

        if ( ! is_writable($this->file)) throw new Exception('Cannot write file to ' . __DIR__);

        fopen($this->file, 'w+');
    }

}