<?php namespace \Geolocation\Calc;
/**
 *	baut auf phpGeo auf
 *  @see https://github.com/mjaschen/phpgeo
 *  @see https://github.com/mjaschen/phpgeo/tree/master/docs
 *
 *	Die Klasse implementiert einen modifizierten DMS-Formatter. Die Sekunden-Nachkommastellen
 *	werden nun auch ausgegeben.
 *
 * @package geolocation
 */

#declare(strict_types=1); // weil es auch im Original so ist

use Location\Coordinate;
use \Geolocation\Calc\Math\Math;

/**
 * Coordinate Formatter "DMS"
 *
 * @author Marcus Jaschen <mjaschen@gmail.com>
 */
class DMS2 extends DMS
{
    /**
     * @var int
     */
    protected $digits = 3;

    /**
     * @var string
     */
    protected $decimalPoint = '.';

    /**
     * @throws InvalidArgumentException     stammt aus dem Originalcode. Ist das wirklich so?
     */
    public function __construct(
        string $separator = ' ',
        bool $useCardinalLetters = true,
        string $unitType = self::UNITS_UTF8
    ) {
        $this->separator = $separator;
        $this->useCardinalLetters = $useCardinalLetters;
        $this->unitType = $unitType;
    }

    /**
     *   Setzt die Anzahl Dezimalstellen der Sekunden auf den angegebenen Wert
     *
     *   @throws InvalidArgumentException     wenn negative Anzahl Nachkommastellen angegeben wurden
     *
     *   @param int
     *   @return Location\Formatter\Coordinate\DMS2
     */
    public function setDigits( ?int $digits = null ): self
    {
        if( null !== $digits && 0 > $digits ) {
            throw new InvalidArgumentException('Invalid number of decimal places (negative value "'.$digits.' given")', 1);
        }
        $this->digits = null === $digits ? $digits : max(0,$digits);
        return $this;
    }

    /**
     *   Setzt den Dezimalpunkt. Im Normalfall solte es beim Punkt bleiben.
     *
     *   @throws InvalidArgumentException     wenn ein leerer String angegeben wurde
     *
     *   @param string $decimalPoint
     *   @return Location\Formatter\Coordinate\DMS2
     */
    public function setDecimalPoint(string $decimalPoint): self
    {
        if( 0 === strlen($decimalPoint) ) {
            throw new InvalidArgumentException('Replacement for decimal-point expected to be at least 1 charcter long; empty string given.', 1);
        }
        return $this;
    }

    /**
     * @param Coordinate $coordinate
     *
     * @return string
     */
    public function format(Coordinate $coordinate): string
    {
        $dmsLat = Math::dd2dms( $coordinate->getLat(), $this->digits );
        $dmsLng = Math::dd2dms( $coordinate->getLng(), $this->digits );

        if( $this->digits ) {
            $pattern = '%s%02d%s %02d%s %02.'.$this->digits.'f%s%s%s%s%03d%s %02d%s %02.'.$this->digits.'f%s%s';
        } else {
            $pattern = '%s%02d%s %02d%s %02d%s%s%s%s%03d%s %02d%s %02d%s%s';
        }

        return sprintf(
            $pattern,
            $this->getLatPrefix($dmsLat['degree']),
            abs($dmsLat['degree']),
            $this->units[$this->unitType]['deg'],
            $dmsLat['minute'],
            $this->units[$this->unitType]['min'],
            $dmsLat['second'],
            $this->units[$this->unitType]['sec'],
            $this->getLatSuffix($dmsLat['degree']),
            $this->separator,
            $this->getLngPrefix($dmsLng['degree']),
            abs($dmsLng['degree']),
            $this->units[$this->unitType]['deg'],
            $dmsLng['minute'],
            $this->units[$this->unitType]['min'],
            $dmsLng['second'],
            $this->units[$this->unitType]['sec'],
            $this->getLngSuffix($dmsLng['degree'])
        );
    }

}
