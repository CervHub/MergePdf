<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeRepository extends Command
{
    protected $signature = 'make:repository {name}';
    protected $description = 'Create a new repository class';

    public function handle()
    {
        $name = $this->argument('name');
        $path = app_path("Repositories/{$name}.php");

        if (File::exists($path)) {
            $this->error('Repository already exists!');
            return;
        }

        File::ensureDirectoryExists(app_path('Repositories'));

        $content = "<?php\n\nnamespace App\Repositories;\n\nclass {$name}\n{\n    // \n}\n";

        File::put($path, $content);

        $this->info("Repository {$name} created successfully.");
    }
}
