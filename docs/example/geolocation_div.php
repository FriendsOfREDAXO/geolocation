<?php
/*
Fragment zum Aufbau des rex-map-HTML

Es finden nur formale Prüfungen statt. Fehlende Werte werden nicht durch Defaults ersetzt.
Hier wird aus Eingangsdaten HTML gebaut, mehr nicht.

<rex-map dataset="..." mapset="..." map="..."></rex-map>
*/
$classes = [];
$attributes = [];

// dataset muss ein Array sein
if( isset($this->dataset) && is_array($this->dataset) ) {
    $attributes['dataset'] = json_encode($this->dataset);
}

// mapset muss ein Array sein
if( isset($this->mapset) && is_array($this->mapset) ) {
    $attributes['mapset'] = json_encode($this->mapset);
}

// map (Kartenoptionen) muss ein Array sein
if( isset($this->map) && is_array($this->map) ) {
    $attributes['map'] = json_encode($this->map);
}

// Klassen hinzufügen (string oder array)
if( isset($this->class) ) {
    $class = $this->class;
    if( is_string($class) && $class) $class = explode(' ',$class);
    if( is_array($class) && $class ) $classes = array_merge($classes,$class);
}
if( $classes ) {
    $attributes['class'] = array_unique($classes);
}

// Schript-Code für Events einfügen
if( isset($this->events) && is_array($this->events) && count($this->events) ) {
    $code = '';
    $id = 'rm'.md5( microtime() );
    foreach( $this->events as $k=>$v ) {
        $exit = 'if( !e.detail.container.hasAttribute(\''.$id.'\') ) return;';
        $code .= 'document.addEventListener(\'geolocation.'.$k.'\', function(e){'.$exit.'console.log(e);'.$v.'});';
    }
    if( $code ) {
        echo '<script type="text/javascript">',$code,'</script>';
        $attributes[$id] = 1;
    }
}

// sonstige Attribute hinzufügen (class,dataset,mapset,map ignorieren)

if( isset($this->attributes) && is_array($this->attributes) && count($this->attributes) ) {
    $attributes = array_merge(
        $attributes,
        array_diff_key($this->attributes,['mapset'=>null,'dataset'=>null,'map'=>null,'class'=>null])
    );
}

// HTML-Tag generieren
$id = 'rm-' . md5(time());
?>
<script>
document.addEventListener('DOMContentLoaded', (event) => {
    let rex_map = document.querySelector( '[rex-map="<?= $id ?>"]' );
    if( rex_map ) {
        Geolocation.initMap( rex_map );
        return;
    }
    console.error( 'Geolocation: map-container "<?= $id ?>" not found.');
});
</script>
<?php
echo '<div rex-map="',$id,'" ',\rex_string::buildAttributes($attributes),'></div>';
