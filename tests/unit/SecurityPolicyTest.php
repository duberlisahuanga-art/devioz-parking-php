<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityPolicyTest extends TestCase
{
    public function testPasswordsAtByteBoundary(): void
    {
        validate_password(str_repeat('a', 71));
        validate_password(str_repeat('a', 72));
        $this->expectException(ValidationException::class);
        validate_password(str_repeat('a', 73));
    }

    public function testMultibytePasswordCannotBypassLimit(): void
    {
        $this->expectException(ValidationException::class);
        validate_password(str_repeat('é', 37));
    }

    public function testNullByteIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        validate_password("password\0suffix");
    }

    public function testClosedReservationsCannotBePaid(): void
    {
        $r = ['status' => 'confirmed', 'cancellation_status' => 'none', 'ends_on' => ReservationController::today()];
        $this->assertTrue(PaymentController::eligible($r));
        foreach (['cancelled', 'expired'] as $status) {
            $this->assertFalse(PaymentController::eligible(array_replace($r, ['status' => $status])));
        }
        $this->assertFalse(PaymentController::eligible(array_replace($r, ['cancellation_status' => 'pending'])));
        $this->assertFalse(PaymentController::eligible(array_replace($r, ['ends_on' => '2000-01-01'])));
    }

    public function testBusinessTimezoneIsLima(): void
    {
        $this->assertSame('America/Lima', date_default_timezone_get());
        $instant = new DateTimeImmutable('2026-10-15 00:30:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-10-14', $instant->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d'));
    }

    public function testRateLimitIsEnforced(): void
    {
        $directory = sys_get_temp_dir() . '/devioz-limit-' . bin2hex(random_bytes(6));
        putenv('APP_STORAGE=' . $directory);
        putenv('APP_SECRET=' . str_repeat('a', 64));
        try {
            RateLimiter::consume('synthetic-key', 2, 60);
            RateLimiter::consume('synthetic-key', 2, 60);
            $this->expectException(RateLimitException::class);
            RateLimiter::consume('synthetic-key', 2, 60);
        } finally {
            foreach (glob($directory . '/rate-limits/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory . '/rate-limits');
            rmdir($directory);
            putenv('APP_STORAGE');
            putenv('APP_SECRET');
        }
    }

    public function testUnexpectedErrorDoesNotExposeItsMessage(): void
    {
        $this->assertStringNotContainsString('private-database-password', public_error_message(new RuntimeException('private-database-password')));
        $this->assertSame('Campo obligatorio.', public_error_message(new ValidationException('Campo obligatorio.')));
    }
}
