<?php

namespace Geolocation\Calc;

/**
 * baut auf phpGeo auf.
 * @see https://github.com/mjaschen/phpgeo
 * @see https://github.com/mjaschen/phpgeo/tree/master/docs
 *
 * n dieser Klasse sind diverse Rechnenroutinen als vereinfachte Schnittstelle mit
 * efault-Handlern enthalten. Faktisch ein Subset der vielen Möglichkeiten
 */

use Location\Bearing\BearingEllipsoidal;
use Location\Bearing\BearingInterface;
use Location\Distance\DistanceInterface;
use Location\Distance\Vincenty;

class Math
{
    protected static ?BearingInterface $bearingCalculator = null;

    protected static ?DistanceInterface $distanceCalculator = null;

    /**
     * setzt "Kompass"-Interface für Berechnungen auf Kompasskurs-Basis
     * Trägt die übergebene BearingInterface-Instanz als neuen Default ein.
     *
     * @see https://github.com/mjaschen/phpgeo/blob/master/docs/400_Calculations/420_Bearing_and_Destination.md
     * @api
     */
    public static function setBearingCalculator(BearingInterface $bearingCalculator): BearingInterface
    {
        self::$bearingCalculator = $bearingCalculator;
        return self::$bearingCalculator;
    }

    /**
     * ruft das aktulle Default-"Kompass"-Interface für Berechnungen auf Kompasskurs-Basis ab
     * Fehlt ein Interface, wird das Interface BearingEllipsoidal angelegt.
     *
     * @api
     */
    public static function bearingCalculator(): BearingInterface
    {
        if (null === self::$bearingCalculator) {
            return self::setBearingCalculator(new BearingEllipsoidal());
        }
        return self::$bearingCalculator;
    }

    /**
     * setzt "Distanz"-Interface für die Berechnungen von Distanzen
     * Trägt die übergebene DistanceInterface-Instanz als neuen Default ein.
     *
     * @see https://github.com/mjaschen/phpgeo/blob/master/docs/400_Calculations/410_Distance_and_Length.md
     * @api
     */
    public static function setDistanceCalculator(DistanceInterface $distanceCalculator): DistanceInterface
    {
        self::$distanceCalculator = $distanceCalculator;
        return self::$distanceCalculator;
    }

    /**
     * ruft das aktulle Default-"Distanz"-Interface für Berechnungen von Distanzen ab.
     * Fehlt ein Interface, wird das Interface Vincenty angelegt
     *
     * @api
     */
    public static function distanceCalculator(): DistanceInterface
    {
        if (null === self::$distanceCalculator) {
            return self::setDistanceCalculator(new Vincenty());
        }
        return self::$distanceCalculator;
    }

    /**
     * Berechnet die Kompass-Richtung am Start (0...360°) in Richtung Ziel.
     *
     * @api
     */
    public static function bearingTo(Point $from, Point $to): float
    {
        return self::bearingCalculator()->calculateBearing($from->coord(), $to->coord());
    }

    /**
     * Berechnet die Kompass-Richtung am Ziel (0...360°) aus Richtung Start kommend.
     *
     * @api
     */
    public static function bearingFrom(Point $from, Point $to): float
    {
        return self::bearingCalculator()->calculateFinalBearing($from->coord(), $to->coord());
    }

    /**
     * Berechnet die kürzeste Distanz zwischen Start und Ziel, geht also über den Großkreis
     * Das Ergebnis ist die Diszanz in Meter.
     *
     * @api
     */
    public static function distance(Point $from, Point $to): float
    {
        return self::distanceCalculator()->getDistance($from->coord(), $to->coord());
    }

    /**
     * Berechnet den Zielpunkt über Startpunkt, Richtung (bearingTo) und Distanz
     * Richtung in Grad (0°...360°), Distanz in Meter.
     *
     * @api
     */
    public static function goBearingDistance(Point $from, float $bearing, float $distance): Point
    {
        $bearing = self::normalizeBearing($bearing);
        $target = self::bearingCalculator()->calculateDestination($from->coord(), $bearing, $distance);
        return Point::byCoordinate($target);
    }

    /**
     * Umrechnung der dezimalen Koordinaten in die Elemente Grad/Minute.
     *
     * [
     *   'degree' => Grad mit Vorzeichen (- entspricht westl Länge bzw. südliche Breite)
     *   'minute' => Bogenminuten mit Nachkommastellen
     *   'min'    => Bogenminuten ohne Nachkommastellen
     * ]
     *
     * @api
     * @return array{degree:int,minute:float,min:int}
     */
    public static function dd2dm(float $degree, ?int $precision = null): array
    {
        $deg = (int) $degree;
        $minute = abs($degree - $deg) * 60;
        return [
            'degree' => $deg,
            'minute' => null === $precision ? $minute : round($minute, max(0, $precision)),
            'min' => (int) round($minute),
        ];
    }

    /**
     * Umrechnung der dezimalen Koordinaten in die Elemente Grad/Minute/Sekunde.
     *
     * [
     *   'degree' => Grad mit Vorzeichen (- entspricht westl Länge bzw. südliche Breite)
     *   'minute' => Bogenminuten mit Nachkommastellen
     *   'second' => Bogensekunde mit Nachkommastellen
     *   'sec'    => Bogensekunde ohne Nachkommastellen
     * ]
     *
     * @api
     * @return array{degree:int,minute:int,second:float,sec:int}
     */
    public static function dd2dms(float $degree, ?int $precision = null): array
    {
        $deg = (int) $degree;
        $minute = abs($degree - $deg) * 60;
        $min = (int) $minute;
        $second = abs($minute - $min) * 60;
        return [
            'degree' => $deg,
            'minute' => $min,
            'second' => null === $precision ? $second : round($second, max(0, $precision)),
            'sec' => (int) round($second),
        ];
    }

    /**
     * Rechnet positive und negative Längengrade um in Werte -180...+180
     * Optional: clippen statt umrechnen.
     *
     *  -200°  =>   160°
     *   200°  =>  -160°
     *
     * @api
     */
    public static function normalizeLongitude(float $longitude, bool $clip = false): float
    {
        if ($clip) {
            return max(-180, min($longitude, 180));
        }
        $longitude = fmod($longitude, 360);
        if ($longitude < -180) {
            return $longitude + 360;
        }

        if ($longitude > 180) {
            return $longitude - 360;
        }
        return $longitude;
    }

    /**
     * Kappt Beitengrade an den Polen. Überlauf ist hier Quatsch.
     *
     *   91°  =>   90°
     *  -91°  =>  -90°
     *
     * @api
     */
    public static function normalizeLatitude(float $latitude): float
    {
        return max(-90, min(90, $latitude));
    }

    /**
     * Rechnet positive und negative Kompasskurse um in Werte 0...360
     *  - 450°  =>  90°
     *    -90°  =>  270°.
     *
     * @api
     */
    public static function normalizeBearing(float $bearing): float
    {
        return fmod($bearing, 360) + ($bearing < 0 ? 360 : 0);
    }
}
