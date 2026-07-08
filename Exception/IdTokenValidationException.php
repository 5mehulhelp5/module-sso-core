<?php
/**
 * Copyright © MageDevGroup. All rights reserved.
 */
declare(strict_types=1);

namespace MageDevGroup\SsoCore\Exception;

/**
 * Thrown when an ID token fails signature or claim validation.
 */
class IdTokenValidationException extends \RuntimeException
{
}
