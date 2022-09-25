<?php namespace Geolocation\Calc;
/**
 *	baut auf phpGeo auf
 *  @see https://github.com/mjaschen/phpgeo
 *  @see https://github.com/mjaschen/phpgeo/tree/master/docs
 *
 *	Die Klasse Point ist eine Verwaltungsklasse um \Location\Coordinate herum.
 *
 * @package geolocation
 */

use \Location\Coordinate;
use \Location\Factory\CoordinateFactory;
use \Location\Formatter\Coordinate\Coordinate\DecimalMinutes;
use \Geolocation\Calc\DMS2;

#class InvalidPointParameter extends InvalidParameter {}


class Point
{

	const DD = 1;
	const DM = 2;
	const DMS = 3;

	/**
     * @var \Location\Coordinate
	 *
	 * Hier wird die \Location-Koordinate abgelegt, die durch Point gekapselt ist.
	 * Es ist leider notwendig, dass die Koordinate zugänglich ist; egal ob so oder per getter
	 * Sie ist nur für interne Zwecke in Geolocation\Calc; BC-Diskussionen bei externer Verwendung
	 * stoßen auf taube Ohren.
     */
	protected $coord = null;

	/**
     *   getter für $coord
	 *
     *   @return \Location\Coordinate
     */
	public function coord(): Coordinate
	{
		return $this->coord;
	}

	// Es folgen Factory-Methoden zum Anlegen eines Punktes ----------------------------------------


	/**
     *   Allgemeine Methode zum Anlegen des Koordinaten-Punktes
	 *
	 *	 Die Koordinaten [$lat => latitude,$lng=>longitude] müssen existieren und numerisch sein.
	 *   Die Einhaltung der Grenzen (-90...+90,-180...+180) wird von \Location\Coordinate geprüft
	 *
	 *	 Damit können auch Punkte aus Arrays mit Text-Keys angelegt werden
	 *		$daten = ['lng'=>12.34,'lat'=>-1234]M
	 *		$point = Point::factory( $daten, 'lat','lng');
	 *
	 *   @throws \Geolocation\Calc\InvalidPointParameter
	 *
	 *   @param array 						Koordinaten als Array mit zwei Werten
	 *   @param int|string					Array-Key für das Element mit Breitangrad
	 *   @param int|string					Array-Key für das Element mit Längengrad
     *   @return \Geolocation\Calc\Point
     */
	 public static function factory( array $point, $keyLat, $keyLng ): self
 	{
		if( $keyLat == $keyLng ) {
			throw new InvalidPointParameter( InvalidPointParameter::KEY_LAT_LNG, [$keyLat,$keyLng]) ;
     	}
 		if( !isset($point[$keyLat]) ) {
			throw new InvalidPointParameter( InvalidPointParameter::LAT_MISSING, [$keyLat]) ;
     	}
 		if( !isset($point[$keyLng]) ) {
			throw new InvalidPointParameter( InvalidPointParameter::LNG_MISSING, [$keyLng]) ;
        }
 		if( !is_numeric($point[$keyLat]) ) {
			throw new InvalidPointParameter( InvalidPointParameter::LAT_RANGE, [$point[$keyLat]]) ;
        }
 		if( !is_numeric($point[$keyLng]) ) {
			throw new InvalidPointParameter( InvalidPointParameter::LNG_RANGE, [$point[$keyLng]]) ;
 		}

 		$latitude = Math::normalizeLatitude( $point[$keyLat] );
		$longitude = Math::normalizeLongitude( $point[$keyLng] );

 		return self::byCoordinate( new Coordinate( $latitude, $longitude ) );
 	}


	/**
     *   Koordinaten-Punkt auf Basis einer \Location\Coordinate anlegen
	 *
	 *   @param \Location\Coordinate		Koordinate
     *   @return \Geolocation\Calc\Point
     */
	public static function byCoordinate( Coordinate $point ): self
	{
		$self = new self();
		$self->coord = $point;
		return $self;
	}

	/**
     *   Koordinaten-Punkt aus einem numerischen Array [0=>latitude,1=>longitude] anlegen.
	 *
	 *	 Das ist die übliche Reihenfolge für LeafletJS
	 *
	 *   @param array						Koordinate
     *   @return \Geolocation\Calc\Point
     */
	 public static function byLatLng( array $point ): self
 	{
 		return self::factory( $point, 0, 1 );
 	}

