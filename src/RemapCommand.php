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
             ->addArgument('dupeTable', InputArgument::REQUIRED, 'The table to be deduped')
             ->addArgument('remapTable', InputArgument::REQUIRED, 'The table to be remapped')
             ->addOption('backup', InputArgument::OPTIONAL, null, 'Whether a backup of the tables are needed or not');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        
    }


}