<?php

namespace DRT;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class DeleteCommand extends BaseCommand {

    public function configure()
    {
        $this->setName('delete')
             ->setDescription('Delete duplicates from the original table')
             ->addArgument('table', InputArgument::REQUIRED, 'The table to have duplicates removed from')
             ->addArgument('removalsTable', InputArgument::REQUIRED, 'The removals table holding only duplicates');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($output);

        $table         = $input->getArgument('table');
        $removalsTable = $input->getArgument('removalsTable');
    }

}