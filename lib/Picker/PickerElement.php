<?php

/**
 * rex_form-Element.
 *
 * Das Element stellt mittels des Location-Picker-Fragmentes die Karte dar und erleubt die Auswahl
 * einer Position/Koordinate über Klick, Drag, Adresseingabe, Koordinateneingabe
 *
 * Die Koordinate wird intern im eigenen Feld gespeichert als JSON-Array {"lat":...,"lng":...}) oder
 * in zwei externen Feldern jeweils als Zahl.
 *
 * Die Validierung (leer (optional) bzw. Grenzwerte) erfolgt automatisch über das Value auch für die
 * Variante mit externen Feldern. Daher keine Validatoren zusätzlich an die Felder pinnen!!!!
 *
 * Die Konfiguration erfolgt über das Picker-Fragment.
 *
 * Beispiel 1: mit interner Speicherung
 *
 *  $form = rex_form::factory(.....);
 *
 *  $field = $form->addField('', 'link', null, ['internal::fieldClass' => FriendsOfRedaxo\Geolocation\PickerElement::class], true);
 *  $field->setLabel('Koordinate');
 *
 *  $geoPicker = $field->setPickerWidget();
 *  $geoPicker->setMapset(2);
 *  $geoPicker->setGeoCoder(1);
 *  ...
 *
 * Beispiel 1: mit externen Feldern für Längengrad und Breitengrad
 *
 *  $form = rex_form::factory(.....);
 *
 *  $latField = $field = $this->addTextField('lat');
 *  $latField->setLabel('Breitengrad');
 *
 *  $lngField = $field = $this->addTextField('lng');
 *  $field->setLabel('Längengrad');
 *
 *  $field = $form->addField('', 'link', null, ['internal::fieldClass' => FriendsOfRedaxo\Geolocation\PickerElement::class], true);
 *  $field->setLabel('Koordinate');
 *
 *  $geoPicker = $field->setPickerWidget($latField, $lngField);
 *  $geoPicker->setMapset(2);
 *  $geoPicker->setGeoCoder(1);
 *  ...
 */

namespace FriendsOfRedaxo\Geolocation\Picker;

use FriendsOfRedaxo\Geolocation\Calc\Point;
use FriendsOfRedaxo\Geolocation\DeveloperException;
use rex_extension;
use rex_extension_point;
use rex_form_base;
use rex_form_element;
use rex_i18n;
use Throwable;

use function is_string;

class PickerElement extends rex_form_element
{
    protected bool $hasGeoPicker = false;
    protected PickerWidget $geoPicker;

    protected bool $internalValue = false;
    protected rex_form_element $latField;
    protected rex_form_element $lngField;

