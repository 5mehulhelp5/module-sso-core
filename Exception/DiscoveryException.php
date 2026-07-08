<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Exception;

/**
 * Thrown when the OIDC discovery document cannot be fetched or is malformed.
 */
class DiscoveryException extends \RuntimeException
{
}
