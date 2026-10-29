<?php
declare(strict_types=1);

namespace App\Database;

/** Oracle недоступен или установленное соединение было потеряно. */
final class DatabaseUnavailableException extends \RuntimeException
{
}
