<?php
/**
 * Sammelt alle Exceptions wegen ungültiger Funktions-Parameter
 * an einer Stelle.
 */

namespace Geolocation;

use Throwable;

class InvalidParameter extends \Geolocation\Exception
{
    /** @api */
    public const KEY_LAT_LNG = 1;
    /** @api */
    public const LAT_MISSING = 2;
    /** @api */
    public const LNG_MISSING = 3;
    /** @api */
    public const LAT_RANGE = 4;
    /** @api */
    public const LNG_RANGE = 5;
    /** @api */
    public const STRING2DD = 6;
    /** @api */
    public const PRECISION = 7;
    /** @api */
    public const DELIMITER = 8;
    /** @api */
    public const BOX2WIDE = 9;
    /** @api */
    public const DATELINE = 10;
    /** @api */
    public const POLE = 11;
    /** @api */
    public const MAPSET_ID = 12;
    /** @api */
    public const MAPSET_DEF = 13;
    /** @api */
    public const BOXEXTEND = 14;
    /** @api */
    public const BOXRESIZE = 15;
    /** @api */
    public const BOXRESIZELAT = 16;
    /** @api */
    public const BOXRESIZELNG = 17;
    /** @api */
    public const DMS_DIGITS = 18;
    /** @api */
    public const DMS_DECIMALPOINT = 19;
    /** @api */
    public const NOT_A_POINT = 20;

    /**
     * @var list<string>
     */
    private array $msg = [
        self::KEY_LAT_LNG => 'Key-Error! Latitude/longitude-Key missing or equal. Given $lat="%s" and $lng="%s"',
        self::LAT_MISSING => 'Latitude value ($point[%s]) missing',
        self::LNG_MISSING => 'Longitude value ($point[%s]) missing',
        self::LAT_RANGE => 'Latitude value must be numeric -90.0 ... +90.0 (given: "%s")',
        self::LNG_RANGE => 'Longitude value must be numeric -90.0 ... +90.0 (given: "%s")',
        self::STRING2DD => 'String to coordinate conversion failed (given: "%s")',
        self::PRECISION => 'Parameter "precision" expected to be NULL or positiv number; negative number "%s" given.',
        self::DELIMITER => 'Parameter "delimiter" is empty or contains reserved charcter dot(.) (given "%s").',
        self::BOX2WIDE => 'Box becomes to wide; longitudal distance east(%s°) to west(%s°) >= 180°) ("%s°").',
        self::DATELINE => 'Box-boundary crosses dateline meridian (±180°).',
        self::POLE => 'Box-boundary crosses pole (±90°).',
        self::MAPSET_ID => 'Missing mapset-ID or mapset "%s" not found',
        self::MAPSET_DEF => 'Default mapset-ID "%s" not found',
        self::BOXEXTEND => 'Parameter has to be "Point" or "Array of Point"',
        self::BOXRESIZE => 'Invalid resize hook-point "%s"',
        self::BOXRESIZELAT => 'Resize factor (latitude or lat/lng) expected larger than zero (given "%s")',
        self::BOXRESIZELNG => 'Resize factor (llongitude) expected larger than zero (given "%s")',
        self::DMS_DIGITS => 'Invalid number of decimal places (negative value "%s" given")',
        self::DMS_DECIMALPOINT => 'Replacement for decimal-point expected to be at least 1 charcter long; empty string or spaces.',
        self::NOT_A_POINT => 'Invalid Box-Parameter; Array of Point expected. Given at leat one item of other type ("%s"),',
    ];

    /**
     * @param list<string|int|bool|float> $values
     */
    public function __construct(int $errorCode, array $values = [], ?Throwable $previous = null)
    {
        $msg = vsprintf($this->msg[$errorCode] ?? 'Error', $values);
        parent::__construct($msg, $errorCode, $previous);
    }
}
