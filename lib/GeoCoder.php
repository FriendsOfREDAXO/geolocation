<?php

/**
 * Die Klasse GeoCoder ist ein Vorgriff auf eine noch einzuführende Datenbank mit
 * Tool-URLs, konkret für GeoCoding.
 *
 * Momentan gibt es nur eine Service-Url: Nomination/OSM mit ID=1
 *
 * Diese Klasse stellt schon einmal die meisten Schnittstellen zur Verfügung, die die
 * Informationen des "Pseudo-Datenstzes" bereitstellen.
 *
 * ... in der Annahme, dass man dann später nur diese Klasse gegen eine ModelClass
 * austauschen muss.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_addon;
use rex_config;
use rex_context;
use rex_i18n;
use rex_logger;
use rex_request;
use rex_response;
use rex_url;

use function is_bool;
use function sprintf;

use const CURLINFO_CONTENT_TYPE;
use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_PROXY;
use const CURLOPT_REFERER;
use const CURLOPT_RETURNTRANSFER;
use const E_WARNING;

class GeoCoder
{
    protected const VALUE_PLACEHOLDER = '{value}';
    protected const VALUE_PARAM = 'v';
    protected int $id = 0;
    protected string $url = '';
    /** @var array<mixed> */
    protected array $mapping = [];
    protected string $copyright = '';
    protected string $name = '';

    /**
     * Die ID etc. ist im Moment noch sinnlos, da es nur einen GeoCoder gibt (Nominatim).
     *
     * Daher wird hier kein Datensatz eingelesen, sondern nur relevanten Felder
     * initialisiert.
     */
    public function __construct(int $id)
    {
        $this->id = $id;
        $defaultResolver = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q={value}';
        $this->url = rex_config::get('geolocation', 'resolver_url', $defaultResolver);
        $this->copyright = 'Nominatim/OSM ©️ <a href="http://openstreetmap.org/copyright">OpenStreetMap contributors</a>';
        $this->name = 'Nominatim / OpenStreetMap';

        $defaultResolverMapping = [
            'lat' => 'lat',
            'lng' => 'lon',
            'label' => 'display_name',
        ];
        $this->mapping = json_decode(rex_config::get('geolocation', 'resolver_mapping', ''), true) ?? $defaultResolverMapping;
    }

    /**
     * Simuliert den Abruf des yform-dataset der angegebenen ID.
     *
     * @api
     */
    public static function get(int $id): ?self
    {
        if (1 !== $id) {
            return null;
        }
        return new self($id);
    }

    /**
     * Analog zu Mapset::take
     * Liefert den Default (1) wenn es die angegebene Nummer nicht gibt.
     *
     * @api
     */
    public static function take(?int $id = null): self
    {
        try {
            if (null === $id) {
                throw new Exception('');
            }
            $geocoder = self::get($id);
            if (null === $geocoder) {
                throw new Exception('');
            }
        } catch (Exception $e) {
            $geocoder = self::get(self::getDefaultId());
            if (null === $geocoder) {
                throw new Exception('GeoCoder::take: Oops! Warum gibt es ID 1 nicht?');
            }
        }
        return $geocoder;
    }

    /**
     * @api
     */
    public static function getDefaultId(): int
    {
        return 1;
    }

    /**
     * @api
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Abruf der Provider-Url für den Abruf.
     * Der Platzhalter wird mit dem konkreten Suchbegriff ersetzt.
     *
     * @api
     */
    public function getUrl(?string $value = null): string
    {
        $value = rex_escape($value, 'url');
        return null === $value ? $this->url : str_replace(self::VALUE_PLACEHOLDER, $value, $this->url);
    }

    /**
     * Abruf des Provider-Copyright.
     *
     * @api
     */
    public function getCopyright(): string
    {
        return $this->copyright;
    }

    /**
     * Abruf des Anzeige-Namens.
     *
     * @api
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Abruf des Mappings der Url-Rückgaben auf Feldnamen, die im JS benutzt werden.
     * Das der Provider eine Liste von Datensätzen liefert, wird über dieses Mapping
     * beim Client eine Zuordnung der Rückmeldung auf HTML-Code durchgeführt.
     *
     * @api
     * @return array<mixed>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * Wegen Fake-Kompatibilität zu yform-datasets.
     *
     * @api
     * @return int|string|array<mixed>|null
     */
    public function getValue(string $name)
    {
        switch ($name) {
            case 'url': return $this->getUrl();
            case 'copyright': return $this->copyright;
            case 'mapping': return $this->getMapping();
            case 'id': return $this->getId();
            case 'name': return $this->getName();
        }
        return null;
    }

    /**
     * Baut die Url zusammen, mit der vom Client aus
     * die Abfrage zum Geocoding erfolgen soll.
     *
     * @api
     */
    public function getRequestUrl(): string
    {
        return rex_url::frontendController([
            KEY_GEOCODER => $this->id,
            self::VALUE_PARAM => self::VALUE_PLACEHOLDER,
        ]);
    }

    /**
     * Baut einen HTML-Tag zusammen, mit dem der Client die Rückmeldung
     * vom Provider darstellt. Standard-Platzhalter werden durch die Namen der
     * korrespondierenden Felder im Ergebnis-Datensatz ersetzt.
     *
     * @api
     */
    public function htmlMappingTag(string $html): string
    {
        $mapping = $this->getMapping();
        return preg_replace_callback(
            '@\{(\w+)\}@',
            static function ($param) use ($mapping) {
                return isset($mapping[$param[1]]) ? sprintf('{%s}', $mapping[$param[1]]) : $param[0];
            },
            $html,
        );
    }

    /**
     * Auswalliste für Choices.
     * GGf. wird ein Item "Default" vorangestellt.
     *
     * @api
     * @return array<string>
     */
    public static function getList(bool $addDefault = false): array
    {
        $list = [];
        if (true === $addDefault) {
            $list[0] = rex_i18n::msg('geolocation_yfv_geopicker_geocoder_c_default');
        }
        $geoCoder = self::get(1);
        $list[$geoCoder->getId()] = $geoCoder->getName();
        return $list;
    }

    /**
     * Wird aus boot.php aufgerufen und sendet "value" an die GeoCoder-URL.
     * Die Rückgabe wird als JSOM-Array zurückübermittelt an den Client.
     *
     * Kein Suchbegriff (value): da kann man sich die Suche sparen und sofort "leer" melden.
     * $geoCoderId ist ungültig: wir tun so, als ob es kein Suchergebnis gibt.
     *
     * Beim Versand der Ergebnisliste an den Client wird als TimeToLive die Zeit
     * von Jetzt bis Mitternacht eingestellt (Angabe in Minuten). Dann kann der Client
     * das Ergebnis cachen, aber nicht zu lang.
     *
     * @api
     * @return never
     */
    public static function sendResponse(int $geoCoderId): void
    {
        // Same Origin
        Tools::isAllowed();

        /**
         * Wenn es kein Value gibt ... abbrechen
         * Leeres Ergebnis senden.
         */
        $value = trim(rex_request::request(self::VALUE_PARAM, 'string', ''));
        if ('' === $value) {
            $ttl = (strtotime('today midnight') - time()) / 60;
            Tools::sendJson([], time(), $ttl);
        }

        /**
         * GeoCOder nicht gefunden. Hm?
         * Einfach "kein Suchergebnis" senden :-).
         */
        $geoCoder = self::get($geoCoderId);
        if (null === $geoCoder) {
            $ttl = (strtotime('today midnight') - time()) / 60;
            Tools::sendJson([], time(), $ttl);
        }

        /**
         * Daten abrufen. In der Url gibt es einen Platzhalter {value}.
         */
        $url = $geoCoder->getUrl($value);
        $ch = curl_init($url);
        if (is_bool($ch)) {
            Tools::sendInternalError();
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_REFERER, rex_request::server('HTTP_REFERER', 'string', ''));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (($proxy = rex_addon::get('geolocation')->getConfig('socket_proxy')) !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        $content = (string) curl_exec($ch);
        $returnCode = (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // keine verwertbare Antwort ...
        if ('0' === $returnCode) {
            $msg = sprintf('Geolocation: GeoCoder failed (cUrl Error %d / %s)', curl_errno($ch), curl_error($ch));
            rex_logger::logError(E_WARNING, $msg, __FILE__, __LINE__ - 8, rex_context::fromGet()->getUrl([], false) . ' ➜ ' . $url);
            Tools::sendInternalError();
        }

        // Da lief was schief. Antwort an den Client weiterleiten
        if ('200' !== $returnCode) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus($returnCode);
            rex_response::sendContent($content, $contentType);
            exit;
        }

        /**
         * Beim Versand wird als TimeToLive die Zeit von Jetzt bis Mitternacht
         * eingestellt (Angabe in Minuten).
         */
        $ttl = (strtotime('today midnight') - time()) / 60;
        Tools::sendJson(json_decode($content, true) ?? [], time(), $ttl);
    }

    /**
     * Where-Filter für die Umkreissuche.
     *
     * - Filter basierend auf zwei Feldern (lat und lng getrennt abgelegt)
     * - Filter basierend auf einem Feld (lat,lng)
     * - Filter basierend auf einem Feld (lng,lat)
     *
     * offensichtlich unsinnige Werte (z.B. negativer Radius) werden hier nicht rausgefiltert.
     * leere Felder werden als "außerhalb des Kreises" gewertet.
     *
     * @api
     */
    public static function circleSearch(float $lat, float $lng, int $radius, string $latField, string $lngField, string $alias = ''): string
    {
        $alias = '' === $alias ? $alias : ($alias . '.');
        $referencePoint = sprintf('point(%s,%s)', $lat, $lng);
        $point = sprintf('point(%s%s,%s%s)', $alias, $latField, $alias, $lngField);
        return sprintf('(%s > \'\' && %s > \'\' && ST_Distance_Sphere(%s,%s) <= %d)', $latField, $lngField, $referencePoint, $point, $radius);
    }

    /** @api */
    public static function circleSearchLatLng(float $lat, float $lng, int $radius, string $field, string $alias = ''): string
    {
        $field = '' === $alias ? $field : ($alias . '.' . $field);
        $referencePoint = sprintf('point(%s,%s)', $lat, $lng);
        $point = sprintf('point(SUBSTRING_INDEX(%s,\',\',1),SUBSTRING_INDEX(%s,\',\',-1))', $field, $field);
        return sprintf('(%s > \'\' && ST_Distance_Sphere(%s,%s) <= %d)', $field, $referencePoint, $point, $radius);
    }

    /** @api */
    public static function circleSearchLngLat(float $lat, float $lng, int $radius, string $field, string $alias = ''): string
    {
        $field = '' === $alias ? $field : ($alias . '.' . $field);
        $referencePoint = sprintf('point(%s,%s)', $lat, $lng);
        $point = sprintf('point(SUBSTRING_INDEX(%s,\',\',-1),SUBSTRING_INDEX(%s,\',\',1))', $field, $field);
        return sprintf('(%s > \'\' && ST_Distance_Sphere(%s,%s) <= %d)', $field, $referencePoint, $point, $radius);
    }
}
