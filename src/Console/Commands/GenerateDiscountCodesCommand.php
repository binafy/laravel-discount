<?php

namespace Binafy\LaravelDiscount\Console\Commands;

use Binafy\LaravelDiscount\Support\DiscountCodeGenerator;
use Illuminate\Console\Command;

class GenerateDiscountCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount:generate
                            {count=1 : How many codes to generate}
                            {--prefix= : Optional prefix, e.g. SUMMER}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate unique discount codes';

    /**
     * Execute the console command.
     */
    public function handle(DiscountCodeGenerator $generator): int
    {
        $count = (int) $this->argument('count');

        if ($count < 1) {
            $this->error('The count must be at least 1.');

            return self::FAILURE;
        }

        $codes = $generator->generateMany($count, $this->option('prefix'));

        foreach ($codes as $code) {
            $this->line($code);
        }

        return self::SUCCESS;
    }
}
