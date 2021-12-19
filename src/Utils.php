<?php

namespace Beycan\MultiChain;

use \Web3\Utils as Web3Utils;
use phpseclib\Math\BigInteger as BigNumber;

final class Utils
{
    /**
     * @param string $value
     * @return string
     */
    public static function hex($value) : string
    {
        return '0x' . dechex($value);
    }

    /**
     * Converts the regular number into a format that blockchain networks will understand
     * Decimal number to hexadecimal number
     * @param float|int $amount
     * @param int $decimals
     * @return string
     */
    public static function toHex(float $amount, int $decimals) : string
    {
        $value = self::toBigNumber($amount, $decimals);
        if (is_numeric($value)) {
            // turn to hex number
            $bn = self::toBn($value);
            $hex = $bn->toHex(true);
            $hex = preg_replace('/^0+(?!$)/', '', $hex);
        } elseif (is_string($value)) {
            $value = self::stripZero($value);
            $hex = implode('', unpack('H*', $value));
        } elseif ($value instanceof BigNumber) {
            $hex = $value->toHex(true);
            $hex = preg_replace('/^0+(?!$)/', '', $hex);
        } else {
            throw new InvalidArgumentException('The value to toHex function is not support.');
        }

        return '0x' . $hex;
    }

    /**
     * @param BigNumber|string|int $number
     * @param int $decimals
     * @return BigNumber
     */
    public static function toBigNumber($number, int $decimals) : BigNumber
    {
        $bn = Web3Utils::toBn($number);
        $length = '1' . str_repeat('0', $decimals);

        $bnt = new BigNumber($length);

        if (is_array($bn)) {
            list($whole, $fraction, $fractionLength, $negative1) = $bn;

            if ($fractionLength > strlen($length)) {
                throw new InvalidArgumentException('toBigNumber fraction part is out of limit.');
            }
            $whole = $whole->multiply($bnt);

            switch (MATH_BIGINTEGER_MODE) {
                case $whole::MODE_GMP:
                    static $two;
                    $powerBase = gmp_pow(gmp_init(10), (int) $fractionLength);
                    break;
                case $whole::MODE_BCMATH:
                    $powerBase = bcpow('10', (string) $fractionLength, 0);
                    break;
                default:
                    $powerBase = pow(10, (int) $fractionLength);
                    break;
            }
            $base = new BigNumber($powerBase);
            $fraction = $fraction->multiply($bnt)->divide($base)[0];

            if ($negative1 !== false) {
                return $whole->add($fraction)->multiply($negative1);
            }
            return $whole->add($fraction);
        }

        return $bn->multiply($bnt);
    }

    /**
     * Converts a hexadecimal number to a normal number
     * Hecadecimal number to decimal number
     * @param string|int|float $amount
     * @param int $decimals
     * @return float
     */
    public static function toDec(string $amount, int $decimals) : float
    {
        $bn = Web3Utils::toBn($amount);
        $length = '1' . str_repeat('0', $decimals);
        $bnt = new BigNumber($length);

        if ($bn->divide($bnt)[0]->toString() != 0) {
            return (float) $bn->divide($bnt)[0]->toString();
        } else {
            $amount = $bn->divide($bnt)[1]->toString();
            $length = '1' . str_repeat('0', $decimals);
            return (float) bcdiv($amount, $length, $decimals);
        }
    }

    /**
     * @param string $amount
     * @param integer $decimals
     * @return string
     */
    public static function toNumber(string $amount, int $decimals) : string
    {
        $pos = stripos((string) $amount, 'E-');
    
        if ($pos !== false) {
            $amount = number_format($amount, $decimals, '.', ',');
        }
    
        return rtrim($amount, '0');
    }

}