<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

/**
 * Explicit local Demo-only dataset entrypoint.
 *
 * This is intentionally separate from production/default seeding. It creates
 * allowlisted local identities, authorization scopes, master data, and
 * purchase-order examples required by the local Demo Auth walkthrough.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('DemoSeeder is only allowed in the local environment.');
        }

        if (! (bool) config('app.demo_auth', false)) {
            throw new LogicException('DemoSeeder requires DEMO_AUTH=true in the local environment.');
        }

        $this->call([
            CanonicalAuthorizationSeeder::class,
            DemoProductSeeder::class,
            LocalDemoSeeder::class,
            DemoApprovedPurchaseOrderSeeder::class,
            DemoPricingSeeder::class,
            DemoLabelQueueSeeder::class,
        ]);
    }
}
