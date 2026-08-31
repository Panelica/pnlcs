<?php
return [
    App\Providers\TranslationServiceProvider::class,
    App\Providers\ViewServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\ModuleServiceProvider::class,
    Modules\CompanyLookup\CompanyLookupServiceProvider::class,
    App\Providers\MailConfigProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\HookServiceProvider::class,
];
