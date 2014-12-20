<?php namespace DRT;

use Symfony\Component\Console\Command\Command;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use SimplePdo\Exceptions\SimplePdoException as SimplePdoException;
use SimplePdo\SimplePdo;

abstract class BaseCommand extends Command {

    protected $db;
    protected $pdo;
    protected $creds;

    public function __construct()
    {
        parent::__construct('DLR - Dedupe, Link and Remap');

        global $dbCreds;

        $this->pdo = new SimplePdo($dbCreds);
        $this->db = $this->initDb(new Capsule);
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

    protected function error($string)
    {
        $this->output->writeln('<error>' . $string . '</error>');
    }

    protected function notify($string)
    {
        $this->output->writeln('<question>' . $string . '</question>');
    }

    protected function initDb(Capsule $capsule)
    {
        global $dbCreds;

        $capsule->addConnection($dbCreds);
        $capsule->setEventDispatcher(new Dispatcher);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }

}