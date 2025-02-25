<?php

/**
 * Die Klasse SqlSupport bietet mehrere statische Methoden, die Elemente
 * für SQL-Abfragen liefern.
 *
 * Aus den erzeugten Code-Blöcken können Felder generiert werden
 * (z.B. "codeBlock as distance") oder eine Umkreissuche
 * (z.B. "codeBlock <= radius)
 *
 * Bitte beachten: die hier benutzten Spatial-Funktionen der Datenbank sind
 * sensibel wenn die Eingangsdaten nicht wie erwartet ausfallen. Der erzeugte
 * Code sollte stets abgesichert werden.
 * (z.B. "where feld > '' && codeBlock < radius)
 */

namespace FriendsOfRedaxo\Geolocation\Calc;

use function sprintf;

class SqlSupport
{
    protected const SRID = 4326; // entspricht WGS84

    /**
     * Where-Klausel für eine Umkreissuche auf Basis zweier DB-Feldes vom Typ "text"
     * oder "varchar" mit jeweils einnem Teil der Koordinate: Längengrad, Breitengrad.
     *
     * Beispiel:
     *      Mittelpunkt der Suche 52.51450905 13.3500947726
     *      Breitengrad-Feld vom Typ "varchar": lat
     *      Längengrad-Feld vom Typ "varchar": lng
     *
     *  ST_Distance(
     *      ST_GeomFromText('POINT(52.51450905 13.3500947726)', 4326),
     *      ST_PointFromWKB(ST_AsBinary(POINT(lat,lng)), 4326)
     *  )
     *
     * @api
     */
    public static function circleSearch(Point $point, int $radius, string $latField, string $lngField, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        $distance = self::spDistance(
            self::spPointFromValue($point),
            self::spPointFromLatAndLng($latField, $lngField, $alias),
        );
        return sprintf('(%s%s > "" && %s%s > "" && %s <= %d)', $alias, $latField, $alias, $lngField, $distance, $radius);
    }

    /**
     * Where-Klausel für eine Umkreissuche auf Basis eines DB-Feldes vom Typ "text"
     * oder "varchar" mit einer kommagetrennten Liste "lat,lng". Für die Umwandlung in
     * einen Point muss das Komma in ein Leerzeichen umgewandelt werden.
     *
     * Beispiel:
     *      Mittelpunkt der Suche 52.51450905 13.3500947726
     *      Feld vom Typ "varchar": location
     *      format: "lat,lng"
     *
     *  ST_Distance(
     *      ST_GeomFromText('POINT(52.51450905 13.3500947726)', 4326),
     *      ST_GeomFromText(CONCAT('POINT(',REPLACE(location,',',' '),')'), 4326)
     *  )
     *
     * @api
     */
    public static function circleSearchLatLng(Point $point, int $radius, string $field, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        $distance = self::spDistance(
            self::spPointFromValue($point),
            self::spPointFromLatLng($field, $alias),
        );
        return sprintf('(%s%s > "" && %s <= %d)', $alias, $field, $distance, $radius);
    }

    /**
     * Where-Klausel für eine Umkreissuche auf Basis eines DB-Feldes vom Typ "text"
     * oder "varchar" mit einer kommagetrennten Liste "lng,lat". Für die Umwandlung in
     * einen Point müssen die beiden Teile extrahiert un in gegenteiliger Reihenfolge
     * eingefügt werden.
     *
     * Beispiel:
     *      Mittelpunkt der Suche 52.51450905 13.3500947726
     *      Feld vom Typ "varchar": location
     *      format: "lng,lat"
     *
     *  ST_Distance(
     *      ST_GeomFromText('POINT(52.51450905 13.3500947726)', 4326),
     *      ST_GeomFromText(CONCAT('POINT(',SUBSTRING_INDEX(location,',',-1),' ',SUBSTRING_INDEX(location,',',1),\)'), 4326)
     *  )
     *
     * @api
     */
    public static function circleSearchLngLat(Point $point, int $radius, string $field, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        $distance = self::spDistance(
            self::spPointFromValue($point),
            self::spPointFromLngLat($field, $alias),
        );
        return sprintf('(%s%s > "" && %s <= %d)', $alias, $field, $distance, $radius);
    }

    /**
     * @api
     */
    public static function spDistance(string $pointA, string $pointB): string
    {
        return sprintf(
            'ST_Distance(%s,%s)',
            $pointA,
            $pointB,
        );
    }

    /**
     * @api
     */
    public static function spPointFromValue(Point $point): string
    {
        return sprintf(
            'ST_GeomFromText("POINT(%s %s)", %d)',
            $point->lat(),
            $point->lng(),
            self::SRID,
        );
    }

    /**
     * Liefert das SQL zum Erzeugen eines geometrischen Objektes für einen
     * Punkt aus zwei Einzelfeldern für "breitengrad" und "längengrad".
     * @api
     */
    public static function spPointFromLatAndLng(string $latField, string $lngField, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        return sprintf(
            'ST_PointFromWKB(ST_AsBinary(POINT(%s%s %s%s)), %d)',
            $alias, $latField,
            $alias, $lngField,
            self::SRID,
        );
    }

    /**
     * Liefert das SQL zum Erzeugen eines geometrischen Objektes für einen
     * Punkt aus einem Kombi-Feld "breitengrad,längengrad".
     * @api
     */
    public static function spPointFromLatLng(string $field, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        return sprintf(
            'ST_GeomFromText(CONCAT("POINT(",REPLACE(%s%s,","," "),")"), %d)',
            $alias, $field,
            self::SRID,
        );
    }

    /**
     * Liefert das SQL zum Erzeugen eines geometrischen Objektes für einen
     * Punkt aus einem Kombi-Feld "längengrad,breitengrad".
     * @api
     */
    public static function spPointFromLngLat(string $field, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        return sprintf(
            'ST_GeomFromText(CONCAT("POINT(",SUBSTRING_INDEX(%s%s,",",-1)," ",SUBSTRING_INDEX(%s%s,",",1),")"), %d)',
            $alias, $field,
            $alias, $field,
            self::SRID,
        );
    }
}
