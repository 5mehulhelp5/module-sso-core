<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\SsoCore\Exception;

/**
 * Thrown when an ID token fails signature or claim validation.
 */
class IdTokenValidationException extends \RuntimeException
{
}
