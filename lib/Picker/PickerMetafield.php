<?php

/**
 * Eine Toolklasse für Metafelder auf Basis des Picker-Widgets.
 */

namespace FriendsOfRedaxo\Geolocation\Picker;

use FriendsOfRedaxo\Geolocation\Calc\Point;
use rex_extension_point;
use rex_fragment;
use Throwable;

use function call_user_func;
use function is_callable;

class PickerMetafield
{
    public const META_FIELD_TYPE = 'LocationPicker (Geolocation)';

    /**
     * Baut im EP METAINFO_CUSTOM_FIELD den Formularcode für einen Location-Picker ein.
     *
     * @param rex_extension_point<array<mixed>> $ep
     */
    public static function createMetaField(rex_extension_point $ep): void
    {
        $subject = $ep->getSubject();
        if (self::META_FIELD_TYPE !== $subject['type']) {
            return;
        }

        /**
         * Die aktuelle Koordinate abrufen und aus gültigen Werten
         * einen Point erzeugen.
         */
        try {
            $location = Point::byLatLng($subject['values']);
        } catch (Throwable $th) {
            $location = null;
        }

        /**
         * Das Picker-Fragment initialisieren;
         * Zwei auf dem Feldnamen basierende Eingabeelemente zur Verfügung stellen
         * Den aktuellen Wert bzw. null zuweisen.
         */
        $baseName = str_replace('rex-metainfo-', '', $subject[3]);
        $picker = PickerWidget::factoryInternal($baseName . '[lat]', $baseName . '[lng]')
            ->setLocation($location);

        /**
         * Wenn Params gefüllt ist, muss es ein Callback sein, der das Picker-Widget formatiert.
         */
        /** @var rex_sql $sql */
        $sql = $subject['sql'];
        $callback = trim($sql->getValue('params'));
        if ('' < $callback && is_callable($callback)) {
            call_user_func($callback, $picker);
        }

        /**
         * den Formulareintrag (DL / DT / DD) erzeugen.
         */
        $elements = [
            [
                'label' => $subject['4'],
                'field' => $picker->parse(),
            ],
        ];
        $fragment = new rex_fragment();
        $fragment->setVar('elements', $elements, false);
        $subject[0] = $fragment->parse('core/form/form.php');

        dump(get_defined_vars());
        $ep->setSubject($subject);
    }
}
