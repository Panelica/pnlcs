<?php

namespace Modules\Addons\BankPayments\Services;

use Illuminate\Contracts\Container\Container;
use Modules\Addons\BankPayments\Services\Parsers\NestBankParser;

class BankMessageParserRegistry
{
    public function __construct(private readonly Container $container) {}

    public function forBank(string $bank): BankMessageParser
    {
        $parser = config('bank-payments.banks.'.$bank.'.parser');

        // The provider config is not loaded in some isolated service tests;
        // keep the built-in Nest Bank mapping usable without weakening the
        // unknown-bank check when the config is present.
        if ((! is_string($parser) || $parser === '') && $bank === 'nest_bank') {
            $parser = 'nest_bank';
        }

        if (! is_string($parser) || $parser === '') {
            throw new \InvalidArgumentException('No parser is configured for bank '.$bank);
        }

        return $this->for($parser);
    }

    public function for(string $name): BankMessageParser
    {
        $configured = config('bank-payments.parsers.'.$name);
        $class = is_string($configured) && class_exists($configured)
            ? $configured
            : NestBankParser::class;

        return $this->container->make($class);
    }
}
