<?php
/**
 * baut auf phpGeo auf.
 * @see https://github.com/mjaschen/phpgeo
 * @see https://github.com/mjaschen/phpgeo/tree/master/docs
 *
 * Die Klasse Point ist eine Verwaltungsklasse um \Location\Coordinate herum.
 */

// TODO: negative Precision überall abfangen
// TODO: Doku anpassen Schnittstellen ahben sich geändert

namespace Geolocation\Calc;

use Exception;
use Location\Coordinate;
use Location\Factory\CoordinateFactory;
use Location\Formatter\Coordinate\DecimalMinutes;

use function strlen;

class Point
{
    /**
     * @api
     */
    public const DD = 1;
    /**
     * @api
     */
    public const DM = 2;
    /**
     * @api
     */
    public const DMS = 3;

    /**
     * Hier wird die \Location-Koordinate abgelegt, die durch Point gekapselt ist.
     * Es ist leider notwendig, dass die Koordinate zugänglich ist; egal ob so oder per getter
     * Sie ist nur für interne Zwecke in Geolocation\Calc
     */
    protected Coordinate $coord;

    /**
     *   getter für $coord.
     */
    public function coord(): Coordinate
    {
        return $this->coord;
    }

    // Es folgen Factory-Methoden zum Anlegen eines Punktes ----------------------------------------

    /**
     * Allgemeine Methode zum Anlegen des Koordinaten-Punktes.
     *
     * Die Koordinaten [$lat => latitude,$lng=>longitude] müssen existieren und numerisch sein.
     * Die Einhaltung der Grenzen (-90...+90,-180...+180) wird von \Location\Coordinate geprüft
     *
     * Damit können auch Punkte aus Arrays mit Text-Keys angelegt werden
     *  $daten = ['lng'=>12.34,'lat'=>-1234];
     *  $point = Point::factory( $daten, 'lat','lng');
     *
     * @api
     * @param array<int|string,int|float> $point   Koordinaten als Array mit zwei Werten
     * @param int|string                  $keyLat  Array-Key für das Element mit Breitangrad
     * @param int|string                  $keyLng  Array-Key für das Element mit Längengrad
     * @throws InvalidPointParameter
     */
    public static function factory(array $point, int|string $keyLat, int|string $keyLng): self
    {
        if ($keyLat === $keyLng) {
            throw new InvalidPointParameter(InvalidPointParameter::KEY_LAT_LNG, [$keyLat, $keyLng]);
        }
        if (!isset($point[$keyLat])) {
            throw new InvalidPointParameter(InvalidPointParameter::LAT_MISSING, [$keyLat]);
        }
        if (!isset($point[$keyLng])) {
            throw new InvalidPointParameter(InvalidPointParameter::LNG_MISSING, [$keyLng]);
        }
        if (!is_numeric($point[$keyLat])) {
            throw new InvalidPointParameter(InvalidPointParameter::LAT_RANGE, [$point[$keyLat]]);
        }
        if (!is_numeric($point[$keyLng])) {
            throw new InvalidPointParameter(InvalidPointParameter::LNG_RANGE, [$point[$keyLng]]);
        }
        $latitude = Math::normalizeLatitude($point[$keyLat]);
        $longitude = Math::normalizeLongitude($point[$keyLng]);

        return self::byCoordinate(new Coordinate($latitude, $longitude));
    }

    /**
     * Koordinaten-Punkt auf Basis einer \Location\Coordinate anlegen.
     *
     * @api
     */
    public static function byCoordinate(Coordinate $point): self
    {
        $self = new self();
        $self->coord = $point;
        return $self;
    }

    /**
     * Koordinaten-Punkt aus einem numerischen Array [0=>latitude,1=>longitude] anlegen.
     * Das ist die übliche Reihenfolge für LeafletJS.
     *
     * @api
     * @param array<int,int|float> $point
     */
    public static function byLatLng(array $point): self
    {
        return self::factory($point, 0, 1);
    }

