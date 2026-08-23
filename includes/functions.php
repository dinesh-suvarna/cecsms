<?php
function notify(string $type, string $msg){
    $_SESSION['notify_type'] = $type; // success, danger, warning, info
    $_SESSION['notify_msg']  = $msg;
}

if (!function_exists('inr')) {
    /**
     * Universal Indian Number Formatter for StockFlow
     * Automatically handles whole numbers, floats, counts, and currency.
     */
    function inr(int|float|string|null $amount, bool $is_currency = false): string {
        if ($amount === null || $amount === '') {
            return $is_currency ? '₹0.00' : '0';
        }

        $val = (float)$amount;
        
        // Auto-detect decimal places: 2 for currency or floats, 0 for integers
        $decimals = $is_currency ? 2 : ((floor($val) == $val) ? 0 : 2);

        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format($val);
        } else {
            // Fallback standard calculation
            $parts = explode('.', number_format(abs($val), $decimals, '.', ''));
            $intPart = $parts[0];
            $decPart = isset($parts[1]) ? '.' . $parts[1] : '';

            if (strlen($intPart) > 3) {
                $lastThree = substr($intPart, -3);
                $rest = substr($intPart, 0, -3);
                $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
                $intPart = $rest . ',' . $lastThree;
            }
            $formatted = ($val < 0 ? '-' : '') . $intPart . $decPart;
        }

        return $is_currency ? '₹' . $formatted : $formatted;
    }
}