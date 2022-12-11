<?php
/**
 * Fragment zum Aufbau des rex-map-HTML als <div>.
 *
 * Es finden nur formale Prüfungen statt. Fehlende Werte werden nicht durch Defaults ersetzt.
 * Hier wird aus Eingangsdaten HTML gebaut, mehr nicht.
 *
 * <div rex-map dataset="..." mapset="..." map="..."></div>
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_fragment;

/**
 * @var rex_fragment $this
 */

$classes = [];
$attributes = [];

// dataset muss ein Array sein
if (isset($this->dataset) && \is_array($this->dataset)) {
    $attributes['dataset'] = json_encode($this->dataset);
}

// mapset muss ein Array sein
if (isset($this->mapset) && \is_array($this->mapset)) {
    $attributes['mapset'] = json_encode($this->mapset);
}

// map (Kartenoptionen) muss ein Array sein
if (isset($this->map) && \is_array($this->map)) {
    $attributes['map'] = json_encode($this->map);
}

// Klassen hinzufügen (string oder array)
if (isset($this->class)) {
    $class = $this->class;
    if (\is_string($class)) {
        $class = explode(' ', $class);
    }
    if (\is_array($class)) {
        $classes = array_merge($classes, $class);
    }
}
$classes = array_filter($classes, 'trim');
if (0 < \count($classes)) {
    $attributes['class'] = array_unique($classes);
}

// Schript-Code für Events einfügen
if (isset($this->events) && \is_array($this->events) && 0 < \count($this->events)) {
    $code = '';
    $id = 'rm'.md5(microtime());
    foreach ($this->events as $k => $v) {
        $exit = 'if( !e.detail.container.hasAttribute(\''.$id.'\') ) return;';
        $code .= 'document.addEventListener(\'geolocation.'.$k.'\', function(e){'.$exit.'console.log(e);'.$v.'});';
    }
    if ('' < $code) {
        echo '<script type="text/javascript">',$code,'</script>';
        $attributes[$id] = 1;
    }
}

// sonstige Attribute hinzufügen (class,dataset,mapset,map ignorieren)
if (isset($this->attributes) && \is_array($this->attributes) && 0 < \count($this->attributes)) {
    $attributes = array_merge(
        $attributes,
        array_diff_key($this->attributes, ['mapset' => null, 'dataset' => null, 'map' => null, 'class' => null])
    );
}

// HTML-Tag generieren
$id = 'rm-' . md5(microtime());
?>
<script>
document.addEventListener('DOMContentLoaded', (event) => {
    let rex_map = document.querySelector( '[rex-map="<?= $id ?>"]' );
    if( rex_map ) {
        Geolocation.initMap( rex_map );
        return;
    }
});
</script>
<?php
echo '<div rex-map="',$id,'" ',\rex_string::buildAttributes($attributes),'></div>';
