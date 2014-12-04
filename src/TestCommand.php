<?php

namespace DRT;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TestCommand extends Command {

    public function configure()
    {
        $this->setName('testy')
             ->setDescription('De-duplicate a table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table to be deduped');
             // ->addOption('nobackups', null, InputOption::VALUE_OPTIONAL, 'Whether a backup of the table is needed or not');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        print 'hi';

        return 'ok';
    }

}