    protected bool $allowEmptyValue = false;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstruktorparameter
    public function __construct($tag = '', ?rex_form_base $table = null, array $attributes = [])
    {
        parent::__construct('', $table, $attributes);
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Konfiguration des Pickers durch Konfigurationsmethoden. Dazu die Picker-Instanz       ┃
    // ┃   anlegen und dann als Objejt nach außerhalb geben. Danach können die üblichen Methoden ┃
    // ┃   direkt darauf angewandt werden.                                                       ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * @api
     */
    public function allowEmptyValue(bool $status = true): void
    {
        $this->allowEmptyValue = $status;
    }

    /**
     * Initialisiert das PickerWidget.
     *
     * Es müssen entweder die externen Feld-Elemente angegeben sein (beide, Lat und Lng)
     * oder eben beide nicht (dann interne Felder aufbauen)
     *
     * Einige Einstellungen (insb. die Validatoren) können durch weitere Parametrisierungen de PickerWidgets
     * folgen. Der finale Feldaufbau erfolgt daher verzögert im EP REX_FORM_GET
     */
    public function setPickerWidget(?rex_form_element $latField = null, ?rex_form_element $lngField = null, bool $updateExternalFields = false): PickerWidget
    {
        if ($this->hasGeoPicker) {
            return $this->geoPicker;
        }

        // Interne Felder aufbauen, intern speichern
        if (null === $latField && null === $lngField) {
            $this->internalValue = true;
            $baseInputName = $this->getAttribute('name');
            $this->geoPicker = PickerWidget::factoryInternal($baseInputName . '[lat]', $baseInputName . '[lng]');
            $this->hasGeoPicker = true;
            rex_extension::register('REX_FORM_GET', $this->adjustInternalField(...));
            return $this->geoPicker;
        }

        // Externe Felder konfigurieren, die Felder speichern selbst
        if (null !== $latField && null !== $lngField && $latField !== $lngField) {
            $this->internalValue = false;
            $this->latField = $latField;
            $this->lngField = $lngField;
            $this->geoPicker = PickerWidget::factoryExternal($latField->getAttribute('id'), $lngField->getAttribute('id'));
            $this->hasGeoPicker = true;
            rex_extension::register('REX_FORM_GET', $this->adjustExternalField(...), params: ['update' => $updateExternalFields]);
            return $this->geoPicker;
        }

        throw new DeveloperException('setPickerWidget expects zero or two different field-elements');
    }

    /**
     * Nachdem alle Parameter des Pickers eingegeben sind - erkennbar daran, dass das Formular
     * nun aufgebaut wird (EP REX_FORM_GET) - werden die Validatoren hinzugefügt.
     *
     * Warum erst jetzt? Weil erst jetzt wesentliche Zahlen bereitstehen, die in die Meldungstexte
     * einfließen.
     *
     * Die Validierung erfolgt summarisch und ist mehr oder weniger überflüssig, da das Eingabefeld
     * im Browser schon auf "numerisch" und die Einhaltung der Grenzwerte prüft.
     *
     * @param rex_extension_point<rex_form_base> $ep
     */
    protected function adjustInternalField(rex_extension_point $ep): void
    {
        // sicherstellen, dass das richtige Formular am Start ist
        if ($ep->getSubject() !== $this->table) {
            return;
        }

        $range = $this->geoPicker->getLocationMarkerRange();

        // Technisch ohne Auswirkungen, da nie leer, aber die Existenz des Validator führt zur Markierung des Feldes
        if (!$this->allowEmptyValue) {
            $this->validator->add('notEmpty', 'xx');
        }
        $this->validator->add(
            'custom',
            rex_i18n::msg('geolocation_rf_geopicker_lat_err1', $this->getLabel() . ' | ' . rex_i18n::msg('geolocation_lat_label'), $range['minLat'], $range['maxLat']),
            $this->validateLatInternal(...),
        );
        $this->validator->add(
            'custom',
            rex_i18n::msg('geolocation_rf_geopicker_lng_err1', $this->getLabel() . ' | ' . rex_i18n::msg('geolocation_lng_label'), $range['minLng'], $range['maxLng']),
            $this->validateLngInternal(...),
        );
    }

    /**
     * Nachdem alle Parameter des Pickers eingegeben sind - erkennbar daran, dass das Formular
     * nun aufgebaut wird (EP REX_FORM_GET) - werden die Validatoren hinzugefügt.
     *
     * Warum erst jetzt? Weil erst jetzt wesentliche Zahlen bereitstehen, die in die Meldungstexte
     * einfließen.
     *
     * "NotEmpty" kann man nur mit dem internen Validator abprüfen.
     * Custom-Validatoren werden systemseitig nur auf nicht-leere Felder aktiviert.
     *
     * @param rex_extension_point<rex_form_base> $ep
     */
    protected function adjustExternalField(rex_extension_point $ep): void
    {
        // sicherstellen, dass das richtige Formular am Start ist
        if ($ep->getSubject() !== $this->table) {
            return;
        }

        $range = $this->geoPicker->getLocationMarkerRange();

        $latMsg = rex_i18n::msg('geolocation_rf_geopicker_lat_err1', $this->latField->getLabel(), $range['minLat'], $range['maxLat']);
        $lngMsg = rex_i18n::msg('geolocation_rf_geopicker_lng_err1', $this->lngField->getLabel(), $range['minLng'], $range['maxLng']);
        if (!$this->allowEmptyValue) {
            $this->latField->validator->add('notEmpty', $latMsg);
            $this->lngField->validator->add('notEmpty', $lngMsg);
        }
        $this->latField->validator->add('custom', $latMsg, $this->validateLatExternal(...));
        $this->lngField->validator->add('custom', $lngMsg, $this->validateLngExternal(...));

        /**
         * Optional zusätzliche Input-Attribute, damit analog zu internen Feldern Grenzwerte etc beachtet werden.
         */
        if (true === $ep->getParam('update', false)) {
            $this->latField->setAttribute('min', $range['minLat']);
            $this->latField->setAttribute('max', $range['maxLat']);
            $this->latField->setAttribute('type', 'number');
            $this->latField->setAttribute('step', 'any');
            $this->latField->setAttribute('autocomplete', 'off');
            $this->latField->setAttribute('placeholder', rex_i18n::msg('geolocation_picker_lat_placeholder'));
            $this->lngField->setAttribute('min', $range['minLng']);
            $this->lngField->setAttribute('max', $range['maxLng']);
            $this->lngField->setAttribute('type', 'number');
            $this->lngField->setAttribute('step', 'any');
            $this->lngField->setAttribute('autocomplete', 'off');
            $this->lngField->setAttribute('placeholder', rex_i18n::msg('geolocation_picker_lng_placeholder'));
            if ($this->geoPicker->isInputRequired()) {
                $this->latField->setAttribute('required', 'required');
                $this->lngField->setAttribute('required', 'required');
            }
        }
    }

    /**
     * Der Feldwert wird intern als Array geführt ['lat'=>,'lng'=>].
     *
     * Wird ein String übergeben, handelt es sich vermutlich um die gespeicherte Version als
     * JSON-Array und muss umgewandelt werden. Wenn das schief geht oder wenn als Wert null
     * übergeben wird (für komplett neu), wird ein Array ohne Werte zugewiesen.
     *
     * @param string|list<float>|null $value
     * @return void
     */
    public function setValue($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (null === $value) {
            $value = [
                'lat' => '',
                'lng' => '',
            ];
        }
        $this->value = $value;
    }

    /**
     * Liefert den zu speichernden Wert für das Feld bei interner Speicherung
     * Konkret wird als JSON-Array gespeichert.
     *
     * Da mit SetValue ab Start ein Array sichergestellt wird, muss es nicht
     * hinterfragt werden. (hoffentlich)
     *
     * @return string
     */
    public function getSaveValue()
    {
        $value = $this->getValue();
        return json_encode($value);
    }

    /**
     * Der aktuelle Feldwert wird bei internen Feldern bereits als Array vorgehalten.
     *
     * Damit wird dann der $geoPicker aufgebaut. Dessen übrige Parameter
     * wurden über entsprechende Methoden eingefügt.
     */
    public function formatElement()
    {
        $geoPicker = $this->setPickerWidget();

        if ($this->internalValue) {
            $value = $this->getValue();
            $geoPicker->setValue($value['lat'], $value['lng']);
        } else {
            $value = [
                'lat' => $this->latField->getValue(),
                'lng' => $this->lngField->getValue(),
            ];

            try {
                $location = Point::factory($value, 'lat', 'lng');
            } catch (Throwable $th) {
                $location = null;
            }
            $geoPicker->setLocation($location);
        }

        return $geoPicker->parse();
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Validierung der Eingaben bei externen Lat/Lng-Feldern                                 ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * Validierung des externen Breitengrades.
     *
     * Auf leer wurde bereits geprüft. Zusätzlich prüfen auf numerisch und den zulässigen Wertebereich
     * Falls leere Werte vorkommen, sind sie also erlaubt; in dem Fall aussteigen ohne weitere Prüfungen
     *
     * @param string $value
     */
    protected function validateLatExternal($value): bool
    {
        $value = trim($value);
        if ('' === $value) {
            $this->latField->setValue($value);
            return true;
        }

        // Numerisch?
        if (!is_numeric($value)) {
            return false;
        }
        $value = (float) $value;
        $this->latField->setValue($value);

        // In den zulässigen Grenzen?
        $bounds = $this->setPickerWidget()->getLocationMarkerRange();
        return $bounds['minLat'] <= $value && $value <= $bounds['maxLat'];
    }

    /**
     * Validierung des externen Breitengrades.
     *
     * Auf leer wurde bereits geprüft. Zusätzlich prüfen auf numerisch und den zulässigen Wertebereich
     * Falls leere Werte vorkommen, sind sie also erlaubt; in dem Fall aussteigen ohne weitere Prüfungen
     *
     * @param string $value
     */
    protected function validateLngExternal($value): bool
    {
        $value = trim($value);
        if ('' === $value) {
            $this->latField->setValue($value);
            return true;
        }

        // Numerisch?
        if (!is_numeric($value)) {
            return false;
        }
        $value = (float) $value;
        $this->latField->setValue($value);

        // In den zulässigen Grenzen?
        $bounds = $this->setPickerWidget()->getLocationMarkerRange();
        return $bounds['minLng'] <= $value && $value <= $bounds['maxLng'];
    }

    // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
    // ┃   Validierung der Eingaben bei internen Feldern                                         ┃
    // ┃   Diese Validierungen sollten eigentlich komplett unnötig sein und werden i.a.R.        ┃
    // ┃   zu einem positiven Ergebnis kommen. Die internen Felder sind mit Client-Validierung   ┃
    // ┃   und werden daher nur wohl  dann anspringen, wenn das Feld nicht leer sein darf.       ┃
    // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

    /**
     * Überprüft die Breitengrad-Komponente.
     *
     * - falls angefordert auf "nicht leer"
     * - numerisch
     * - im zulässigen Wertebereich
     *
     * $value ignorieren; über getValue() bekommt man den json-decodierten String
     */
    protected function validateLatInternal($value): bool
    {
        $val = $this->getValue();

        // Leer akzeptieren?
        $lat = $val['lat'];
        if ('' === $lat) {
            return $this->allowEmptyValue;
        }

        // Numerisch?
        if (!is_numeric($lat)) {
            return false;
        }
        $latVal = (float) $lat;
        $val['lat'] = $latVal;
        $this->setValue($val);

        // In den zulässigen Grenzen?
        $bounds = $this->setPickerWidget()->getLocationMarkerRange();
        return $bounds['minLat'] <= $latVal && $latVal <= $bounds['maxLat'];
    }

    /**
     * Überprüft die Längengrad-Komponente.
     *
     * - falls angefordert auf "nicht leer"
     * - numerisch
     * - im zulässigen Wertebereich
     *
     * $value ignorieren; über getValue() bekommt man den json-decodierten String
     */
    protected function validateLngInternal($value): bool
    {
        $val = $this->getValue();

        // Leer akzeptieren?
        $lng = $val['lng'];
        if ('' === $lng) {
            return $this->allowEmptyValue;
        }

        // Numerisch?
        if (!is_numeric($lng)) {
            return false;
        }
        $lngVal = (float) $lng;
        $val['lng'] = $lngVal;
        $this->setValue($val);

        // In den zulässigen Grenzen?
        $bounds = $this->setPickerWidget()->getLocationMarkerRange();
        return $bounds['minLng'] <= $lngVal && $lngVal <= $bounds['maxLng'];
    }
}
