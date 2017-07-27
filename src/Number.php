<?php
/**
 * This file is part of the PrestaShop\Decimal package
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace PrestaShop\Decimal;

use PrestaShop\Decimal\Operation\Rounding;

/**
 * Decimal number.
 *
 * Allows for arbitrary precision math operations.
 */
class Number
{

    /**
     * Indicates if the number is negative
     * @var bool
     */
    private $isNegative = false;

    /**
     * Integer representation of this number
     * @var string
     */
    private $coefficient = '';

    /**
     * Scientific notation exponent. For practical reasons, it's always stored as a positive value.
     * @var int
     */
    private $exponent = 0;

    /**
     * Number constructor.
     *
     * This constructor can be used in two ways:
     *
     * 1) With a number string:
     *
     * ```php
     * (string) new Number('0.123456'); // -> '0.123456'
     * ```
     *
     * 2) With an integer string as coefficient and an exponent
     *
     * ```php
     * // 123456 * 10^(-6)
     * (string) new Number('123456', 6); // -> '0.123456'
     * ```
     *
     * Note: exponents are always positive.
     *
     * @param string $number Number or coefficient
     * @param int $exponent [default=null] If provided, the number is considered a coefficient of
     * the scientific notation.
     */
    public function __construct($number, $exponent = null)
    {
        if (!is_string($number)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid type - expected string, but got (%s) "%s"', gettype($number), print_r($number, true))
            );
        }

        if (null === $exponent) {
            $this->initFromString($number);
        } else {
            $this->initFromScientificNotation($number, $exponent);
        }

