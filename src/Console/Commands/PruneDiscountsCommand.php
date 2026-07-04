<?php

namespace Binafy\LaravelDiscount\Console\Commands;

use Binafy\LaravelDiscount\Models\Discount;
use Illuminate\Console\Command;

class PruneDiscountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount:prune
                            {--days=0 : Only prune discounts expired at least this many days ago}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete discounts whose expiry date has passed (their usage records are removed with them)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $count = Discount::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$count} expired ".str('discount')->plural($count).'.');

        return self::SUCCESS;
    }
}
