<?php namespace Geolocation\Calc;
/**
 *	baut auf phpGeo auf
 *  @see https://github.com/mjaschen/phpgeo
 *  @see https://github.com/mjaschen/phpgeo/tree/master/docs
 *
 *	Die Klasse Box ist eine Verwaltungsklasse um \Location\Bounds herum.
 *
 *	Das Problem bei Boxen ist "Überschreiten der Datumslinie", also dass sich die Box über die
 *	-180°-Länge erstreckt.
 *
 *	Basisannahme zur Lösung des Problems:
 *		Eine Box muss weniger als 180° Länge umfassen! Daher werden West und East ggf. vertauscht.
 *		Anders gesagt: Angabe zweier Ecken schafft immer ein Rechteck < 180°
 *		Erweiterungen "extendBy(Point)" führen, ab 180° Breite der Box zu einer Exception.
 *
 *	I.d.R sind das keine relevanten Einschränkungen, da Boxen selten so groß sein dürften.
 *
 * @package geolocation
 */

use \Geolocation\InvalidParameter;

use \Location\Bounds;

class InvalidBoxParameter extends InvalidParameter {}

class Box
{
	const HOOK_NW = 1;
	const HOOK_NE = 2;
	const HOOK_SE = 3;
	const HOOK_SW = 4;
	const HOOK_CE = 5;

	/**
     * @var \Location\Bounds
	 *
	 * Hier wird die Bounds-Instanz abgelegt, die durch Box gekapselt ist.
     */
	protected $bounds = null;

	/**
     * @var \Geolocation\Calc\Point
	 *
	 * Die NordWest-Ecke der Box
     */
	protected $nw = null;

	/**
     * @var \Geolocation\Calc\Point
	 *
	 * Die SüdOst-Ecke der Box
     */
	protected $se = null;

	/**
     *   Factory-Methode zum Anlegen der Box aus einem Punkt-Array
	 *
	 *	 @throws \Geolocation\Calc\InvalidBoxParameter
	 *
	 *   @param array 						array of \Geolocation\Calc\Point
     *   @return \Geolocation\Calc\Box
     */
	public static function factory( array $points ): self
	{
		if( 0 == count($points) ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::MISSING_POINT ) ;
		}
		$lat = [];
		$lng = [];
		foreach( $points as $point ) {
			if( !($point instanceof Point) ) {
				throw new InvalidBoxParameter( InvalidBoxParameter::NOT_A_POINT ) ;
			}
			$lat[] = $point->lat();
			$lng[] = $point->lng();
		}
		$south = min( $lat );
		$north = max( $lat );
		$west = min( $lng );
		$east = max( $lng );

