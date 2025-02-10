<?php

/**
 * Auf rex-fragment aufsetzende Fragment-Schnittstelle mit einem toleranteren
 * Interface als setVar/getVar.
 *
 * Die Parse-Methode ignoriert angegebene Fragment-Dateien und ruft stets
 * `geolocation/picher.php' auf.
 *
 * Die Methoden hier liefern dem Freagment sichere Werte auch unter Berücksichtigung der
 * Fallbacks und Defaults. Daher müssen im Fragment i.a.R. werder Typ-Überprüfungen
 * durchgeführt noch noch auf Default/Fallback umgeschaltet werden
 */

namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\Point;
use rex_config;
use rex_fragment;
use Throwable;

use function count;
use function is_string;
use function sprintf;

use const PREG_SPLIT_NO_EMPTY;

class PickerWidget extends rex_fragment
{
    protected string $type = '';

    protected string $latFieldId = '';
    protected string $lngFieldId = '';
    protected string $latFieldName = '';
    protected string $lngFieldName = '';
    protected bool $adjustAttributes = false;

    protected ?Point $initialLocation = null;

    /** @var array<string> */
    protected array $containerClass = [];
    protected string $containerId = '';

    /** @var array<string> */
    protected array $mapsetClass = [];
    protected string $mapsetId = '';
    protected int $mapset = 0;

    protected int $lmRadius = 0;
    /** @var array<string, mixed> */
    protected array $lmStyle = [];
    protected ?Box $markerRange = null;
    protected Box $voidBounds;
    protected ?int $geoCoder = null;
    /** @var array<string,string> */
    protected array $addressFields = [];
    protected string $latError = '';
    protected string $lngError = '';
    protected string $latErrorClass = '';
    protected string $lngErrorClass = '';

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Methoden zum Anlegen der Instanz                                                      ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    final protected function __construct(string $type)
    {
        parent::__construct();
        $this->type = $type;
        $this->lmRadius = (int) rex_config::get(ADDON, 'picker_radius');
        $this->voidBounds = $this->assertBaseBounds();
    }

    /**
     * Die Instanz reagiert auf Eingaben in einem Lat- und einem Lng-Feld außerhalb des eigenen
     * HTML-Containers. Dafür wird die ID der Felder benötigt.
     *
     * Der aktuelle Wert wird aber nicht aus den feldern ausgelesen. Er muss zusätzlich mit
     * setLocation(...) übermittelt werden (siehe dort)
     *
     * NOTE: geplant ....
     * $adjustAttributes erlaubt dem Widget, die Attribute der extrernen Eingabefelder zu überschreiben
     *  - type = "number"
     *  - max = "..."
     *  - min = "...
     *  - ...
     *
     * @api
     */
    public static function factoryExternal(string $latId, string $lngId, bool $adjustAttributes = false): static
    {
        if ('' === trim($latId) || '' === trim($lngId)) {
            throw new DeveloperException('Expected parameters $latId and $lngId must not be empty!');
        }
        $itsMe = new static('external');
        $itsMe->latFieldId = trim($latId);
        $itsMe->lngFieldId = trim($lngId);
        $itsMe->adjustAttributes = $adjustAttributes;
        return $itsMe;
    }

    /**
     * Die Instanz legt ihre eigenen Eingabefelder für Lat- und einem Lng-Werte innerhalb des eigenen
     * HTML-Containers an. Dafür wird der HTML-Name der Felder benötigt, damit die im Formular
     * zurückgegebenen Daten auch Context-grecht weiterverarbeitet werden können. Module z.B.
     * machen das anders als YForm ...
     *
     * Die ID kann man angeben. Aus Widget-Sicht ist die Angabe nicht notwendig, weil für
     * die internen Felder die ID zwar notwendig ist, aber ggf. selbst erzeugt wird.
     *
     * Der aktuelle Wert wird aber nicht aus den Feldern ausgelesen. Er muss zusätzlich mit
     * setLocation(...) übermittelt werden (siehe dort)
     *
     * @api
     */
    public static function factoryInternal(string $latName, string $lngName, string $latId = '', string $lngId = ''): static
    {
        if ('' === trim($latName) || '' === trim($lngName)) {
            throw new DeveloperException('Expected parameters $latName and $lngName must not be empty!');
        }
        $itsMe = new static('internal');
        $itsMe->latFieldName = trim($latName);
        $itsMe->lngFieldName = trim($lngName);
        $baseId = uniqid('geolocation-picker-');
        $itsMe->latFieldId = '' === $latId ? sprintf('%s-lat', $baseId) : $latId;
        $itsMe->lngFieldId = '' === $lngId ? sprintf('%s-lng', $baseId) : $lngId;
        return $itsMe;
    }

