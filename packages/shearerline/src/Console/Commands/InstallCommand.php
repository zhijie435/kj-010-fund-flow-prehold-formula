<?php

namespace Shearerline\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'shearerline:install';

    protected $description = 'Install Shearerline package';

    public function handle(): int
    {
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--provider' => 'Shearerline\\ShearerlineServiceProvider',
            '--tag' => 'shearerline-config',
        ]);

        $this->info('Publishing migrations...');
        $this->call('vendor:publish', [
            '--provider' => 'Shearerline\\ShearerlineServiceProvider',
            '--tag' => 'shearerline-migrations',
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->info('Shearerline installed successfully!');

        return self::SUCCESS;
    }
}
