<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeApi extends Command
{
    protected $signature = 'make:api {name}';
    protected $description = 'Create a new API class';

    public function handle()
    {
        $name = $this->argument('name');
        $path = app_path("Apis/{$name}.php");

        if (File::exists($path)) {
            $this->error('API already exists!');
            return;
        }

        File::ensureDirectoryExists(app_path('Apis'));

        $content = "<?php\n\nnamespace App\Apis;\n\nclass {$name}\n{\n    // \n}\n";

        File::put($path, $content);

        $this->info("API {$name} created successfully.");
    }
}
