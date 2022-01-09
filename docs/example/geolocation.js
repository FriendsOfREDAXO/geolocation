/* Geolocation Custom-JS */

/* im BE bounds zu Kontrollzwecken sichtbar machen */
if( 'object'===typeof(rex) && true===rex.backend){
    Geolocation.default.boundsRect = {
        color:"#ff7800",
        weight:1,
        fillOpacity:0.1
    };
}

/* Tool "nrmarker" */
Geolocation.Tools.Nrmarker = class extends Geolocation.Tools.Marker
{
    setValue( markerArray )
    {
        super.setValue( markerArray );
        if( this.map ) {
            let map = this.map;
            this.remove();
            this.map = map;
        }
        this.marker = [];
        markerArray.forEach( (data) => {
            let pos = L.latLng( data[0] );
            if( !pos ) return this;
            let marker = L.marker( pos );
            if( !marker ) return this;
            marker.setIcon( Geolocation.svgIconPin( Geolocation.default.markerColor, data[1], 'darkred' ) );
            this.marker.push( marker );
        } );
        if( this.map ) this.show( this.map );
        return this;
    }
}
Geolocation.tools.nrmarker = function(...args) { return new Geolocation.Tools.Nrmarker(args); };

/* Tool "center" */
//  [ [lat,lng], zoom, radius in Meter, color ]
Geolocation.default.boundsCenter = {
    color:"#ff7800",
    weight:1,
    fillOpacity:0.1
};
Geolocation.Tools.Center = class extends Geolocation.Tools.Template
{
    constructor ( ...args){
        super(args);
        this.zoom = this.zoomDefault = Geolocation.default.zoom;
        this.center = this.centerDefault = L.latLngBounds( Geolocation.default.bounds ).getCenter();
        return this;
    }
    setValue( data ){
        super.setValue( data );
        this.center = L.latLng( data[0] ) || this.centerDefault;
        this.zoom = data[1] || this.zoomDefault;
        this.circle = null;
        if( data[2] ) {
            let options = Geolocation.default.boundsCenter;
            options.color = data[3] || options.color;
            options.radius = data[2];
            this.circle = L.circle( this.center, options );
        }
        if( this.map ) this.show( this.map );
        return this;
    }
    show( map ){
        super.show( map );
        map.setView( this.center, this.zoom );
        if( this.circle instanceof L.Circle ) this.circle.addTo( map );
        return this;
    }
    getCurrentBounds(){
        return this.center;
    }
}
Geolocation.tools.center = function(...args) { return new Geolocation.Tools.Center(args); };


//-------- Demo-Tools. Siehe Dokumentation devgeojson.md ---------

// Tool "geojsonfreebus"
// basiert auf geojson, zeigt Buslinien under construction in gelb an
Geolocation.Tools.GeojsonFreeBus = class extends Geolocation.Tools.GeoJSON
{
    //--- Sonderfunktionen
    _style(feature) {
        // Normale Linie
        let style = super._style(feature) || {};
        // Sonderfall Linie under Construction
        if( feature.properties && feature.properties.underConstruction !== undefined ) {
            if( true === feature.properties.underConstruction ) {
                style.color = 'yellow';
            }
        }
        return style;
    }

}
Geolocation.tools.geojsonfreebus = function(...args) { return new Geolocation.Tools.GeojsonFreeBus(args); };

// Tool "geojsonbicyclerental"
// basiert auf geojson, zeigt aber ein spezielles Icon an
Geolocation.Tools.GeojsonBicycleRental = class extends Geolocation.Tools.GeoJSON
{
    // Fahrrad-Verleihstationen bekommen einen grünen Pfeil
    icon = Geolocation.svgIconPin( 'green', 'B', 'red' )

    //--- Sonderfunktionen
    setOptions( options ) {
        // pointToLayer:callback hinzufügen
        options.pointToLayer = this._pointToLayer.bind(this);
        return options;
    }
    _pointToLayer(feature, latlng) {
        return L.marker( latlng,{icon:this.icon} );
    }

}
Geolocation.tools.geojsonbicyclerental = function(...args) { return new Geolocation.Tools.GeojsonBicycleRental(args); };


// Tool "GeojsonCoorsField"
// basiert auf geojson, zeigt aber ein spezielles Icon an
Geolocation.Tools.GeojsonCoorsField = class extends Geolocation.Tools.GeoJSON
{
    // Fahrrad-Verleihstationen bekommen einen grünen Pfeil
    icon = Geolocation.svgIconPin( 'yellow', 'C', 'red' )

    //--- Sonderfunktionen
    setOptions( options ) {
        // pointToLayer:callback hinzufügen
        options.pointToLayer = this._pointToLayer.bind(this);
        return options;
    }
    _pointToLayer(feature, latlng) {
        return L.marker( latlng,{
            icon: L.icon({
        		iconUrl: Geolocation.sPath + '/images/baseball-marker.png',
        		iconSize: [32, 37],
        		iconAnchor: [16, 37],
        		popupAnchor: [0, -28]
        	})
        } );
    }

}
Geolocation.tools.geojsoncoorsfield = function(...args) { return new Geolocation.Tools.GeojsonCoorsField(args); };