	/**
     *   Koordinaten-Punkt aus einem numerischen Array [0=>longitude,1=>latitude] anlegen.
	 *
	 *	 Das ist die übliche Reihenfolge für geoJSON-Datensätze
	 *
	 *   @param array						Koordinate
     *   @return \Geolocation\Calc\Point
     */
 	public static function byLngLat( array $point ): self
 	{
 		return self::factory( $point, 1, 0 );
 	}

	/**
     *   Koordinaten-Punkt aus einem Textfeld anlegen.
	 *
	 *	 Die String-Auswertung durch \Location\Factory\CoordinateFactory nach Best Guess. Also wird
	 *   nicht jeder String erkannt. CoordinateFactory hadert mit Punkt vs. Komma, also vor dem
	 *	 Aufruf die Locale korrigieren
	 *
	 *	 @see https://github.com/mjaschen/phpgeo/blob/master/docs/700_Parsing_and_Input/110_Coordinates_Parser.md
	 *
	 *   @throws \Geolocation\Calc\InvalidPointParameter
	 *
	 *   @param string						Koordinate
     *   @return \Geolocation\Calc\Point
     */
	public static function byText( string $point ): self
	{
		try {
			$loc = setlocale(LC_NUMERIC,0);
			setlocale(LC_NUMERIC,'en_US.UTF-8');
			$coordinate = CoordinateFactory::fromString( $point );
			setlocale(LC_NUMERIC,$loc);
		} catch (\Exception $e) {
			throw new InvalidPointParameter( InvalidPointParameter::STRING2DD, [$point], $e) ;
		}

		return self::byCoordinate( $coordinate );
	}


	// Es folgen Methoden zur Ausgabe der Punkt-Daten ----------------------------------------------

	/**
     *   Breitengrad
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return float
     */
	public function lat( ?int $precision=null ): float
	{
		if( null === $precision ) {
			return $this->coord->getLat();
		}
		return round( $this->coord->getLat(), max(0,$precision));
	}

	/**
     *   Längengrad
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return float
     */
	public function lng( ?int $precision=null ): float
	{
		if( null === $precision ) {
			return $this->coord->getLng();
		}
		return round( $this->coord->getLng(), max(0,$precision));
	}

	/**
     *   Gegenstück zu byLatLng: [0=>latitude,1=>longitude]
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function latLng( ?int $precision=null ): array
	{
		return [$this->lat($precision),$this->lng($precision)];
	}

	/**
     *   Gegenstück zu byLngLat: [0=>longitude,1=>latitude]
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function lngLat( ?int $precision=null ): array
	{
		return [$this->lng($precision),$this->lat($precision)];
	}

	/**
     *   Gegenstück zu factory: [$lat=>latitude,$lng=>longitude]
	 *
	 *   @throws \Geolocation\Calc\InvalidPointParameter
	 *
	 *   @param mixed			array-Key für Latitude
	 *   @param mixed			array-Key für Longitude
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function pos( $keyLat, $keyLng, ?int $precision=null ): array
	{
		if( $keyLat == $keyLng ) {
			throw new InvalidPointParameter( InvalidPointParameter::KEY_LAT_LNG, [$keyLat,$keyLng]) ;
     	}
		return [
			$keyLat => $this->lat($precision),
			$keyLng => $this->lng($precision)
		];
	}

	/**
     *   Gegenstück zu byText: "latitude longitude"
	 *
	 *	 Die konkrete Ausgabe kann durch den gewählten Formatierer gesteuert werden:
	 *		DD:		als Grad/Dezimalstellen
	 *		DM:		als Grad und Minute/Dezimalstellen
	 *		DMS:	als Grad Minute Sekunde/Dezimalstellen
	 *
	 *   Die Formatierer hadern mit Punkt vs. Komma, also vor dem Aufruf die Locale korrigieren
	 *
	 *   @throws \Geolocation\Calc\InvalidPointParameter     negative Anzahl Nachkommastellen
	 *
	 *   @param int				Formatierer: Point::DD,Point::DM, Point::DMS
	 *   @param string			Trennzeichen zwischen lat/Lng
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return string
     */
	public function text( int $formatter=self::DD, ?string $delimiter=null, ?int $precision=null ): string
	{
		$delimiter = $delimiter ?? ' ';
		if( (null !== $delimiter) && (0==strlen($delimiter) || str_contains($delimiter, '.')) ) {
			throw new InvalidPointParameter( InvalidPointParameter::DELIMITER, [$delimiter]) ;
        }
		if( null !== $precision && 0 > $precision ) {
			throw new InvalidPointParameter( InvalidPointParameter::PRECISION, [$precision]) ;
		}

		$loc = setlocale(LC_NUMERIC,0);
		setlocale(LC_NUMERIC,'en_US.UTF-8');

		if( self::DM === $formatter ){
			$precision = $precision ?? max (
				strlen( abs( $this->lat() - (int)$this->lat() ) ) - 2,
				strlen( abs( $this->lng() - (int)$this->lng() ) ) - 2,
			);
			$str = (new DecimalMinutes($delimiter))
				->setDigits( $precision )
				->setDecimalPoint( '.' )
				->format( $this->coord );
		}

		elseif( self::DMS === $formatter ){
			$str = (new DMS2($delimiter))
			->setDigits( $precision )
			->setDecimalPoint( '.' )
				->format( $this->coord );
		}

		else {
			$str = $this->lat($precision) .
			   $delimiter .
			   $this->lng($precision);
		}

		setlocale(LC_NUMERIC,$loc);
		return $str;
	}

