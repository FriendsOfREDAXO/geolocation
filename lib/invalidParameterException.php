<?php namespace Geolocation;

class InvalidParameter extends \Geolocation\Exception
{

    const KEY_LAT_LNG = 1;
    const LAT_MISSING = 2;
    const LNG_MISSING = 3;
    const LAT_RANGE = 4;
    const LNG_RANGE = 5;
    const STRING2DD = 6;
    const PRECISION = 7;
    const DELIMITER = 8;
    const BOX2WIDE = 9;
    const DATELINE = 10;
    const POLE = 11;
    const MAPSET_ID = 12;
    const MAPSET_DEF = 13;


    private $msg = [
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
    ];

    public function __construct( $errorCode, $values=[], ?\Throwable $previous = null ){
        $msg = vsprintf( ($this->msg[$errorCode] ?? 'Error'), $values );
        parent::__construct( $msg, $errorCode, $previous );
    }

}