    /**
     * Koordinaten-Punkt aus einem numerischen Array [0=>longitude,1=>latitude] anlegen.
     * Das ist die übliche Reihenfolge für geoJSON-Datensätze.
     *
     * @api
     * @param array<int,int|float> $point
     */
    public static function byLngLat(array $point): self
    {
        return self::factory($point, 1, 0);
    }

    /**
     * Koordinaten-Punkt aus einem Textfeld anlegen.
     *
     * Die String-Auswertung durch \Location\Factory\CoordinateFactory nach Best Guess. Also wird
     * nicht jeder String erkannt. CoordinateFactory hadert mit Punkt vs. Komma, also vor dem
     * Aufruf die Locale korrigieren
     *
     * @api
     * @see https://github.com/mjaschen/phpgeo/blob/master/docs/700_Parsing_and_Input/110_Coordinates_Parser.md
     * @throws InvalidPointParameter
     */
    public static function byText(string $point): self
    {
        try {
            $loc = (string) setlocale(LC_NUMERIC, '0');
            setlocale(LC_NUMERIC, 'en_US.UTF-8');
            $coordinate = CoordinateFactory::fromString($point);
            setlocale(LC_NUMERIC, $loc);
        } catch (Exception $e) {
            throw new InvalidPointParameter(InvalidPointParameter::STRING2DD, [$point], $e);
        }

        return self::byCoordinate($coordinate);
    }

    // Es folgen Methoden zur Ausgabe der Punkt-Daten ----------------------------------------------

    /**
     * Breitengrad.
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     */
    public function lat(?int $precision = null): float
    {
        if (null === $precision) {
            return $this->coord->getLat();
        }
        return round($this->coord->getLat(), max(0, $precision));
    }

    /**
     * Längengrad.
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     */
    public function lng(?int $precision = null): float
    {
        if (null === $precision) {
            return $this->coord->getLng();
        }
        return round($this->coord->getLng(), max(0, $precision));
    }

    /**
     * Gegenstück zu byLatLng: [0=>latitude,1=>longitude].
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     * @return array{0:float,1:float}
     */
    public function latLng(?int $precision = null): array
    {
        return [$this->lat($precision), $this->lng($precision)];
    }

    /**
     * Gegenstück zu byLngLat: [0=>longitude,1=>latitude].
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     * @return array{0:float,1:float}
     */
    public function lngLat(?int $precision = null): array
    {
        return [$this->lng($precision), $this->lat($precision)];
    }

    /**
     * Gegenstück zu factory: [$lat=>latitude,$lng=>longitude].
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     * @throws InvalidPointParameter
     * @return array<int|string,float>
     */
    public function pos(int|string $keyLat, int|string $keyLng, ?int $precision = null): array
    {
        if ($keyLat === $keyLng) {
            throw new InvalidPointParameter(InvalidPointParameter::KEY_LAT_LNG, [$keyLat, $keyLng]);
        }
        return [
            $keyLat => $this->lat($precision),
            $keyLng => $this->lng($precision),
        ];
    }

    /**
     * Gegenstück zu byText: "latitude longitude".
     *
     * Die konkrete Ausgabe kann durch den gewählten Formatierer gesteuert werden:
     *   DD:   als Grad/Dezimalstellen
     *   DM:   als Grad und Minute/Dezimalstellen
     *   DMS:  als Grad Minute Sekunde/Dezimalstellen
     *
     * Die Formatierer hadern mit Punkt vs. Komma, also vor dem Aufruf die Locale korrigieren
     *
     * @api
     * @param int     $formatter  Formatierer: Point::DD, Point::DM, Point::DMS
     * @param string  $delimiter  Trennzeichen zwischen lat/Lng; mindestens 1 Zeichen; default=","
     * @param ?int    $precision  Anzahl Nachkommastellen; default: alle
     * @throws InvalidPointParameter
     */
    public function text(int $formatter = self::DD, string $delimiter = ',', ?int $precision = null): string
    {
        if ('' === $delimiter || str_contains($delimiter, '.')) {
            throw new InvalidPointParameter(InvalidPointParameter::DELIMITER, [$delimiter]);
        }
        if (null !== $precision && 0 > $precision) {
            throw new InvalidPointParameter(InvalidPointParameter::PRECISION, [$precision]);
        }

        $loc = (string) setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'en_US.UTF-8');