    /**
     * Gibt dem Widget vorab bereits den aktuellen Wert der Felder bekannt. Das ermöglich der
     * Karte, bereits ab Start korrekt positioniert zu sein. Anders wäre es mit dem Geolocation-
     * Mapset unnötig kompliziert. Sollte eigentlich kein Problem sein, die Werte zu haben.
     *
     * Wenn nicht aufgerufen bzw. ohne Wert, startet die Karte unabhängig von realen Feldwerten mit
     * dem Auschnitt, der via setBaseBounds(...) oder über die Geolocation-Konfiguration gesetzt ist
     *
     * So gesehen: technisch optional, aber logisch mandatory
     *
     * @api
     */
    public function setLocation(?Point $location = null): static
    {
        $this->initialLocation = $location;
        return $this;
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Optionale Methoden zur Konfiguration des Widget bzw. seines Verhaltens                ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * Optional: Attribute für den Widget-Container.
     *
     * Nur zwei Attribute sind vorgesehen:
     * $id      eindeutige HTML-Id
     * $class   Klassennamen als Array oder String
     *
     * @api
     * @param array<string>|string $class
     */
    public function setContainer(string $id = '', array|string $class = []): static
    {
        if (is_string($class)) {
            $class = preg_split('@\s+@', $class, flags: PREG_SPLIT_NO_EMPTY);
        }
        $this->containerId = $id;
        $this->containerClass = array_unique($class);
        return $this;
    }

    /**
     * Optional: ID des Geolocation-Kartensatzes.
     *
     * Ansonsten wird der Default-Kartensatz benutzt.
     *
     * Die zusätzliche Angabe ID für das Karten-Element ist nur erforderlich, wenn man
     * es zum Auffindne der Karte im DOM benötigt (was aber auch anders ginge.)
     *
     * Die zusätzliche Angabe einer Klasse ist nur erforderlich, wenn der Container
     * ein anderes Layout bekommen soll, als im Default.
     *
     * @api
     * @param array<string>|string $class
     */
    public function setMapset(int $mapset = 0, string $id = '', array|string $class = []): static
    {
        if (is_string($class)) {
            $class = preg_split('@\s+@', $class, flags: PREG_SPLIT_NO_EMPTY);
        }
        $this->mapset = max(0, $mapset);
        $this->mapsetId = $id;
        $this->mapsetClass = $class;
        return $this;
    }

    /**
     * Optional: Kartenauschnitt für den Fall, dass es keine ausgewählte Position gibt.
     *
     * Wenn hier kein Wert gesetzt ist, wird die in der Geolocation-Konfiguration
     * eingestellte Karte benutzt
     *
     * (Obacht: eine Box darf maximal 180° breit sein.)
     *
     * @api
     */
    public function setBaseBounds(?Box $voidBounds = null): static
    {
        $this->voidBounds = $this->assertBaseBounds($voidBounds);
        return $this;
    }

    /**
     * Optional: Location-Marker stylen.
     *
     * Bis auf die Mindestgröße des Marker-Radius wird nichts weiter überprüft.
     * Ein Wert von 0 wird später vom Fragment automatisch durch den Default
     * ersetzt.
     *
     * Wenn das JS später mit dem $style-Array nichts anfangen kann ... Pech gehabt.
     *
     * optional; Fallback auf die Werte für Radius und den Leaflet-internen Style
     *
     * @api
     * @param array<mixed> $style
     */
    public function setLocationMarker(int $radius = 0, array $style = []): static
    {
        $minRadius = (int) rex_config::get(ADDON,'picker_min_radius');
        if ($radius <= 0) {
            $radius = rex_config::get(ADDON, 'picker_radius');
        } elseif (0 < $radius && $radius < $minRadius) {
            throw new DeveloperException(sprintf('Minimum picker-radius is %d meter',$minRadius));
        }
        $this->lmRadius = $radius;
        $this->lmStyle = $style;
        return $this;
    }

    /**
     * Optional: Wertebereich für gültige Marker.
     *
     * Damit wird wenn möglich dem Input für LatLng bereits ein Min/Max mitgegeben.
     * Es wird nämlich in aller Regel keine weltweite Positionierung geben.
     *
     * (Obacht: eine Box darf maximal 180° breit sein.)
     *
     * Keine Angabe: es gelten die normalen Grenzen (-90...+90 btw. -180...+180)
     *
     * @api
     */
    public function setLocationRange(?Box $range = null): static
    {
        $this->markerRange = $range;
        return $this;
    }

    /**
     * Optional: GeoCoder, mit dem AdressDaten über einen WebService in Koordinaten
     * umgerechnet werden.
     *
     * Wenn kein GeoCoder angegeben ist, wird die Funktionalität nicht eingebaut.
     * 0 oder eine andere ungültige ID führen zum Default-GeoCoder.
     * NULL bedeutet: kein GeoCoder
     *
     * @api
     */
    public function setGeoCoder(?int $geoCoder = null): static
    {
        $this->geoCoder = $geoCoder;
        return $this;
    }

    /**
     * Optional: IDs von Eingabefeldern (inputTags), aus denen der GeoCoder
     * die Texte übernimmt (Statt die Adresse noch mal einzugeben.).
     *
     * Die Angabe ist sinnlos, wenn eh kein GeoCoder gesetzt ist.
     *
     * Es muss ein Array key/value angegeben werden mit ded ID als Key und dem
     * Feld-LAbel als velue. Warum: der Button zu dieser Funktionalität hat als
     * Hinweistext auch die Feld-Label).
     *
     * @api
     * @param array<string, string> $addressFields
     */
    public function setAdressFields(array $addressFields = []): static
    {
        if (count($addressFields) !== count(array_filter($addressFields, is_scalar(...)))) {
            throw new DeveloperException('Parameter $addressFields must have a key/value-structure with scalar values only');
        }
        $this->addressFields = $addressFields;
        return $this;
    }

    /**
     * Übermittelt eine Fehlermeldung. Bei internen Feldern wird die
     * Fehlermeldung möglichst am Lat-Feld ausgegeben und das Feld optisch
     * entsprechend markiert.
     *
     * keine ErrorClass: kein Fehler; Text hin oder her
     * nur ErrorClass: Feld mit der Fehlerklasse auszeichnen; die Meldung ist möglicherweise über dem Formular
     * Beides angegeben: Fehlerklasse am Feld, Text unter dem Feld
     *
     * @api
     */
    public function setLatError(string $errorClass, string $error = ''): static
    {
        $this->latError = $error;
        $this->latErrorClass = $errorClass;
        return $this;
    }

    /**
     * Übermittelt eine Fehlermeldung. Bei internen Feldern wird die
     * Fehlermeldung möglichst am Lng-Feld ausgegeben und das Feld optisch
     * entsprechend markiert.
     *
     * keine ErrorClass: kein Fehler; Text hin oder her
     * nur ErrorClass: Feld mit der Fehlerklasse auszeichnen; die Meldung ist möglicherweise über dem Formular
     * Beides angegeben: Fehlerklasse am Feld, Text unter dem Feld
     *
     * @api
     */
    public function setLngError(string $errorClass, string $error = ''): static
    {
        $this->lngError = $error;
        $this->lngErrorClass = $errorClass;
        return $this;
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Mit den Gettern ruft das Fragment in sich konsistente Informationen ab, die direkt    ┃
    // ┃   und ohne weitere Prüfung zum Aufbau des HTML genutzt werden können                    ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * Die Information, ob interne Felder gerendert werden müssen oder nicht.
     *
     * @api
     */
    public function useExternalInput(): bool
    {
        return 'external' === $this->type;
    }

    /**
     * Liefert dem Fragment ein valides Mapset-Objekt.
     *
     * Die optionale Klasse bzw. ID ist auch schon eingebaut.
     *
     * @api
     */
    public function getMapset(): Mapset
    {
        $mapset = Mapset::take($this->mapset);
        if ('' < $this->mapsetId) {
            $mapset->attributes('id', $this->mapsetId);
        }
        if ('' < $this->mapsetClass) {
            $mapset->attributes('class', implode(' ', $this->mapsetClass));
        }
        return $mapset;
    }

    /**
     * Gibt die Information, ob das Widget mit oder ohne Geocodierung ausgegeben werden soll.
     *
     * @api
     */
    public function hasGeoCoding(): bool
    {
        return null !== $this->geoCoder;
    }

    /**
     * Liefert dem Fragment stets ein valides GeoCoder-Objekt.
     * Falls die Unterscheidung auf "mit oder ohne Geocoder" benötigt
     * wird: vorher abfragen mit ->hasGeoCoding().
     *
     * @api
     */
    public function getGeoCoder(): GeoCoder
    {
        return GeoCoder::take($this->geoCoder);
    }

    /**
     * liefert das Array mit den Referenzen auf externe Adressen-Felder als GeoCoder-Input.
     *
     * @api
     * @return array<string, string>
     */
    public function getAddressFields(): array
    {
        return $this->addressFields;
    }

    /**
     * @api
     */
    public function getContainerId(): string
    {
        return $this->containerId;
    }

    /**
     * Gibt die Container-Klasse zurück, ggf. zusammengeführt mit weiteren Klassen
     * aus dem Parameter $moreClasses. Doppelte Namen werden eliminiert.
     *
     * @api
     * @param array<string> $moreClasses
     * @return array<string>
     */
    public function getContainerClass(array $moreClasses = []): array
    {
        $classes = array_merge($moreClasses, $this->containerClass);
        $classes = array_unique($classes);
        return $classes;
    }

    /**
     * Übermittelt die ID(s) der Eingabefelder.
     *
     * @api
     * @return string|array<string, string>
     */
    public function getFieldId(string $key = ''): string|array
    {
        switch ($key) {
            case 'lat': return $this->latFieldId;
            case 'lng': return $this->lngFieldId;
            case '': return [
                'lat' => $this->latFieldId,
                'lng' => $this->lngFieldId,
            ];
        }
        throw new DeveloperException(sprintf('Invalid key «%s»; ust «lat» or «lng»', $key));
    }

    /**
     * Übermitelt die Input-Namen der Eingabefelder.
     *
     * @api
     * @return string|array<string, string>
     */
    public function getFieldName(string $key = ''): string|array
    {
        if (!$this->useExternalInput()) {
            switch ($key) {
                case 'lat': return $this->latFieldName;
                case 'lng': return $this->lngFieldName;
                case '': return [
                    'lat' => $this->latFieldName,
                    'lng' => $this->lngFieldName,
                ];
            }
            throw new DeveloperException(sprintf('Invalid key «%s»; ust «lat» or «lng»', $key));
        }
        throw new DeveloperException('Dont use this function in an "external fields" context');
    }

    /**
     * Übermittelt die Koordinaten der Basiskarte. Es ist immer ein gültiger
     * Kartenauschnitt, ggf. aus dem Fallback auf die Geolocation-Basiskarte.
     * @api
     */
    public function getBaseBounds(): Box
    {
        return $this->voidBounds;
    }

    /**
     * @api
     */
    public function getLocationMarkerRadius(): int
    {
        return $this->lmRadius;
    }

    /**
     * @api
     * @return array<string, mixed>
     */
    public function getLocationMarkerStyle(): array
    {
        return $this->lmStyle;
    }

    /**
     * @api
     * @return array<string, float|int>
     */
    public function getLocationMarkerRange(): array
    {
        if (null === $this->markerRange) {
            return [
                'minLat' => -90,
                'maxLat' => 90,
                'minLng' => -180,
                'maxLng' => 180,
            ];
        }
        return [
            'minLat' => $this->markerRange->south(),
            'maxLat' => $this->markerRange->north(),
            'minLng' => $this->markerRange->west(),
            'maxLng' => $this->markerRange->east(),
        ];
    }

    /**
     * Bei initialLocation === null würde die Default-Karte angezeigt.
     * @api
     */
    public function hasLocationMarkerPosition(): bool
    {
        return null !== $this->initialLocation;
    }

    /**
     * liefert nur den initialen Breitengrad aus der initialLocation
     * bzw. den Default-Wert wenn sie null ist.
     *
     * @api
     */
    public function getLocationMarkerLat(?string $default = null): string|float|null
    {
        if ($this->hasLocationMarkerPosition()) {
            return $this->initialLocation->lat();
        }
        return $default;
    }

    /**
     * liefert nur den initialen Längengrad aus der initialLocation
     * bzw. den Default-Wert wenn sie null ist.
     *
     * @api
     */
    public function getLocationMarkerLng(?string $default = null): string|float|null
    {
        if ($this->hasLocationMarkerPosition()) {
            return $this->initialLocation->lng();
        }
        return $default;
    }

    /**
     * liefert nur die initiale Koordinate aus der initialLocation
     * bzwl den Wert von $default falls es initialLocation null ist.
     *
     * @api
     * @return string|array<float>|null
     */
    public function getLocationMarkerLatLng(?string $default = null): string|array|null
    {
        if ($this->hasLocationMarkerPosition()) {
            return $this->initialLocation->latLng();
        }
        return $default;
    }

    /**
     * Liefert den Location-Marker als Point-Objekt bzw. null.
     *
     * @api
     */
    public function getLocationMarker(): ?Point
    {
        return $this->initialLocation;
    }

    /**
     * @api
     */
    public function hasLatError(): bool
    {
        return '' < $this->latErrorClass;
    }

    /**
     * @api
     */
    public function getLatErrorClass(): string
    {
        return $this->latErrorClass;
    }

    /**
     * @api
     */
    public function getLatErrorMsg(): string
    {
        return $this->hasLatError() ? $this->latError : '';
    }

    /**
     * @api
     */
    public function hasLngError(): bool
    {
        return '' < $this->lngErrorClass;
    }

    /**
     * @api
     */
    public function getLngErrorClass(): string
    {
        return $this->lngErrorClass;
    }

    /**
     * @api
     */
    public function getLngErrorMsg(): string
    {
        return $this->hasLngError() ? $this->lngError : '';
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Das HTML erzeugen                                                                     ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * Der Parser benötigt abweichend vom normalen rex_fragment keinen ausdrücklichen Dateinamen.
     *
     * @api
     */
    public function parse($filename = 'geolocation/picker.php')
    {
        return parent::parse($filename);
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Helferlein                                                                            ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    protected function assertBaseBounds(?Box $bounds = null): Box
    {
        if (null === $bounds) {
            try {
                $v = sprintf('[%s]', rex_config::get(ADDON, 'map_bounds'));
                $b = json_decode($v, true);
                $bounds = Box::byCorner(Point::byLatLng($b[0]), Point::byLatLng($b[1]));
            } catch (Throwable $th) {
                throw new DeveloperException('Critical Error: rex_config-entry «' . ADDON . ' | map_bounds» expected but missing; call the config-page and save the entry again.');
            }
        }
        return $bounds;
    }
}
