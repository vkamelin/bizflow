<?php

declare(strict_types=1);

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
                ->action(\App\Presentation\Http\HomePage\Action::class)
                ->name('home'),
        ),
];
