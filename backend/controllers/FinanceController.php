<?php

declare(strict_types=1);

final class FinanceController
{
    public static function filters(array $input): array
    {
        $month = $input['month'] ?? (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m');
        if (!is_string($month) || !preg_match('/^(?:[1-9][0-9]{3})-(?:0[1-9]|1[0-2])$/D', $month)) {
            throw new InvalidArgumentException('Mes inválido. Usa YYYY-MM.');
        }
        $start = new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone('America/Lima'));
        $type = $input['type'] ?? '';
        $category = $input['category'] ?? '';
        if (!in_array($type, ['', 'income', 'expense'], true) || !in_array($category, ['', ...Expense::CATEGORIES], true)) {
            throw new InvalidArgumentException('Filtro inválido.');
        }
        if (isset($input['search']) && !is_string($input['search'])) throw new InvalidArgumentException('Búsqueda inválida.');
        return ['month' => $month, 'type' => $type, 'category' => $category,
            'search' => clean_string($input['search'] ?? '', 150),
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $start->modify('+1 month')->format('Y-m-d H:i:s')];
    }

    public static function overview(array $filters = []): array
    {
        require_admin();
        $filters = self::filters($filters);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $previous = (float) Payment::financeSummary(null, $filters['start'])['total'] - Expense::totalForPeriod(null, $filters['start']);
            $income = Payment::financeSummary($filters['start'], $filters['end']);
            $expenses = Expense::totalForPeriod($filters['start'], $filters['end']);
            $categories = Expense::totalsByCategory($filters['start'], $filters['end']);
            $movements = [];
            if ($filters['type'] !== 'expense' && $filters['category'] === '') {
                foreach (Payment::financePayments($filters) as $p) {
                    $movements[] = [...$p, 'type' => 'income', 'datetime' => $p['finance_datetime'],
                        'category' => 'Pago mensual', 'concept' => 'Reserva #' . $p['reservation_id'],
                        'description' => 'Pago aprobado', 'payment_method' => ucfirst($p['method']), 'reference_id' => $p['id']];
                }
            }
            if ($filters['type'] !== 'income') {
                foreach (Expense::all($filters) as $e) {
                    $movements[] = [...$e, 'type' => 'expense', 'datetime' => $e['expense_datetime'],
                        'user_name' => $e['created_by_name'], 'reference_id' => $e['id']];
                }
            }
            usort($movements, static fn(array $a, array $b): int => strcmp($b['datetime'], $a['datetime']) ?: strcmp($a['type'], $b['type']) ?: ((int) $b['reference_id'] <=> (int) $a['reference_id']));
            $pdo->commit();
            return ['filters' => $filters, 'previous' => $previous, 'income' => (float) $income['total'],
                'income_count' => (int) $income['count'], 'expenses' => $expenses,
                'available' => $previous + (float) $income['total'] - $expenses,
                'categories' => $categories, 'movements' => $movements];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function getExpense(int $id): ?array
    {
        require_admin();
        return Expense::find($id);
    }

    private static function validate(array $data): array
    {
        $result = [];
        foreach (['category' => 30, 'concept' => 255, 'description' => 10000, 'payment_method' => 30, 'other_detail' => 1000] as $key => $max) {
            if (isset($data[$key]) && !is_string($data[$key])) throw new InvalidArgumentException('Campo inválido: ' . $key);
            $value = trim($data[$key] ?? '');
            if (mb_strlen($value) > $max) throw new InvalidArgumentException('Campo demasiado largo: ' . $key);
            $result[$key] = $value;
        }
        if (!in_array($result['category'], Expense::CATEGORIES, true)) throw new InvalidArgumentException('Categoría no permitida.');
        if (!in_array($result['payment_method'], Expense::METHODS, true)) throw new InvalidArgumentException('Método no permitido.');
        if ($result['concept'] === '' || $result['description'] === '') throw new InvalidArgumentException('Concepto y descripción son obligatorios.');
        if ($result['category'] === 'Otros' && $result['other_detail'] === '') throw new InvalidArgumentException('Especifica el detalle de Otros.');
        if ($result['category'] !== 'Otros') $result['other_detail'] = null;
        $amount = $data['amount'] ?? '';
        if (!is_string($amount) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount) || (float) $amount <= 0) throw new InvalidArgumentException('Monto inválido; debe ser mayor que cero y tener hasta dos decimales.');
        $result['amount'] = number_format((float) $amount, 2, '.', '');
        $date = $data['expense_datetime'] ?? '';
        if (!is_string($date)) throw new InvalidArgumentException('Fecha inválida.');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date, new DateTimeZone('America/Lima'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $date || (int) $parsed->format('Y') < 1000) throw new InvalidArgumentException('Fecha y hora inválidas.');
        $result['expense_datetime'] = $parsed->format('Y-m-d H:i:s');
        return $result;
    }

    private static function upload(array $files): ?string
    {
        $file = $files['voucher'] ?? null;
        if ($file === null || (is_array($file) && ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE)) return null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir el comprobante. Revisa el límite de subida del servidor.');
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('Comprobante inválido.');
        $size = filesize($tmp);
        if ($size === false || $size <= 0 || $size > 5 * 1024 * 1024) throw new RuntimeException('El comprobante debe tener como máximo 5 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) throw new RuntimeException('Solo se permiten JPG, PNG, WEBP o PDF.');
        $dir = __DIR__ . '/../uploads/egresos';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear la carpeta de comprobantes.');
        $name = 'egreso_' . time() . '_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) throw new RuntimeException('No se pudo guardar el comprobante.');
        return 'backend/uploads/egresos/' . $name;
    }

    public static function createExpense(array $data, array $files = []): void
    {
        self::save(null, $data, $files);
    }

    public static function updateExpense(int $id, array $data, array $files = []): void
    {
        self::save($id, $data, $files);
    }

    private static function save(?int $id, array $data, array $files): void
    {
        $user = require_admin();
        verify_csrf();
        $values = self::validate($data);
        $pdo = db();
        $newPath = null;
        $pdo->beginTransaction();
        try {
            $old = null;
            if ($id !== null) {
                $lock = $pdo->prepare('SELECT id FROM expenses WHERE id=:id FOR UPDATE');
                $lock->execute(['id' => $id]);
                if (!$lock->fetch()) throw new RuntimeException('El egreso no existe.');
                $old = Expense::find($id);
            }
            $newPath = self::upload($files);
            $values['voucher_path'] = $newPath ?? ($old['voucher_path'] ?? null);
            if ($id === null) {
                $id = Expense::create([...$values, 'created_by' => (int) $user['id']]);
                $action = 'Egreso creado';
            } else {
                Expense::update($id, $values);
                $action = 'Egreso actualizado';
            }
            AuditService::log('Finanzas', $action, "EG-{$id}: {$values['category']} / {$values['concept']} / S/ {$values['amount']}", 'Expense', $id, "EG-{$id}", ['before' => $old, 'after' => $values]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($newPath !== null) @unlink(__DIR__ . '/../../' . $newPath);
            throw $e;
        }
        // Se conserva el comprobante anterior para mantener la trazabilidad.
    }

    public static function voucherUrl(?string $path): ?string
    {
        if (!$path || !preg_match('#^backend/uploads/(?:egresos|comprobantes)/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp|pdf)$#D', $path)) return null;
        return app_url('/' . $path);
    }
}