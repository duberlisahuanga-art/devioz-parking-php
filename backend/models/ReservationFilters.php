<?php

declare(strict_types=1);

final class ReservationFilters
{
    public static function fromInput(array $input): array
    {
        $view = ($input['view'] ?? '') === 'history' ? 'history' : 'current';
        $filters = [
            'view' => $view,
            'search' => is_string($input['search'] ?? null) ? mb_substr(trim($input['search']), 0, 100) : '',
            'status' => self::choice($input['status'] ?? '', $view === 'history'
                ? ['expired', 'cancelled'] : ['confirmed', 'active']),
            'payment' => self::choice($input['payment'] ?? '', ['paid', 'pending', 'unpaid']),
            'vehicle_type' => self::choice($input['vehicle_type'] ?? '', ['car', 'moto']),
            'space_id' => filter_var(
                $input['space_id'] ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) ?: '',
            'validity' => self::choice($input['validity'] ?? '', ['future', 'current', 'expired']),
            'cancellation' => self::choice($input['cancellation'] ?? '', ['none', 'pending', 'approved', 'rejected']),
            'date_from' => '', 'date_to' => '', 'errors' => [],
            'page' => filter_var(
                $input['page'] ?? 1,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 100000]]
            ) ?: 1,
        ];
        foreach (['date_from' => 'desde', 'date_to' => 'hasta'] as $key => $label) {
            $value = $input[$key] ?? '';
            if ($value === '') {
                continue;
            }
            $date = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if (!$date || $date->format('Y-m-d') !== $value) {
                $filters['errors'][] = 'La fecha de inicio «' . $label . '» no es válida.';
            } else {
                $filters[$key] = $value;
            }
        }
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            $filters['errors'][] = 'La fecha «desde» no puede ser posterior a la fecha «hasta».';
        }
        return $filters;
    }

    public static function query(array $filters, ?string $view = null): string
    {
        if ($view !== null) {
            $filters['view'] = $view;
            $filters['page'] = 1;
        }
        $filters = self::fromInput($filters);
        unset($filters['errors']);
        if ($filters['page'] === 1) {
            unset($filters['page']);
        }
        return http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
    }

    private static function choice(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }
}
