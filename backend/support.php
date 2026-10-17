<?php

declare(strict_types=1);

class ValidationException extends RuntimeException
{
}

class RateLimitException extends ValidationException
{
}

class ExpenseValidationException extends ValidationException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}

function public_error_message(Throwable $error): string
{
    if ($error instanceof ValidationException) {
        return $error->getMessage();
    }
    $reference = bin2hex(random_bytes(6));
    error_log('DEVIOZ error ' . $reference . ' ' . get_class($error) . ': ' . $error->getMessage() . "\n" . $error->getTraceAsString());
    return 'No se pudo completar la operación. Referencia: ' . $reference;
}

function validate_password(string $password): void
{
    if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new ValidationException('La contraseña debe tener entre 8 y 72 bytes.');
    }
}

function transaction(callable $action): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $action();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function page_offset(): int
{
    return (max(1, min(100000, (int) ($_GET['page'] ?? 1))) - 1) * 50;
}
