<?

namespace karmabunny\visor\errors;

use Exception;
use karmabunny\interfaces\HttpExceptionInterface;
use Throwable;

class HttpException extends Exception implements HttpExceptionInterface
{

    public function __construct(int $status, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }


    public function getStatusCode(): int
    {
        return $this->getCode();
    }

}
