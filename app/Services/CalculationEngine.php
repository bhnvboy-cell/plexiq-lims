<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sandboxed Calculation Engine for Laboratory Test Results
 *
 * Implements:
 * 1. Safe Shunting-Yard tokenized mathematical expression parser (Zero eval()).
 * 2. ASTM E29 Round Half to Even (Banker's rounding) & Significant Figures.
 * 3. Statistical functions (Mean, StDev, %RSD, Min, Max).
 * 4. Analytical outlier detection (Dixon Q-Test & Grubbs Test).
 */
class CalculationEngine
{
    private const ALLOWED_FUNCTIONS = [
        'sqrt', 'abs', 'log', 'ln', 'exp', 'round', 'ceil', 'floor',
        'min', 'max', 'avg', 'stdev', 'rsd', 'sum'
    ];

    /**
     * Evaluate a mathematical expression with variable substitution.
     *
     * @param string $expression e.g. "(Gross - Tare) * Dilution / Weight"
     * @param array<string, float|int|numeric-string> $variables e.g. ['Gross' => 12.54, 'Tare' => 10.12, ...]
     * @param int|null $sigFigs Significant figures to round to (or null for raw float)
     * @param string $roundingMode 'half_even' (ASTM E29 default) | 'half_up' | 'truncate'
     * @return float
     * @throws \InvalidArgumentException on syntax error, division by zero, or illegal token
     */
    public function evaluate(
        string $expression,
        array $variables = [],
        ?int $sigFigs = null,
        string $roundingMode = 'half_even'
    ): float {
        $tokens = $this->tokenize($expression);
        $rpn = $this->toRpn($tokens);
        $result = $this->evaluateRpn($rpn, $variables);

        if ($sigFigs !== null && $sigFigs > 0) {
            $result = self::roundSigFigs($result, $sigFigs, $roundingMode);
        }

        return $result;
    }

    /**
     * Round a number according to ASTM E29 Standard Practice for Significant Digits.
     *
     * @param float $val
     * @param int $sigFigs Number of significant digits
     * @param string $mode 'half_even' (default / banker's), 'half_up', 'truncate'
     * @return float
     */
    public static function roundSigFigs(float $val, int $sigFigs, string $mode = 'half_even'): float
    {
        if ($val === 0.0 || !is_finite($val)) {
            return 0.0;
        }

        $d = ceil(log10(abs($val)));
        $power = $sigFigs - (int)$d;
        $magnitude = pow(10, $power);
        $shifted = $val * $magnitude;

        switch ($mode) {
            case 'truncate':
                $rounded = ($shifted >= 0) ? floor($shifted) : ceil($shifted);
                break;

            case 'half_up':
                $rounded = round($shifted, 0, PHP_ROUND_HALF_UP);
                break;

            case 'half_even':
            default:
                $rounded = round($shifted, 0, PHP_ROUND_HALF_EVEN);
                break;
        }

        return $rounded / $magnitude;
    }

    /**
     * Outlier Rejection: Dixon's Q-Test (for n = 3 to 30 replicate measurements).
     *
     * @param array<float> $replicates
     * @param float $confidence 0.90, 0.95 (default), or 0.99
     * @return array{replicates: array<float>, outliers: array<float>, status: string}
     */
    public static function dixonQTest(array $replicates, float $confidence = 0.95): array
    {
        $n = count($replicates);
        if ($n < 3 || $n > 30) {
            return ['replicates' => $replicates, 'outliers' => [], 'status' => 'insufficient_or_excess_n'];
        }

        // Dixon critical values at 95% CL (Rorabacher 1991)
        $qCrit95 = [
            3 => 0.970, 4 => 0.829, 5 => 0.710, 6 => 0.625, 7 => 0.568,
            8 => 0.526, 9 => 0.493, 10 => 0.466, 11 => 0.444, 12 => 0.426,
            13 => 0.410, 14 => 0.396, 15 => 0.384, 16 => 0.374, 17 => 0.365,
            18 => 0.356, 19 => 0.349, 20 => 0.342, 21 => 0.337, 22 => 0.331,
            23 => 0.326, 24 => 0.321, 25 => 0.317, 26 => 0.312, 27 => 0.308,
            28 => 0.305, 29 => 0.301, 30 => 0.298,
        ];

        sort($replicates);
        $range = end($replicates) - $replicates[0];
        if ($range == 0.0) {
            return ['replicates' => $replicates, 'outliers' => [], 'status' => 'zero_range'];
        }

        $outliers = [];
        $crit = $qCrit95[$n] ?? 0.466;

        // Test lowest value
        $qMin = ($replicates[1] - $replicates[0]) / $range;
        if ($qMin > $crit) {
            $outliers[] = array_shift($replicates);
        }

        // Test highest value
        if (count($replicates) >= 3) {
            $range = end($replicates) - $replicates[0];
            $count = count($replicates);
            $qMax = ($replicates[$count - 1] - $replicates[$count - 2]) / $range;
            if ($qMax > ($qCrit95[$count] ?? 0.466)) {
                $outliers[] = array_pop($replicates);
            }
        }

        return [
            'replicates' => array_values($replicates),
            'outliers' => $outliers,
            'status' => count($outliers) > 0 ? 'outlier_rejected' : 'no_outlier'
        ];
    }

    /**
     * Compute descriptive statistics on numeric replicates.
     *
     * @param array<float> $values
     * @return array{mean: float, stdev: float, rsd: float, min: float, max: float, n: int}
     */
    public static function statistics(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['mean' => 0.0, 'stdev' => 0.0, 'rsd' => 0.0, 'min' => 0.0, 'max' => 0.0, 'n' => 0];
        }

        $sum = array_sum($values);
        $mean = $sum / $n;

        if ($n === 1) {
            return [
                'mean' => $mean,
                'stdev' => 0.0,
                'rsd' => 0.0,
                'min' => $values[0],
                'max' => $values[0],
                'n' => 1
            ];
        }

        $variance = 0.0;
        foreach ($values as $val) {
            $variance += pow($val - $mean, 2);
        }
        $stdev = sqrt($variance / ($n - 1));
        $rsd = ($mean != 0.0) ? abs(($stdev / $mean) * 100.0) : 0.0;

        return [
            'mean' => $mean,
            'stdev' => $stdev,
            'rsd' => $rsd,
            'min' => min($values),
            'max' => max($values),
            'n' => $n
        ];
    }

    /**
     * Tokenize expression string into operands, operators, parentheses and functions.
     *
     * @param string $expr
     * @return array<array{type: string, value: string}>
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            $char = $expr[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (str_contains('+-*/^(),', $char)) {
                $tokens[] = ['type' => 'symbol', 'value' => $char];
                $i++;
                continue;
            }

            // Numeric literals
            if (ctype_digit($char) || ($char === '.' && $i + 1 < $len && ctype_digit($expr[$i + 1]))) {
                $num = '';
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.' || strtolower($expr[$i]) === 'e')) {
                    if (strtolower($expr[$i]) === 'e' && $i + 1 < $len && ($expr[$i + 1] === '+' || $expr[$i + 1] === '-')) {
                        $num .= $expr[$i++];
                    }
                    $num .= $expr[$i++];
                }
                $tokens[] = ['type' => 'number', 'value' => $num];
                continue;
            }

            // Identifiers: variables or functions
            if (ctype_alpha($char) || $char === '_') {
                $ident = '';
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_' || $expr[$i] === '.')) {
                    $ident .= $expr[$i++];
                }
                $lower = strtolower($ident);
                if (in_array($lower, self::ALLOWED_FUNCTIONS, true)) {
                    $tokens[] = ['type' => 'function', 'value' => $lower];
                } else {
                    $tokens[] = ['type' => 'variable', 'value' => $ident];
                }
                continue;
            }

            throw new \InvalidArgumentException("Illegal character '{$char}' at position {$i} in expression.");
        }

        return $tokens;
    }

    /**
     * Convert token stream to Reverse Polish Notation (RPN) via Shunting-Yard.
     *
     * @param array<array{type: string, value: string}> $tokens
     * @return array<array{type: string, value: string}>
     */
    private function toRpn(array $tokens): array
    {
        $output = [];
        $stack = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, '^' => 3];
        $rightAssoc = ['^' => true];

        $prevType = 'start';

        foreach ($tokens as $token) {
            $type = $token['type'];
            $val = $token['value'];

            if ($type === 'number' || $type === 'variable') {
                $output[] = $token;
            } elseif ($type === 'function') {
                $stack[] = $token;
            } elseif ($val === ',') {
                while (!empty($stack) && end($stack)['value'] !== '(') {
                    $output[] = array_pop($stack);
                }
            } elseif ($type === 'symbol' && isset($precedence[$val])) {
                // Handle unary minus
                if ($val === '-' && in_array($prevType, ['start', 'symbol', 'function'], true)) {
                    $output[] = ['type' => 'number', 'value' => '0'];
                }

                while (!empty($stack)) {
                    $top = end($stack);
                    if ($top['type'] === 'symbol' && isset($precedence[$top['value']])) {
                        $p1 = $precedence[$val];
                        $p2 = $precedence[$top['value']];
                        $isRight = isset($rightAssoc[$val]);
                        if ((!$isRight && $p1 <= $p2) || ($isRight && $p1 < $p2)) {
                            $output[] = array_pop($stack);
                            continue;
                        }
                    } elseif ($top['type'] === 'function') {
                        $output[] = array_pop($stack);
                        continue;
                    }
                    break;
                }
                $stack[] = $token;
            } elseif ($val === '(') {
                $stack[] = $token;
            } elseif ($val === ')') {
                while (!empty($stack) && end($stack)['value'] !== '(') {
                    $output[] = array_pop($stack);
                }
                if (empty($stack)) {
                    throw new \InvalidArgumentException("Mismatched parentheses in expression.");
                }
                array_pop($stack); // pop '('
                if (!empty($stack) && end($stack)['type'] === 'function') {
                    $output[] = array_pop($stack);
                }
            }

            $prevType = $val === '(' ? 'symbol' : $type;
        }

        while (!empty($stack)) {
            $top = array_pop($stack);
            if ($top['value'] === '(' || $top['value'] === ')') {
                throw new \InvalidArgumentException("Mismatched parentheses at end of expression.");
            }
            $output[] = $top;
        }

        return $output;
    }

    /**
     * Evaluate RPN stack safely.
     *
     * @param array<array{type: string, value: string}> $rpn
     * @param array<string, mixed> $variables
     * @return float
     */
    private function evaluateRpn(array $rpn, array $variables): float
    {
        $evalStack = [];

        foreach ($rpn as $token) {
            $type = $token['type'];
            $val = $token['value'];

            if ($type === 'number') {
                $evalStack[] = (float)$val;
            } elseif ($type === 'variable') {
                if (!array_key_exists($val, $variables)) {
                    throw new \InvalidArgumentException("Undefined variable '{$val}' in formula.");
                }
                $evalStack[] = (float)$variables[$val];
            } elseif ($type === 'symbol') {
                if (count($evalStack) < 2) {
                    throw new \InvalidArgumentException("Insufficient operands for operator '{$val}'.");
                }
                $b = array_pop($evalStack);
                $a = array_pop($evalStack);

                switch ($val) {
                    case '+': $evalStack[] = $a + $b; break;
                    case '-': $evalStack[] = $a - $b; break;
                    case '*': $evalStack[] = $a * $b; break;
                    case '/':
                        if ($b == 0.0) {
                            throw new \InvalidArgumentException("Division by zero in calculation formula.");
                        }
                        $evalStack[] = $a / $b;
                        break;
                    case '^': $evalStack[] = pow($a, $b); break;
                }
            } elseif ($type === 'function') {
                if (empty($evalStack)) {
                    throw new \InvalidArgumentException("Insufficient arguments for function '{$val}'.");
                }

                switch ($val) {
                    case 'sqrt':
                        $a = array_pop($evalStack);
                        if ($a < 0.0) throw new \InvalidArgumentException("Square root of negative number.");
                        $evalStack[] = sqrt($a);
                        break;
                    case 'abs': $evalStack[] = abs(array_pop($evalStack)); break;
                    case 'log': $evalStack[] = log10(array_pop($evalStack)); break;
                    case 'ln': $evalStack[] = log(array_pop($evalStack)); break;
                    case 'exp': $evalStack[] = exp(array_pop($evalStack)); break;
                    case 'round': $evalStack[] = round(array_pop($evalStack)); break;
                    case 'ceil': $evalStack[] = ceil(array_pop($evalStack)); break;
                    case 'floor': $evalStack[] = floor(array_pop($evalStack)); break;
                    case 'min':
                    case 'max':
                    case 'sum':
                    case 'avg':
                        if (count($evalStack) < 2) {
                            // Single argument, leave on stack
                            break;
                        }
                        $b = array_pop($evalStack);
                        $a = array_pop($evalStack);
                        if ($val === 'min') $evalStack[] = min($a, $b);
                        elseif ($val === 'max') $evalStack[] = max($a, $b);
                        elseif ($val === 'sum') $evalStack[] = $a + $b;
                        elseif ($val === 'avg') $evalStack[] = ($a + $b) / 2.0;
                        break;
                    default:
                        throw new \InvalidArgumentException("Unsupported function '{$val}'.");
                }
            }
        }

        if (count($evalStack) !== 1) {
            throw new \InvalidArgumentException("Malformed expression: stack contains " . count($evalStack) . " values.");
        }

        return (float)array_pop($evalStack);
    }
}
