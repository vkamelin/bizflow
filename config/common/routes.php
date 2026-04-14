<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/api/v1')
    ->routes(
        Route::get('/me')
        ->name('api_v1_me')
        ->action()
    ),

    // Base web url
    Group::create()
        ->routes(
            Route::get('/')
                ->action(Web\HomePage\Action::class)
                ->name('home'),
        ),
];