	/**
	 *   Abschnitt für einen geoJSON-Datensatz: als Punkt.
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
	 *   @return array
	 */
	public function geoJSON( ?int $precision=null ): array
	{
		return [
			'type' => 'Point',
			'coordinates' => $this->lngLat( $precision ),
		];
	}


	// Es folgen Methoden für Berechnungen ausgehend von diesem Punkt ------------------------------

	/**
     *   Kürzeste Distanz in Meter zwischen diesem und dem Zielpunkt auf dem Großkreis
	 *
	 *   @param \Geolocation\Calc\Point		Zielpunkt
     *   @return float						Distanz in Meter
     */
	public function distanceTo( self $point ): float
	{
		return Math::distance( $this, $point );
	}

	/**
     *   Kompassrichtung am Ausgangspunkt zwischen diesem und dem Zielpunkt auf dem Großkreis
	 *
	 *   @param \Geolocation\Calc\Point		Zielpunkt
     *   @return float						Kompasskurs in Grad (0°...360°)
     */
	public function bearingTo( self $point ): float
	{
		return Math::bearingTo( $this, $point );
	}

	/**
     *   Kompassrichtung am Zielpunkt zwischen diesem und dem Zielpunkt auf dem Großkreis
	 *
	 *   @param \Geolocation\Calc\Point		Zielpunkt
     *   @return float						Kompasskurs in Grad (0°...360°)
     */
	public function bearingAt( self $point ): float
	{
		return Math::bearingFrom( $this, $point );
	}

	/**
     *   Berechnet den Zielpunkt ab diesem Punkt über Richtung (bearing) und Distanz
	 *
	 *	 @param float						Richtung in Grad (0°...360°)
	 *   @param float						Distanz in Meter
	 *	 @return \Geolocation\Calc\Point	Zielpunkt
     */
	public function moveBy( float $bearing, float $distance ): self
	{
		return Math::goBearingDistance( $this, $bearing, $distance );
	}

	/**
     *   Sind zwei Punkte "identisch"?
	 *
	 *	 Je nach Zoomfaktor kann man hiermit nah beieinander liegende Punkte aussortieren.
	 *
	 *	 @param \Geolocation\Calc\Point		Zweiter Punkt
	 *   @param float						zulässige Distanz; default: 10 cm
	 *	 @return bool						Abstand kleiner/gleich $allowedDistance
     */
	public function equals( self $point, float $allowedDistance=0.1 ): bool
	{
		return $this->coord->hasSameLocation( $point->coord, $allowedDistance );
	}

	/**
     *   Liegt der Punkt außerhalb der Box, wird sie so erweitert, das der Punkt Teil der Box wird.
	 *	 Vorher isInBox aufzurufen ist nicht nötig.
	 *
	 *	 @param \Geolocation\Calc\Box		Zu erweiternde Box
	 *	 @return \Geolocation\Calc\Point	dieser Punkt
     */
	public function extendBox( Box $box ): self
	{
		$box->extendBy ( $this );
		return $this;
	}

	/**
     *   Prüft ab, ob der Punkt innerhalb einer Box liegt
	 *
	 *	 @param \Geolocation\Calc\Box		die Box
	 *	 @return boolval					true wenn in der Box, sonst false
     */
	public function isInBox( Box $box ): bool
	{
		return $box->contains ( $this );
	}

}
