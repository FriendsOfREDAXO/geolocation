<?php

/**
 * Fragment zum Aufbau des rex-map-HTML.
 *
 * Es finden nur formale Prüfungen statt. Fehlende Werte werden nicht durch Defaults ersetzt.
 * Hier wird aus Eingangsdaten HTML gebaut, mehr nicht.
 *
 * <rex-map dataset="..." mapset="..." map="..." ... ></rex-map>
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_fragment;
use rex_string;

use function count;
use function is_array;
use function is_string;

/** @var rex_fragment $this */

$classes = [];
$attributes = [];

// dataset muss ein Array sein
if (isset($this->dataset) && is_array($this->dataset)) {
    $attributes['dataset'] = json_encode($this->dataset);
}

// mapset muss ein Array sein
if (isset($this->mapset) && is_array($this->mapset)) {
    $attributes['mapset'] = json_encode($this->mapset);
}

// map (Kartenoptionen) muss ein Array sein
if (isset($this->map) && is_array($this->map)) {
    $attributes['map'] = json_encode($this->map);
}

// Klassen hinzufügen (string oder array)
if (isset($this->class)) {
    $class = $this->class;
    if (is_string($class)) {
        $class = explode(' ', $class);
    }
    if (is_array($class)) {
        $classes = array_merge($classes, $class);
    }
}
$classes = array_filter($classes, trim(...));
if (0 < count($classes)) {
    $attributes['class'] = array_unique($classes);
}

// sonstige Attribute hinzufügen (class,dataset,mapset,map ignorieren)

if (isset($this->attributes) && is_array($this->attributes) && 0 < count($this->attributes)) {
    $attributes = array_merge(
        $attributes,
        array_diff_key($this->attributes, ['mapset' => null, 'dataset' => null, 'map' => null, 'class' => null]),
    );
}

// HTML-Tag generieren
echo '<rex-map',rex_string::buildAttributes($attributes),'></rex-map>';
