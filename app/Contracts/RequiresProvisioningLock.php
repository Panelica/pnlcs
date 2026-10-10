<?php

namespace App\Contracts;

/** Opt in to serialization through the durable local lifecycle commit. */
interface RequiresProvisioningLock extends ServerModuleInterface {}