		if( 180 <= ($east-$west) ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOX2WIDE, [$east,$west,$east-$west]) ;
		}

		$self = new self();
		$self->nw = Point::byLatLng( [$north,$west] );
		$self->se = Point::byLatLng( [$south,$east] );
		$self->bounds = new Bounds( $self->nw->coord(), $self->se->coord() );
		return $self;
	}

	/**
     *   Factory-Methode zum Anlegen der Box aus zwei gegenüberliegenden Koordinaten-Punkten
	 *
	 *	 @throws \Geolocation\Calc\InvalidBoxParameter
	 *
	 *   @param \Geolocation\Calc\Point		Die eine Ecke
	 *   @param \Geolocation\Calc\Point		Die gegenüberliegende Ecke
     *   @return \Geolocation\Calc\Box
     */
	static function byCorner( Point $cornerA, Point $cornerB ): self
	{
		return self::factory( [$cornerA,$cornerB] );
		$south = min( $cornerA->lat(), $cornerB->lat() );
		$north = max( $cornerA->lat(), $cornerB->lat() );
		$west = min( $cornerA->lng(), $cornerB->lng() );
		$east = max( $cornerA->lng(), $cornerB->lng() );

		if( 180 <= ($east-$west) ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOX2WIDE, [$east,$west,$east-$west]) ;
		}

		$self = new self();
		$self->nw = Point::byLatLng( [$north,$west] );
		$self->se = Point::byLatLng( [$south,$east] );
		$self->bounds = new Bounds( $self->nw->coord(), $self->se->coord() );
		return $self;
	}

	/**
     *   Factory-Methode zum Anlegen der quadratischen Box aus Mittelpunkt und Innenradius
	 *
	 *   Die Arbeit macht self::bySize. Aus dem Radius wird (mal 2) Höhe und Breite gesetzt.
	 *
	 *	 @throws \Geolocation\Calc\InvalidBoxParameter
	 *
	 *   @param \Geolocation\Calc\Point		Mittelpunkt
	 *   @param float						Radius in Meter
     *   @return \Geolocation\Calc\Box
     */
	static function byInnerCircle ( Point $center, float $radius ) : self
	{
		$radius *= 2;
		return self::bySize ( $center, $radius, $radius );
	}

	/**
     *   Factory-Methode zum Anlegen der quadratischen Box aus Mittelpunkt und Außenradius
	 *
	 *   Die Arbeit macht self::bySize. Aus dem Radius wird (mal 2) Höhe und Breite gesetzt.
	 *
	 *	 @throws \Geolocation\Calc\InvalidBoxParameter
	 *
	 *   @param \Geolocation\Calc\Point		Mittelpunkt
	 *   @param float						Radius in Meter
     *   @return \Geolocation\Calc\Box
     */
	static function byOuterCircle ( Point $center, float $radius ) : self
	{
		$radius = $radius / sqrt(2) * 2;
		return self::bySize( $center, $radius, $radius );
	}

	/**
     *   Factory-Methode zum Anlegen der Box aus Mittelpunkt, Höhe und Breite
	 *
	 *   Geprüft wird, ob der Platz auch reicht. Die Box muss weit genug vom Pol entfernt sein,
	 *   damit die Breite auf dem polnäheren Breitegrad nicht über die Datumsgrente führt.
	 *   Die maximale Boxbreite muss unter 180° sein.
	 *
	 *	 @throws \Geolocation\Calc\InvalidBoxParameter
	 *
	 *   @param \Geolocation\Calc\Point		Mittelpunkt
	 *   @param float						Breite in Meter
	 *   @param float						Höhe in Meter
     *   @return \Geolocation\Calc\Box
     */
	static function bySize ( Point $center, float $width, float $height ) : self
	{
		$width = $width / 2;
		$height = $height / 2;

		// In Pole-Nähe gibt es sehr seltsame Ergebnisse; erst mal prüfen
		// Abbruch wenn ab Center der Radius über die Pole reicht.
		$pole = (0 <= $center->lat()) ? 90 : -90;
		$poleDistance = $center->distanceTo( Point::byLatLng( [$pole,$center->lat()] ) );
		if( $poleDistance <= $height ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::POLE) ;
		}

		// Polenahe Kante
		$pointSN = $center->moveBy( abs($pole-90), $height );

		// Am Datumsgenzen-Meridian gibt es sehr seltsame Ergebnisse; erst mal prüfen
		// Abbruch wenn als Breite der pol-näheren Kante der Radius über den 180°-Meridian reicht.
		$dateline = (0 <= $center->lng()) ? 180 : -180;
		$datelineDistance = $pointSN->distanceTo( Point::byLatLng( [$pointSN->lat(),$dateline] ) );
		if( $datelineDistance <= $width ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::DATELINE) ;
		}

		// Dateline-nahe Kante
		$pointWE = $center->moveBy( abs($dateline-90), $width );

		// Die Box passt. Nun die tatsächlichen Grenzen berechnen
		// Die Breite muss untr 180° liegen.
		$W = $pointWE->lng();
		$E = $center->lng() * 2 - $W;
		if( 180 <= abs($E-$W) ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOX2WIDE,[$E,$W,$E-$W]);
		}
		$S = $pointSN->lat();
		$N = $center->lat() * 2 - $S;

		return self::factory([
			Point::byLatLng( [$N, $W] ),
			Point::byLatLng( [$S, $E] )
		]);
	}

	// Es folgen Methoden zur Ausgabe einzelner Daten ----------------------------------------------

	/**
     *   Nördlicher Breitengrad der Box
	 *
     *   @return float
     */
	public function north(): float
	{
		return $this->bounds->getNorth();
	}

	/**
     *   Südlicher Breitengrad der Box
	 *
     *   @return float
     */
	public function south(): float
	{
		return $this->bounds->getSouth();
	}

	/**
     *   Westlicher Längengrad der Box
	 *
     *   @return float
     */
	public function west(): float
	{
		return $this->bounds->getWest();
	}

	/**
     *   Östlicher Längengrad der Box
	 *
     *   @return float
     */
	public function east(): float
	{
		return $this->bounds->getEast();
	}

	/**
     *   NordWestliche Ecke der Box
	 *
	 *	 Faktisch ist die Rückgabe des internen NordWest-Punktes
	 *	 Wenn die Box per Extend vergrößert wird, wird auch ein neuer NW-Punkt angelegt.
	 *   Eine extern als Zugriffshilfe zwischengespeicherte Instanz ist dann nicht mehr aktuell.
	 *
     *   @return \Geolocation\Calc\Point
     */
	public function northWest(): Point
	{
		return $this->nw;
	}

	/**
     *   Südöstliche Ecke der Box
	 *
	 *	 Faktisch ist die Rückgabe des internen SüdWest-Punktes
	 *	 Wenn die Box per Extend vergrößert wird, wird auch ein neuer NW-Punkt angelegt.
	 *   Eine extern als Zugriffshilfe zwischengespeicherte Instanz ist dann nicht mehr aktuell.
	 *
     *   @return \Geolocation\Calc\Point
     */
	public function southEast(): Point
	{
		return $this->se;
	}

	/**
     *   Nordöstliche Ecke der Box
	 *
	 *	 Das es hierfür keinen existenten Point gibt, wird eine neu angelegte Instanz
	 *   Übergeben. Hat den Nachteil, dass bei jedem Abruf eine neue Instanz angelegt wird.
	 *
     *   @return \Geolocation\Calc\Point
     */
	public function northEast(): Point
	{
		return Point::byLatLng( [$this->north(), $this->east()] );
	}

	/**
     *   Südwestliche Ecke der Box
	 *
	 *	 Das es hierfür keinen existenten Point gibt, wird eine neu angelegte Instanz
	 *   Übergeben. Hat den Nachteil, dass bei jedem Abruf eine neue Instanz angelegt wird.
	 *
     *   @return \Geolocation\Calc\Point
     */
	public function southWest(): Point
	{
		return Point::byLatLng( [$this->south(), $this->west()] );
	}

	/**
     *   Zentrum der Box
	 *
	 *	 Das es hierfür keinen existenten Point gibt, wird eine neu angelegte Instanz
	 *   Übergeben. Hat den Nachteil, dass bei jedem Abruf eine neue Instanz angelegt wird.
	 *
     *   @return \Geolocation\Calc\Point
     */
	public function center(): Point
	{
		return Point::byCoordinate( $this->bounds->getCenter() );
	}

	/**
     *   Koordinaten in der Reihenfolge lat/lng (LeafletJS-Reihenfolge)
	 *
	 *	 Liefert ein Array mit den Eck-Koordinaten der Box
	 *		[ 0 => [0=>latNW,1=lngNW], 1=>[0=>latSE,lngSE] ]
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function latLng( ?int $precision=null ): array
	{
		return [
			0 => $this->northWest()->latLng($precision),
			1 => $this->southEast()->latLng($precision),
		];
	}

	/**
     *   Koordinaten in der Reihenfolge lng/lat (geoJSON-Reihenfolge)
	 *
	 *	 Liefert ein Array mit den Eck-Koordinaten der Box
	 *		[ 0 => [0=>lngNW,1=latNW], 1=>[0=>lmgSE,latSE] ]
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function lngLat( ?int $precision=null ): array
	{
		return [
			0 => $this->northWest()->lngLat($precision),
			1 => $this->southEast()->lngLat($precision),
		];
	}

	/**
     *   Die Breite der Box als Abstand der Längengrade
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function width( ?int $precision=null ): float
	{
		return abs( $this->east() - $this->west() );
	}

	/**
     *   Die Höhe der Box als Abstand der Breitengrade
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
     *   @return array
     */
	public function height( ?int $precision=null ): float
	{
		return abs( $this->north() - $this->south() );
	}

	/**
	 *   Abschnitt für einen geoJSON-Datensatz: als zwei Punkte.
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
	 *   @return array
	 */
	public function geoJSONMultipoint( ?int $precision=null ): array
	{
		return [
			'type' => 'MultiPoint',
			'coordinates' => $this->lngLat($precision),
		];
	}

	/**
	 *   Abschnitt für einen geoJSON-Datensatz: als geschlossenes Polygon.
	 *
	 *   @param null|int		Anzahl Nachkommastellen; default: alle
	 *   @return array
	 */
	public function geoJSONPolygon( ?int $precision=null ): array
	{
		return [
			'type' => 'Polygon',
			'coordinates' => [[
				$this->northWest()->lngLat($precision),
				$this->northEast()->lngLat($precision),
				$this->southEast()->lngLat($precision),
				$this->southWest()->lngLat($precision),
				$this->northWest()->lngLat($precision),
			]],
		];
	}

	// Es folgen Methoden für Berechnungen ---------------------------------------------------------

	/**
     *   Außenradius, also Kreis um den Mittelpunkt mit den Radius "halbe Diagonale"
	 *   Funktioniert auch bei nicht-quadratischen Boxen.
	 *
     *   @return float
     */
	public function outerRadius(): float
	{
		return $this->nw->distanceTo( $this->se ) / 2;
	}

	/**
     *   Innenradius, also Kreis um den Mittelpunkt mit den Radius "halbe Distanz Nord/Süd" oder
	 *	 "halbe Distanz Ost/West". Es wird der kürzere Weg herangezogen, da es sonst bei nicht-
	 *   quadratischen Boxen schwierig wird.
	 *
     *   @return float
     */
	public function innerRadius(): float
	{
		$sw = $this->southWest();
		$distanceSN = $this->nw->distanceTo( $sw );
		$distanceWE = $this->se->distanceTo( $sw );
		return min( $distanceSN,$distanceWE ) / 2;
	}

	/**
     *   Prüft ob der angegeben Point innerhalb der Box liegt
	 *
	 *	 @param \Geolocation\Calc\Point
     *   @return bool						true = innen, false = außen
     */
	public function contains( Point $point )
	{
		if( $point->lat() > $this->north() ) return false;
		if( $point->lat() < $this->south() ) return false;
		if( $point->lng() < $this->west() ) return false;
		if( $point->lng() > $this->east() ) return false;
		return true;
	}

	/**
     *   Erweitert die Box so, dass der angegebene Punkt innerhalb der Box liegt
	 *
	 *	 Sofern der neue Punkt dazu führt, dass die Box 180° oder mehr breit werden würde,
	 *	 bricht die Methode mit einer Exception ab.
	 *   Im Erfolgsfall sind $this->nw, $this->se und $this->bounds anschließend neu gesetzt.
	 *
	 *	 @param array|\Geolocation\Calc\Point	Entweder ein einzelner Punkt oder ein Array
     *   @return \Geolocation\Calc\Box
     */
	public function extendBy ( $data ): self
	{
		if( !is_array($data) ) $data = [$data];

		$south = $north = $west = $east = [];

		foreach( $data as $point ){
			if( !($point instanceof Point) ) {
				throw new InvalidBoxParameter( InvalidBoxParameter::BOXEXTEND );
			}
			if( !$this->contains( $point ) ) {
				$south[] = $point->lat();
				$north[] = $point->lat();
				$west[] = $point->lng();
				$east[] = $point->lng();
			}
		}

		if( $south ) { // es reicht aus, nur ein Array zu prüfen, alle sind gleich groß
			$south[] = $this->south();
			$north[] = $this->north();
			$west[] = $this->west();
			$east[] = $this->east();

			$south = min($south);
			$north = max($north);
			$west = min($west);
			$east = max($east);

			$this->nw = Point::byLatLng( [$north,$west] );
			$this->se = Point::byLatLng( [$south,$east] );
			$this->bounds = new Bounds( $this->nw->coord(), $this->se->coord() );
		}
		return $this;
	}

	/**
     *   Erweitert die Box so, dass der angegebene Punkt innerhalb der Box liegt
	 *
	 *	 Sofern der neue Punkt dazu führt, dass die Box 180° oder mehr breit werden würde,
	 *	 bricht die Methode mit einer Exception ab.
	 *   Im Erfolgsfall sind $this->nw, $this->se und $this->bounds anschließend neu gesetzt.
	 *
	 *   @param float			Umrechnungsfaktor für Höhe (und ggf. Länge)
	 *   @param null|float		Umrechnungsfaktor für Breite (oder null für Übernahme Höhenfaktor)
	 *   @param int				Referenzpunkt, default: Center
     *   @return \Geolocation\Calc\Box
     */
	public function resizeBy ( float $factorLat, ?float $factorLng=null, int $reference=self::HOOK_CE ): self
	{
		if( null === $factorLng ) {
			$factorLng = $factorLat;
		}

		// Faktoren müssen > 0 sein.
		if( $factorLat <= 0 ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOXRESIZELAT, [$factorLat] );
		}

		if( $factorLng <= 0 ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOXRESIZELNG, [$factorLng] );
		}

		// Abkürzung: wenn Faktor 1 dann unverändert
		if( abs( $factorLat - 1 ) < 0.0000001 && abs( $factorLng - 1 ) < 0.0000001) {
			return $this;
		}

		$deltaLat = $this->height() * $factorLat;
		$deltaLng = $this->width() * $factorLng;

		switch ($reference ) {
			case self::HOOK_NW:
				$north = $this->north();
				$west = $this->west();
				$south = $north - $deltaLat;
				$east = $west + $deltaLng;
				break;
			case self::HOOK_NE:
				$north = $this->north();
				$east = $this->east();
				$south = $north - $deltaLat;
				$west = $east - $deltaLng;
				break;
			case self::HOOK_SE:
				$south = $this->south();
				$east = $this->east();
				$north = $south + $deltaLat;
				$west = $east - $deltaLng;
				break;
			case self::HOOK_SW:
				$south = $this->south();
				$west = $this->west();
				$north = $south + $deltaLat;
				$east = $west + $deltaLng;
				break;
			case self::HOOK_CE:
				$deltaLat = $deltaLat / 2;
				$deltaLng = $deltaLng / 2;
				$center = $this->center();
				$south = $center->lat() - $deltaLat;
				$north = $center->lat() + $deltaLat;
				$west = $center->lng() - $deltaLng;
				$east = $center->lng() + $deltaLng;
				break;
			default:
				throw new InvalidBoxParameter( InvalidBoxParameter::BOXRESIZE, [$reference] );
		}

		// passen die neuen Grenzen?
		// Lat nur normalisieren, Lng als Fehler melden
		if( 180 <= ($east-$west) ) {
			throw new InvalidBoxParameter( InvalidBoxParameter::BOX2WIDE, [$east,$west,$east-$west] );
		}
		$north = Math::normalizeLatitude( $north );
		$south = Math::normalizeLatitude( $south );

		// neue Koordinaten übernehmen

		$this->nw = Point::byLatLng( [$north,$west] );
		$this->se = Point::byLatLng( [$south,$east] );
		$this->bounds = new Bounds( $this->nw->coord(), $this->se->coord() );

		return $this;
	}

	/**
     *   Erzeugt eine Kopie, bei der auch die internen Objekte geklont werden.
     */
	public function __clone()
	{
		$this->nw = Point::byLatLng( $this->nw->latLng() );
		$this->se = Point::byLatLng( $this->se->latLng() );
		$this->bounds = new Bounds( $this->nw->coord(), $this->se->coord() );
	}

}
