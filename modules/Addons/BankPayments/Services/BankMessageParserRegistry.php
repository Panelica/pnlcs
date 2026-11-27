<?php

namespace Modules\Addons\BankPayments\Services;

use Illuminate\Contracts\Container\Container;
use Modules\Addons\BankPayments\Services\Parsers\NestBankParser;

class BankMessageParserRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(string $name): BankMessageParser
    {
        $configured = config('bank-payments.parsers.'.$name);
        $class = is_string($configured) && class_exists($configured)
            ? $configured
            : NestBankParser::class;

        return $this->container->make($class);
    }
}