        if (self::DM === $formatter) {
            $precision = $precision ?? max(
                strlen((string) abs($this->lat() - (int) $this->lat())) - 2,
                strlen((string) abs($this->lng() - (int) $this->lng())) - 2,
            );
            $str = (new DecimalMinutes($delimiter))
                ->setDigits($precision)
                ->setDecimalPoint('.')
                ->format($this->coord);
        } elseif (self::DMS === $formatter) {
            $str = (new DMS2($delimiter))
            ->setDigits($precision)
            ->setDecimalPoint('.')
                ->format($this->coord);
        } else {
            $str = $this->lat($precision) .
               $delimiter .
               $this->lng($precision);
        }

        setlocale(LC_NUMERIC, $loc);
        return $str;
    }

    /**
     * Liefert für diesen Punkt ein Punkt-Element für einen geoJSON-Datensatz.
     * $precision: Anzahl Nachkommastellen; default: alle.
     *
     * @api
     * @return array{type:string,coordinates:array{0:float,1:float}}
     */
    public function geoJSON(?int $precision = null): array
    {
        return [
            'type' => 'Point',
            'coordinates' => $this->lngLat($precision),
        ];
    }

    // Es folgen Methoden für Berechnungen ausgehend von diesem Punkt ------------------------------

    /**
     * Berechnet die kürzeste Distanz in Meter zwischen diesem und dem Zielpunkt auf dem Großkreis.
     * siehe Doku: Die kürzeste Distanz kann auch über die Pole gehen oder über die Dataumsgrenze.
     *
     * @api
     */
    public function distanceTo(self $point): float
    {
        return Math::distance($this, $point);
    }

    /**
     * Berechnet die Kompassrichtung (0°…360°) am Ausgangspunkt zwischen diesem und dem Zielpunkt
     * auf dem Großkreis.
     *
     * @api
     */
    public function bearingTo(self $point): float
    {
        return Math::bearingTo($this, $point);
    }

    /**
     * Berechnet die Kompassrichtung (0°…360°) am Zielpunkt zwischen diesem und dem Zielpunkt
     * auf dem Großkreis.
     *
     * @api
     */
    public function bearingAt(self $point): float
    {
        return Math::bearingFrom($this, $point);
    }

    /**
     * Berechnet den Zielpunkt ab diesem Punkt über Richtung in Grad (0°…360°, $bearing)
     * und Distanz in Meter ($distance).
     *
     * Das Ergebnis ist der Zielpunkt
     *
     * @api
     */
    public function moveBy(float $bearing, float $distance): self
    {
        return Math::goBearingDistance($this, $bearing, $distance);
    }

    /**
     * Sind zwei Punkte "identisch"?
     *
     * Die Funktion überprüft, ob dieser Pnkt und der als Parameter angegebene zweite
     * Punkt ($point) innerhalb des erlaubsten Abstands ($allowedDistance) liegen.
     *
     * Je nach Zoomfaktor der Karte kann man hiermit zu nah beieinander liegende Punkte aussortieren.
     *
     * @api
     */
    public function equals(self $point, float $allowedDistance = 0.1): bool
    {
        return $this->coord->hasSameLocation($point->coord, $allowedDistance);
    }

    /**
     * Liegt dieser Punkt außerhalb der Box, wird sie so erweitert,
     * das der Punkt Teil der Box wird.
     * Vorher isInBox aufzurufen ist nicht nötig.
     *
     * @api
     */
    public function extendBox(Box $box): self
    {
        $box->extendBy($this);
        return $this;
    }

    /**
     * Prüft ab, ob dieser Punkt innerhalb der Box liegt.
     *
     * @api
     */
    public function isInBox(Box $box): bool
    {
        return $box->contains($this);
    }
}
