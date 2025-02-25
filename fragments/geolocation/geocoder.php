<?php
/**
 * rex_fragment für einen Such-Input mit Adress-Auflösung via API.
 *
 *  geo_coder           GeoCoder-Datensatz mit den den nötigen Angaben
 *  address_fields      Array mit ID (key) und Label (value) von Input-Tags, die Adress-Teile liefern.
 *
 * Alle Werte sind optional:
 * - Als GeoCoder wird - wenn nicht angegeben (null) - der Default-Geocoder genommen
 * - Wenn es keine Adressen-Felder-IDs gibt,macht das nichts, denn wird der entsprechende
 *   Button eben nicht angelegt.
 *
 * Hinweis zum Code:
 * - HTML-Attribute werden i.d.R. per rex_string::buildAttributes aufbereitet.
 * - Sub-Elemente wie Input-Groups oder Button werden über die Core-Fragmente erzeugt
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_fragment;
use rex_i18n;
use rex_string;

/** @var rex_fragment $this */

/**
 * Variablen des Fragments abrufen bzw. auf Default setzen.
 *
 * @var ?GeoCoder $geoCoder
 */
$geoCoder = $this->getVar('geo_coder', null);
if (!is_a($geoCoder, GeoCoder::class)) {
    return;
}
$addressFields = $this->getVar('address_fields', []);

/**
 * Wenn es Suchfelder gibt, wird dazu ein Button angelegt, der aus den hinterlegten Feldern
 * die Werte abruft und in die Suchzeile einträgt.
 */
if (0 === \count($addressFields)) {
    $left = '<i class="rex-icon rex-icon-search"></i>';
} else {
    $i18nId = 1 === \count($addressFields) ? 'geolocation_geocoder_adress_btn' : 'geolocation_geocoder_adresses_btn';
    $button = [
        'label' => \sprintf('<span class="wide">%s</span><i class="rex-icon rex-icon-search"></i>', rex_i18n::msg($i18nId, '')),
        'attributes' => [
            'class' => ['btn btn-primary'],
            'type' => 'button',
            'title' => rex_i18n::msg('geolocation_geocoder_adr_title', implode(', ', $addressFields)),
        ],
    ];

    $fragment = new rex_fragment();
    $fragment->setVar('buttons', [$button], false);
    $left = $fragment->parse('core/buttons/button.php');
}

/**
 * Die Attribute für das Suchfeld zusammenstellen.
 */
$searchAttributes = [
    'class' => 'form-control',
    'type' => 'text',
    'value' => '',
    'placeholder' => rex_i18n::rawMsg('geolocation_geocoder_placeholder', strip_tags($geoCoder->getCopyright())),
    'autocomplete' => 'off',
];

/**
 * Aus den obigen Informationen die Input-Group mit dem Suchfeld unf ggf. Buttons zusammenschrauben.
 */
$fragment = new rex_fragment();
$n = [
    'left' => $left,
    'field' => '<input' . rex_string::buildAttributes($searchAttributes) . '/>',
];

$fragment->setVar('elements', [$n], false);
$searchInput = $fragment->parse('core/form/input_group.php');

/**
 * Die Attribute für <geolocation-geocoder-search> ztusammenstellen.
 *
 * Das Ergebnis der GeoCoder-Abfrage vom Client aus liefer i.d.R. ein
 * JSON-Array der gefundenen Einträge. Deren Feldnamen stimmen u.U. nicht mit
 * den im JS benutzen Namen überein. Im Tag, der die einzelnen Ergebnisse in
 * der Ergebnisliste darstellt, werden die Platzhalter gegen die Richtigen ausgetauscht.
 * Das passiert über eine GeoCoder-Methode, denn der GeoCoder kennt die Zuordnung.
 *
 * Auch die Abruf-Url für die Clients kann der GeoCoder liefern
 */
$searchResultItemTemplate = '<a href="#" class="list-group-item" lat="{lat}" lng="{lng}">{label}</a>';

$attr = [
    'geocoder' => $geoCoder->getRequestUrl(),
    'template' => $geoCoder->htmlMappingTag($searchResultItemTemplate),
    'addresslink' => json_encode(array_keys($addressFields)),
    'class' => 'form-group',
];

?>
<geolocation-geocoder-search <?= rex_string::buildAttributes($attr) ?> >
    <?= $searchInput ?>
    <div class="geolocation-geocoder-search-result">
        <div class="list-group hidden">
        </div>
    </div>
</geolocation-geocoder-search>
