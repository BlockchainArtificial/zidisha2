<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ApplicationSetup extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Used to setup environment and database variables.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $databaseConfig = array();

        $environment = $this->ask('What is your current environment? [Default=local] : ', 'local');

        $databaseConfig['databaseHost'] = $this->ask('Enter your database host [Default=localhost] : ', 'localhost');

        $databaseConfig ['databaseName'] = $this->ask('Enter your database name [Default=homestead] : ', 'homestead');

        $databaseConfig['databaseUsername'] = $this->ask(
            'Enter your database username [Default=homestead] : ',
            'homestead'
        );

        $databaseConfig ['databasePassword'] = $this->secret('Enter your database password [Default=secret] : ') ?: 'secret';

        $databaseConfig['databasePortNumber'] = $this->ask('Enter your database port number [Default=5432] : ', '5432');

        $file = new \Illuminate\Filesystem\Filesystem();
        $contents = <<<ENV
<?php
define("LARAVEL_ENV", '$environment');
ENV;

        $file->put(base_path() . '/bootstrap/env.php', $contents);

        $config = View::make('command.runtime-conf', $databaseConfig);

        if (!$file->isDirectory(base_path() . '/app/config/propel/')) {
            $file->makeDirectory(base_path() . '/app/config/propel');
        }

        $file->put(base_path() . '/app/config/propel/runtime-conf.xml', $config);
        $file->put(base_path() . '/app/config/propel/buildtime-conf.xml', $config);

        exec('vendor/bin/propel config:convert-xml --output-dir="app/config/propel" --input-dir="app/config/propel"');

        $this->info('You are done.');
    }

}
