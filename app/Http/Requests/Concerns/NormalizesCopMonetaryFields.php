<?php

namespace App\Http\Requests\Concerns;

trait NormalizesCopMonetaryFields
{
    /**
     * Normaliza un valor monetario en formato COP (ej. "25.000", "50.000", "1.250.000", "25.000,50", 25000)
     * convirtiéndolo a un número float o int estándar para persistencia y validación.
     */
    protected function normalizeCopMoney(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Si ya es un tipo numérico primitivo (int o float), retornarlo directamente
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $val = trim($value);
        $val = preg_replace('/[^\d.,]/', '', $val);

        if ($val === '') {
            return 0.0;
        }

        // Si contiene puntos y coma (ej. "25.000,50" o "1,250,000.50")
        if (str_contains($val, '.') && str_contains($val, ',')) {
            $lastDot = strrpos($val, '.');
            $lastComma = strrpos($val, ',');
            if ($lastComma > $lastDot) {
                // Formato latino: 1.250.000,50
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            } else {
                // Formato anglo: 1,250,000.50
                $val = str_replace(',', '', $val);
            }
            return is_numeric($val) ? (float) $val : $val;
        }

        // Si contiene coma y no punto (ej. "25000,50" o "25,000")
        if (str_contains($val, ',') && ! str_contains($val, '.')) {
            if (substr_count($val, ',') > 1 || preg_match('/,\d{3}$/', $val)) {
                $val = str_replace(',', '', $val);
            } else {
                $val = str_replace(',', '.', $val);
            }
            return is_numeric($val) ? (float) $val : $val;
        }

        // Si contiene puntos (ej. "25.000", "50.000", "1.250.000")
        if (str_contains($val, '.')) {
            // Si coincide con patrón de miles colombiano (ej. 25.000, 1.250.000, 500.000)
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $val)) {
                $cleaned = str_replace('.', '', $val);
                return is_numeric($cleaned) ? (float) $cleaned : $val;
            }

            // O si tiene más de un punto (ej. 1.250.000)
            if (substr_count($val, '.') > 1) {
                $cleaned = str_replace('.', '', $val);
                return is_numeric($cleaned) ? (float) $cleaned : $val;
            }

            // Un solo punto con 3 dígitos al final (ej. 25.000, 75.500)
            if (preg_match('/^\d+\.\d{3}$/', $val)) {
                $cleaned = str_replace('.', '', $val);
                return is_numeric($cleaned) ? (float) $cleaned : $val;
            }

            // Si es un número con punto decimal explícito de 1 o 2 decimales (ej. 25.5 o 25.50)
            if (preg_match('/^\d+\.\d{1,2}$/', $val)) {
                return (float) $val;
            }

            // Fallback para campos monetarios COP: remover puntos
            $cleaned = str_replace('.', '', $val);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        return is_numeric($val) ? (float) $val : $val;
    }
}
