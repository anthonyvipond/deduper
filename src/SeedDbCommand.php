<?php

namespace DRT;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SeedDbCommand extends Command {

    public function configure()
    {
        $this->setName('duplicateTable')
             ->setDescription('Duplicate a production table schema and data to new one')
             ->addArgument('sourceDb', InputArgument::REQUIRED, 'The source database name')
             ->addArgument('targetDb', InputArgument::REQUIRED, 'The target database name')
             ->addArgument('targetTable', InputArgument::REQUIRED, 'The target table')
             ->addOption('columns', null, InputOption::VALUE_OPTIONAL, 'The columns to be copied over. All by default.', '*');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $sourceTable = $input->getArgument('sourceTable');
        $targetTable = $input->getArgument('targetTable');
        $columns     = explode(':', $input->getArgument('columns'));

        $min = $this->db->table($sourceTable)->min('id');
        $max = $this->db->table($sourceTable)->max('id');

        for ($i = $min; $i <= $max; $i++)
        {
            
        }
    }

}