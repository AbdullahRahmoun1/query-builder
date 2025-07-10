<?php

namespace Wever\AdvancedQueryBuilder\Commands;

use Illuminate\Console\Command;

class AdvancedQueryBuilderCommand extends Command
{
    public $signature = 'query-builder';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
