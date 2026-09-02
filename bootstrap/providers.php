<?php
return [
    App\Providers\TranslationServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    Modules\Ksef\KsefServiceProvider::class,
    App\Providers\MailConfigProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\HookServiceProvider::class,
];
