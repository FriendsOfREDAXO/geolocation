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
Geolocation.Tools.Center = class extends Geolocation.Tools.Template{
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
        if( this.map ) this.show( this.map );
        return this;
    }
    show( map ){
        super.show( map );
        map.setView( this.center, this.zoom );
        return this;
    }
    getCurrentBounds(){
        return this.center;
    }
}
Geolocation.tools.center = function(...args) { return new Geolocation.Tools.Center(args); };
