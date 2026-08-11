<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$scenario = $argv[1] ?? '';
$params = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

try {
    $user = User::query()->findOrFail((int) $params['user_id']);
    Auth::login($user);
    $result = match ($scenario) {
        'return_complete' => app(RetailReturnAction::class)->complete($user, \App\Modules\Retail\Models\RetailReturn::query()->findOrFail((int) $params['return_id']), (string) $params['idempotency_key'], isset($params['payment_method_id']) ? (int) $params['payment_method_id'] : null),
        'gift_redeem' => app(GiftCardAction::class)->redeem($user, \App\Modules\Retail\Models\GiftCard::query()->findOrFail((int) $params['card_id']), (string) $params['amount'], (string) $params['idempotency_key'], 'concurrency', (string) $params['idempotency_key']),
        default => throw new InvalidArgumentException('Unknown race scenario.'),
    };
    fwrite(STDOUT, json_encode(['ok' => true, 'id' => $result->id]).PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'exception' => get_class($exception), 'message' => $exception->getMessage()]).PHP_EOL);
}
