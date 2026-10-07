<?php

use App\Models\BusinessSetting;
use App\Models\MeasurementTemplate;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StudioSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Explicitly isolated browser fixture. Never invoked by normal installation.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_contains(config('database.connections.'.config('database.default').'.database'), 'browser')) {
    throw new RuntimeException('Use a separate browser-test database.');
}
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--class' => StudioSeeder::class, '--force' => true]);
$credentials = ['email' => 'owner@example.invalid', 'password' => bin2hex(random_bytes(18))];
$user = User::firstOrNew(['email' => $credentials['email']]);
$user->name = 'Test owner';
$user->password = $credentials['password'];
$user->is_owner = true;
$user->email_verified_at = now();
$user->save();
BusinessSetting::findOrFail(1)->update(['name' => 'The Stitching Studio']);
$template = MeasurementTemplate::where('code', 'kurta')->firstOrFail();
Product::firstOrCreate(['name' => 'Kurta stitching'], ['template_id' => $template->id, 'price' => 220000, 'taxes' => [], 'cost_known' => true, 'cost' => 75000]);
$credentialPath = getenv('STUDIO_TEST_CREDENTIALS') ?: sys_get_temp_dir().'/stitching-browser-credentials.json';
file_put_contents($credentialPath, json_encode($credentials, JSON_THROW_ON_ERROR));
chmod($credentialPath, 0600);
echo "Isolated browser fixture ready.\n";