        if ('0' === $this->coefficient) {
            // make sure the sign is always positive for zero
            $this->isNegative = false;
        }
    }

    /**
     * Returns the integer part of the number.
     * Note that this does NOT include the sign.
     *
     * @return string
     */
    public function getIntegerPart()
    {
        if ('0' === $this->coefficient) {
            return $this->coefficient;
        }

        if (0 === $this->exponent) {
            return $this->coefficient;
        }

        if ($this->exponent >= strlen($this->coefficient)) {
            return '0';
        }

        return substr($this->coefficient, 0, -$this->exponent);
    }

    /**
     * Returns the fractional part of the number.
     * Note that this does NOT include the sign.
     * @return string
     */
    public function getFractionalPart()
    {
        if (0 === $this->exponent || '0' === $this->coefficient) {
            return '0';
        }

        if ($this->exponent > strlen($this->coefficient)) {
            return str_pad($this->coefficient, $this->exponent, '0', STR_PAD_LEFT);
        }

        return substr($this->coefficient, -$this->exponent);
    }

    /**
     * Returns the number of digits in the fractional part.
     *
     * @see self::getExponent() This method is an alias of getExponent().
     *
     * @return int
     */
    public function getPrecision()
    {
        return $this->exponent;
    }

    /**
     * Returns the number's sign.
     * Note that this method will return an empty string if the number is positive!
     *
     * @return string '-' if negative, empty string if positive
     */
    public function getSign()
    {
        return $this->isNegative ? '-' : '';
    }

    /**
     * Returns the exponent of this number. For practical reasons, this exponent is always >= 0.
     *
     * This value can also be interpreted as the number of significant digits on the decimal part.
     *
     * @return int
     */
    public function getExponent()
    {
        return $this->exponent;
    }

    /**
     * Returns the raw number as stored internally. This coefficient is always an integer.
     *
     * It can be transformed to float by computing:
     * ```
     * getCoefficient() * 10^(-getExponent())
     * ```
     *
     * @return string
     */
    public function getCoefficient()
    {
        return $this->coefficient;
    }

    /**
     * Returns a string representation of this object
     * @return string
     */
    public function __toString()
    {
        $output = $this->getSign() . $this->getIntegerPart();

        $fractionalPart = $this->getFractionalPart();

        if ('0' !== $fractionalPart) {
            $output .= '.' . $fractionalPart;
        }

        return $output;
    }

    /**
     * Returns the number as a string, rounded to a specified precision
     * @param $precision
     * @param string $roundingMode
     *
     * @return string
     */
    public function toPrecision($precision, $roundingMode = Rounding::ROUND_TRUNCATE)
    {
        if ($precision > $this->getPrecision()) {
            return (
                $this->getSign()
                . $this->getIntegerPart()
                . '.'
                . str_pad($this->getFractionalPart(), $precision, '0')
            );
        }

        return (new Operation\Rounding())->compute($this, $precision, $roundingMode);
    }

    /**
     * Returns this number as a positive number
     *
     * @return self
     */
    public function toPositive()
    {
        if (!$this->isNegative) {
            return $this;
        }

        return $this->invert();
    }

    /**
     * Returns this number as a negative number
     *
     * @return self
     */
    public function toNegative()
    {
        if ($this->isNegative) {
            return $this;
        }

        return $this->invert();
    }

    /**
     * Returns the computed result of adding another number to this one
     *
     * @param self $addend Number to add
     *
     * @return self
     */
    public function plus(self $addend)
    {
        return (new Operation\Addition())->compute($this, $addend);
    }

    /**
     * Returns the computed result of subtracting another number to this one
     *
     * @param self $subtrahend Number to subtract
     *
     * @return self
     */
    public function minus(self $subtrahend)
    {
        return (new Operation\Subtraction())->compute($this, $subtrahend);
    }

    /**
     * Indicates if this number is greater than the provided one
     *
     * @param self $number
     *
     * @return bool
     */
    public function isGreaterThan(self $number)
    {
        return (1 === (new Operation\Comparison())->compare($this, $number));
    }

    /**
     * Indicates if this number is greater than the provided one
     *
     * @param self $number
     *
     * @return bool
     */
    public function isLowerThan(self $number)
    {
        return (-1 === (new Operation\Comparison())->compare($this, $number));
    }

    /**
     * Indicates if this number is positive
     *
     * @return bool
     */
    public function isPositive()
    {
        return !$this->isNegative;
    }

    /**
     * Indicates if this number is negative
     *
     * @return bool
     */
    public function isNegative()
    {
        return $this->isNegative;
    }

    /**
     * Indicates if this number equals another one
     *
     * @param self $number
     *
     * @return bool
     */
    public function equals(self $number)
    {
        return (
            $this->isNegative === $number->isNegative
            && $this->coefficient === $number->getCoefficient()
            && $this->exponent === $number->getExponent()
        );
    }

    /**
     * Returns the additive inverse of this number (that is, N * -1).
     *
     * @return static
     */
    public function invert()
    {
        // invert sign
        $sign = $this->isNegative ? '' : '-';

        return new static($sign . $this->getCoefficient(), $this->getExponent());
    }

    /**
     * Initializes the number using a string
     *
     * @param string $number
     */
    private function initFromString($number)
    {
        if (!preg_match("/^(?<sign>[-+])?(?<integerPart>\d+)(?:\.(?<fractionalPart>\d+))?$/", $number, $parts)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" cannot be interpreted as a number', $number)
            );
        }

        $this->isNegative = ('-' === $parts['sign']);

        // extract the integer part and remove leading zeroes and plus sign
        $integerPart = ltrim($parts['integerPart'], '0');

        $fractionalPart = '';
        if (array_key_exists('fractionalPart', $parts)) {
            // extract the fractional part and remove trailing zeroes
            $fractionalPart = rtrim($parts['fractionalPart'], '0');
        }

        $this->exponent = strlen($fractionalPart);
        $this->coefficient = $integerPart . $fractionalPart;

        // when coefficient is '0' or a sequence of '0'
        if ('' === $this->coefficient) {
            $this->coefficient = '0';
        }
    }

    /**
     * Initializes the number using a coefficient and exponent
     *
     * @param string $coefficient
     * @param int $exponent
     */
    private function initFromScientificNotation($coefficient, $exponent)
    {
        if ($exponent < 0) {
            throw new \InvalidArgumentException(
                sprintf('Invalid value for exponent. Expected a positive integer or 0, but got "%s"', $coefficient)
            );
        }

        if (!preg_match("/^(?<sign>[-+])?(?<integerPart>\d+)$/", $coefficient, $parts)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" cannot be interpreted as a number', $coefficient)
            );
        }

        $this->isNegative = ('-' === $parts['sign']);
        $this->exponent = (int) $exponent;
        // trim leading zeroes
        $this->coefficient = ltrim($parts['integerPart'], '0');

        // when coefficient is '0' or a sequence of '0'
        if ('' === $this->coefficient) {
            $this->exponent = 0;
            $this->coefficient = '0';
        }
    }
}