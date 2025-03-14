<?php

/**
 * Die Klasse implementiert einen modifizierten DMS-Formatter.
 * - Dezimalpunkt änderbar
 * - Die Sekunden-Nachkommastellen in beliebiger Genauingkeit ausgeben.
 *
 * baut auf phpGeo auf.
 * @see https://github.com/mjaschen/phpgeo
 * @see https://github.com/mjaschen/phpgeo/tree/master/docs
 */

namespace FriendsOfRedaxo\Geolocation\Calc;

use FriendsOfRedaxo\Geolocation\InvalidParameter;
use Location\Coordinate;
use Location\Formatter\Coordinate\DMS;

use function sprintf;

/**
 * Coordinate Formatter "DMS".
 *
 * @author Marcus Jaschen <mjaschen@gmail.com>
 */
class DMS2 extends DMS
{
    protected int $defDigits = 3;
    protected int $digits = 3;

    protected string $defDecimalPoint = '.';
    protected string $decimalPoint = '.';

    /**
     * Setzt die Anzahl Dezimalstellen der Sekunden auf den angegebenen Wert.
     * "null" setzt zurück auf den Default-Wert 3.
     *
     * @api
     * @throws InvalidParameter     wenn negative Anzahl Nachkommastellen angegeben wurden
     */
    public function setDigits(?int $digits = null): self
    {
        $digits ??= $this->defDigits;
        if (0 > $digits) {
            throw new InvalidParameter(InvalidParameter::DMS_DIGITS, [$digits]);
        }
        $this->digits = $digits;
        return $this;
    }

    /**
     * Setzt den Dezimalpunkt. Im Normalfall sollte es beim Punkt bleiben.
     * "null" setzt zurück auf den Default-Wert ".".
     *
     * @api
     * @throws InvalidParameter     wenn ein leerer String angegeben wurde
     */
    public function setDecimalPoint(?string $decimalPoint = null): self
    {
        $decimalPoint ??= $this->defDecimalPoint;
        $decimalPoint = trim($decimalPoint);

        if ('' === $decimalPoint) {
            throw new InvalidParameter(InvalidParameter::DMS_DECIMALPOINT);
        }
        $this->decimalPoint = $decimalPoint;
        return $this;
    }

    /**
     * Formatiert die Koordinate.
     *
     * @api
     * @throws InvalidParameter     wenn ein leerer String angegeben wurde
     */
    public function format(Coordinate $coordinate): string
    {
        $dmsLat = Math::dd2dms($coordinate->getLat(), $this->digits);
        $dmsLng = Math::dd2dms($coordinate->getLng(), $this->digits);

        if (0 < $this->digits) {
            $pattern = '%s%02d%s %02d%s %02.' . $this->digits . 'f%s%s%s%s%03d%s %02d%s %02.' . $this->digits . 'f%s%s';
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
            $this->getLngSuffix($dmsLng['degree']),
        );
    }
}
