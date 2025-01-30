/* Geolocation Backend-JS */

/**
 * Tool "locationpicker"
 * 
 * Dataset:
 *              [lat,lng] oder null:    Position des Markers 
 *              radius:                 Umkreis um latlng (default 1000)
 *              [[lat,lng],[lat,lng]]:  Kartenbereich wenn es keinen gültigen Marker gibt (Default: Systemdefault)
 *              {...}                   Diverse Parameter zur optischen Konfiguration der Kartenelemente
 *                                      pin.color => Farbe des Pins/Markers
 *                                      circle.color => Farbe des Kreises
 *                                      circle.weight => Breite des Randes (0=aus)
 *                                      circle.opacity => Transparenz der Fläche des Kreises (0..1)
 *          
 *        JS:   [ [lat,lng], 250, [[latNE,lngNE],[latSW.lngSW]], {pin: {color: blue}, circle: {color: orange, ...}}]
 *        PHP:  [ [lat,lng], 250, [[latNE,lngNE],[latSW.lngSW]], [pin => [color => blue], circle => [color => orange, ...]]]
 * 
 * Konzept:
 *          Der LocationPicker stellt die verschiedenen Zustände der Karte mehr oder weniger automatisch her.
 *          So gesehen ein recht komplexer Marker:
 *          Gültige Positionsangabe:
 *              Blendet den Marker an der Position ein
 *              Blendet einen Kreis an der Position ein, dessen Radius die Größe des Kartenausschnitts setzt.
 *              Positioniert und zoomt die Karte passen dazu.
 *          ungültige  bzw. unvollständige Positionsangabe:
 *              Blendet Marker und Kreis aus, lässt die Karte ansonsten unvwerändert.
 *              (Passiert nur bei manueller, noch unvollständiger Koordinateneingabe)
 *          keine Positionsangabe, null:
 *              blendet alles aus und stellt den Kartenausschnitt auf die Grundposition
 * 
 * Verhalten:
 *          Bein normalen initialisieren (setValue(...)) sollten die Parameter vollständig angegen werde,
 *          Auf Basis der Auswertungwerden Circle, Marker und Bounds konfiguriert.
 * 
 *          Weitere Änderungen passieren nicht mehr über setValue(...), sondern die drei Anzeige-Methoden
 *          - showValidPosition
 *          - showInvalidPosition
 *          - showVoidPosition 
 * 
 *          Der Marker unterstützt Dragging. An der Endposition angekommen, wird der Event "dragend"
 *          aufgefangen und daraus ein CustomEvent mit der Position auf dem Karten-Container gesendet.
 *          Der Kreis wird um den Marker gezogen und bestimmt damit bei gültigem Marker die räumliche
 *          Ausdehnung und dmait den Mindest-Kartenausschnitt. Nicht verwirren lassen: je nach Circle-
 *          Parametern (z.b. "weight":0,"fillOpacity":0}) ist der Kreis unsichtbar, aber vorhanden!
 *          
 * Events (ausgehend)
 *          geolocation:locationpicker.dragend  =>  detail.latlng 
 */
Geolocation.Tools.LocationPicker = class extends Geolocation.Tools.Template {
    radius = 1000;      // integer
    marker = null;      // L.Marker
    circle = null;      // L.Circle
    bounds = null;      // L.latLngBounds
    status = -1;        // -1, 0, 1
    params = {};

    /**
     * data ist ein Array mit der initialen Position und den zusätzlichen Angaben 
     * für Radius und Fallback-Karte.
     *  data[2] => [PosNW,PosSE], also zwei Ecken, die die Default-Karte ergeben
     *  data[1] => radius, mit der gewünschten Umgebung um den Marker
     *  data[1] => [lat,lng] oder null, also die Position des Markers
     */
    setValue(data) {
        super.setValue(data);

        this.radius = isNaN(data[1]) ? 1000 : data[1];

        this.bounds = L.latLngBounds(data[2]);
        if (!this.bounds.isValid()) {
            this.bounds = L.latLngBounds(Geolocation.default.bounds);
        }

        this.params = data[3] || this.params;
        this.params.circle = this.params.circle || {};
        this.params.pin = this.params.pin || {};

        this.pos = L.latLng(data[0]);
        this.status = this.pos ? 1 : -1;
        this.pos = this.pos || this.bounds.getCenter();

        if (this.circle instanceof L.Circle) {
            this.circle.setRadius($this.radius);
            this.circle.setLatLng(this.pos);
        } else {
            this.params.circle.radius = this.radius;
            this.circle = L.circle(this.pos, this.params.circle);
            this.circle.on('add', (e) => this.map.fitBounds(this.circle.getBounds()));
        }

        if (this.marker instanceof L.Marker) {
            this.marker.setLatLng(this.pos);
        } else {
            this.marker = L.marker(this.pos, {
                icon: Geolocation.svgIconPin(this.params.pin.color || Geolocation.default.positionColor),
                draggable: true,
                autoPan: true,
                zIndexOffset: 100,
            });
            this.marker.on('dragend', this.evtDragend.bind(this));
        }
        return this;
    }

    show(map) {
        super.show(map);
        this.map = map;
        if (this.status === 0) {
            this.showInvalidPosition();
            return this;
        }
        if (this.status === 1) {
            this.showValidPosition(this.pos, map);
            return this;
        }
        this.showVoidPosition();
        return this;
    }

    remove() {
        if (this.marker instanceof L.Marker) this.marker.remove();
        if (this.marker instanceof L.Circle) this.circle.remove();
        super.remove();
        this.map = null;
        return this;
    }

    getCurrentBounds() {
        if (this.status === -1) {
            return this.bounds;
        }
        return (this.circle instanceof L.Circle) ? this.circle.getBounds() : null;
    }

    evtDragend(e) {
        let latlng = e.target.getLatLng();
        this.showValidPosition(latlng);
        let event = new CustomEvent(
            'geolocation:locationpicker.dragend',
            {
                bubbles: true,
                cancelable: true,
                detail: latlng,
            });
        this.map.getContainer().dispatchEvent(event);
    }

    /**
     * Zusätzliche Methoden, um den Marker zu positionieren oder auszublenden 
     * 
     * showVoidPosition
     *      blendet die Objekte marker und circle aus
     *      repositioniert die Karte um die Bounds
     * 
     * showValidPosition
     *      berechnet den Circle um die Position
     *      blendet den circle ein
     *      blendet den Marker ein
     *      repositioniert die Karte um den circle
     * 
     * showInvalidPosition
     *      behällt die aktuellen Einstellungen, keine Repositionierung der Karte
     *      blendet die Objekte marker und circle aus
     */
    showVoidPosition() {
        this.status = -1;
        if (this.map) {
            this.marker.remove();
            this.circle.remove();
            this.map.fitBounds(this.bounds);
        }
    }

    showValidPosition(latlng) {
        this.status = 1;
        this.pos = latlng;
        this.marker.setLatLng(latlng);
        this.circle.setLatLng(latlng);
        if (this.map) {
            this.marker.addTo(this.map);
            this.circle.addTo(this.map);
            this.map.setView(latlng, this.map.getZoom())
            // ein "this.map.fitBounds(this.circle.getBounds())"" funktioniert hier nicht (timing-Problem)
            // daher oben ein "this.circle.on('add' ...)"
        }
    }

    showInvalidPosition() {
        this.status = 0;
        this.marker.remove();
        this.circle.remove();
    }
}
Geolocation.tools.locationpicker = function (...args) { return new Geolocation.Tools.LocationPicker(args); };

/**
 * Die Klasse bildet den allgemeinen Ablaufrahmen für URL-Tests mittels CustomHTML-Elementen.
 * 
 * Derzeit gibt es nur den Test auf Tile/Layer-Urls.
 * In der Basisklasse werden drei Attribute geprüft und erwartet!
 * 
 *      modal       Code für ein Bootstrap-Modal, in dem die Ergebnisse des Tests angezeigt werden
 *      api         ein JSON-Array mit Parametern für die Abfrage (rex-api-call, _csrf, action-Code)
 *      url         HtmlID des Input-Feldes für die Url  
 *    
 * Für ggf. zusätzliche Attribute muss die abgeleitete Klasse sorgen, die auch die
 * Grundlage des CustomHtml-Tags ist.
 */
Geolocation.Classes.TestUrl = class extends HTMLElement {

    __api = null;
    __modalNode = null;
    __modalContentNode = null;
    __urlNode = null;

    /**
     * Da der Link auf weitere DOM-Elemente außerhalb dieses Nodes benötigt wird
     * muss die eigentliche Initialisierung an ggf. DOMContentLoaded delegiert werden.
     * 
     * Der als Attribut mitgelieferte HTML-Code für ein Modal (Ergebnisanzeige)
     * wird hier bereits abgerufen, vorne in den DOM gehängt und die Referenzen gespeichert.
     * Kann der Modal-Code nicht korrekt eingebaut werden, bleibt this deaktiviert.
     * 
     * Die API-Parameter (rex-api-call, _csrf, action-Code) werden hier ebenfalls bereits
     * abgerufen (JSON-Array).
     */
    connectedCallback() {
        // erstmal deaktiviert
        this._setActiveState(false)

        // API-Soll-Parameter abrufen und als FormData bereitstellen
        try {
            let api = JSON.parse(this.getAttribute('api'));
            this.__api = new FormData();
            for (const [key, value] of Object.entries(api)) {
                this.__api.set(key, value)
            }
        } catch (e) {
            this.__api = null;
            return false;
        }

        // Modal-Code auslesen, Abbruch wenn nicht vorhanden
        let modal = (this.getAttribute('modal') || '').trim();
        if ('' === modal) {
            return;
        }

        // https://stackoverflow.com/questions/1912501/unescape-html-entities-in-javascript
        let elem = document.createElement('textarea');
        elem.innerHTML = modal;
        modal = elem.value;

        // Modal vorne im Body einhängen (verhindert "seltsame" CSS-Effekte).
        document.body.insertAdjacentHTML('afterbegin', modal);
        this.__modalNode = document.body.firstElementChild;

        // Plausibility-Test des Modals durch Abruf des Content-Nodes
        this.__modalContentNode = this.__modalNode.querySelector('.modal-body') || null;
        if (!this.__modalContentNode) {
            // Aufräumen vor dem Abbruch
            this.__modalNode.remove();
            this.__modalNode = null;
            this.__api = null;
            return;
        }

        // Alles Übrige initialisieren
        if ('loading' === document.readyState) {
            document.addEventListener('DOMContentLoaded', this._initialize.bind(this));
        } else {
            this._initialize();
        }
    }

    disconnectedCallback() {
        this._turnOff();
        $(this.__modalNode).off('shown.bs.modal', this._validate.bind(this));
        this.removeEventListener('click', this._onClick.bind(this));
        if (this.__urlNode) {
            this.__urlNode.removeEventListener('input', this._checkActiveState.bind(this));
        }
        document.removeEventListener('DOMContentLoaded', this._initialize.bind(this));
    }

    /**
     * Überprüft, ob das mit dem Attribut "url" angegebene Feld
     * existiert.
     * Überprüft mit _turnOn die in einer abgeleiteten Klasse ggf. zusätzlichen
     * Felder.
     * 
     * Wenn eine der beiden Überprüfungen scheitert, bleibt das Element deaktiviert.
     */
    _initialize(event) {

        // Id des Url-Eingabefeldes angegeben, Feld existent?
        this.__urlNode = document.getElementById(this.getAttribute('url')) || null;

        // Wenn UrlNode angegeben und wenn die nachgelagerten Elemente existieren
        // das Element aktivieren
        if (this.__urlNode && this._turnOn()) {
            // Event-Handler das Modal und auf sich selbst
            $(this.__modalNode).on('shown.bs.modal', this._validate.bind(this));
            this.addEventListener('click', this._onClick.bind(this));
            this.__urlNode.addEventListener('input', this._checkActiveState.bind(this));
            // Freischalten?
            this._checkActiveState();
            return
        }

        // Aufräumen vor dem Abbruch
        this.__modalNode.remove();
        this.__modalNode = null;
        this.__api = null;
        return
    }

    /**
     * Setzt das Element auf "aktiviert", wenn die beteiligten Felder gültige
     * Inhalte haben.
     * Dem entsprechend wird dieser Node als Button freigeschaltet oder nicht.
     * this._setActiveState(this._hasValidContent()); wird nur aufgerufen,
     * wenn __url.value eine formal gültige Url ist.
     */
    _checkActiveState() {
        try {
            // gültige URL?
            new URL(this.__urlNode.value);
            this._setActiveState(this._hasValidContent());
        } catch (e) {
            this._setActiveState(false);
        }
    }

    /**
     * Schaltet diesen Node frei oder nicht mittels Attribut "disabled"
     */
    _setActiveState(state) {
        if (state) {
            this.removeAttribute('disabled');
        } else {
            this.setAttribute('disabled', '');
        }
    }

    /**
     * Click auf diesen Button (wenn er freigegeben ist) öffnet den modalen Dialog
     * zur Anzeige der Validierung. Die eigentliche Validierung wird erst angestoßen, wenn
     * der Dialog sich öffnet.
     * Das "this" kein echter Button ist, wird trotz optischen "Disabled" auf Clicks reagiert.
     * Also: bei "Disabled" Click abfangen und nicht weiterleiten!
     */
    _onClick(event) {
        if (this.hasAttribute('disabled') && 'false' !== this.getAttribute('disabled')) {
            event.stopImmediatePropagation();
            event.preventDefault();
            return;
        }
        this.__modalContentNode.innerHTML = '<i class="fa fa-spinner" style="animation: rex-ajax-loader-spin 1s linear infinite;"></i>';
        $(this.__modalNode).modal('show');
    }

    /**
     * Steuert den Validierungs-Prozess.
     * Zuerst werden die Parameter eingesammelt (_setValidationParams)
     * Dann wird der AJAX-Call durchgeführt und das Ergebnis im
     * Modal angezeigt. 
     */
    _validate() {
        // Url-Object aktualisieren
        this.__api.set('url', this.__urlNode.value);
        this._setValidationParams(this.__api);
        fetch(window.location.origin + window.location.pathname, {
            method: 'POST',
            body: this.__api,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.text();
            })
            .then((HTML) => { this.__modalContentNode.innerHTML = HTML; })
            .catch((error) => {
                this.__modalContentNode.innerHTML = '<p>' + Geolocation.i18n('There has been a problem with your fetch operation: {error}', { 'error': error }) + '</p>';
            });
    }

    /**
     * Überschreiben! 
     * 
     * initialisiert ggf. individuellen Content (z.B. über Atribute
     * angegebene ReferenzNodes) und setzt EventHandler etc.
     * liefert nomalerweise false (etwas lief schief) oder im
     * Erfolgsfall true (aktiviere den Button).
     */
    _turnOn() {
        return false;
    }

    /**
     * Überschreiben! 
     * 
     * wird von disconnectedCallback aufgerufen und entfernt z.B. 
     * die EventListener, die mit _turnOn aktiviert wurden.
     */
    _turnOff() {
    }

    /**
     * Überschreiben! 
     * 
     * fügt die für dieses CustomFeld relevanten Parameter in das
     * FormData-Object "formData" ein, mit dem die Validierung per Ajax erfolgt
     */
    _setValidationParams(formData) {
    }

    /**
     * Überschreiben! 
     * 
     * Prüft ab, ob die individuellen Felder des jeweiligen Testumfangs
     * korrekte Werte aufweisen, mit denen eine Überprüfung sinnvoll wäre
     * __url ist zu diesem Zeitpunkt schon getestet und OK
     * Gibt entsprechend false oder true zurück
     */
    _hasValidContent() {
        return false;
    }
}

/** <geolocation-test-tile-url> Custom-HTML-Element für Url-Felder mit Test auf Tiles/Layer
 * 
 * Custom-HTML-Element <geolocation-test-tile-url>button-label</geolocation-test-tile-url>
 * 
 * Attribute allgemein:
 *      modal       Code für ein Bootstrap-Modal, in dem die Ergebnisse des Tests angezeigt werden
 *      api         ein JSON-Array mit Parametern für die Abfrage (rex-api-call, _csrf, action-Code)
 *      url         HtmlID des Input-Feldes für die Url
 * 
 * Zusätzliches Attribut hier:
 *      subdomain   HtmlID des Input-Feldes für die SubDomain
 */
customElements.define('geolocation-test-tile-url', class extends Geolocation.Classes.TestUrl {
    __subdomainNode = null;

    /**
     * Überprüft subdomain und setzt ggf. die nötigen Event-Handler.
     */
    _turnOn() {
        // Id des SubDomain-Eingabefeldes angegeben, Feld existent?
        this.__subdomainNode = document.getElementById(this.getAttribute('subdomain')) || null;
        if (!this.__subdomainNode) {
            return false;
        }

        // Event-Handler aktivieren
        this.__subdomainNode.addEventListener('input', this._checkActiveState.bind(this));
        return true;
    }

    /**
     * wird von disconnectedCallback aufgerufen und entfernt die 
     * EventListener, die mit _turnOn aktiviert wurden.
     */
    _turnOff() {
        if (this.__subdomainNode) {
            this.__subdomainNode.removeEventListener('input', this._checkActiveState.bind(this));
        }
    }

    /**
     * fügt die für dieses CustomFeld relevanten Parameter in das
     * FormData-Object "formData" ein, mit dem die Validierung per Ajax erfolgt
     */
    _setValidationParams(formData) {
        formData.set('subdomain', this.__subdomainNode.value);
    }

    /**
     * überprüft die Inhalte der Felder __urlNode und __subdomainNode, ob sie
     * zumindest formal gültige Inhalte haben:
     *  -   eine formal valide  Url -> new URL wirft keine Exception
     *  -   wenn die Url SubDomains benötigt ({s} in der Url) muss auch die SubDomain angegeben sein
     * Gibt entsprechend false oder true zurück
     */
    _hasValidContent() {
        // keine SubDomain erforderlich?
        if (-1 === this.__urlNode.value.indexOf('{s}')) {
            return true;
        }
        // erforderliche SubDomain angegeben?
        if (0 < this.__subdomainNode.value.trim().length) {
            return true;
        }
        // Fehler, nötige Subdomain fehlt.
        return false;
    }

});

/**
 * ein Workaround um sicherzustellen, dass alle Sub-Elemente geladen sind, bevor
 * weitere Arbeitschritte im CustomHTML loslegen. Erübrigt in den meisten Fällen
 * Hilfskrücken mit DOMContentLoaded, wenn es um die Nodes IM Element geht
 * Quelle: https://stackoverflow.com/questions/48498581/textcontent-empty-in-connectedcallback-of-a-custom-htmlelement
 */
Geolocation.Classes.CustomHTMLBaseElement = (superclass) => class extends superclass {

    constructor(...args) {
        const self = super(...args);
        self.parsed = false; // guard to make it easy to do certain stuff only once
        self.parentNodes = [];
        return self
    }

    connectedCallback() {
        if (typeof super.connectedCallback === "function") {
            super.connectedCallback();
        }
        // --> HTMLBaseElement
        // when connectedCallback has fired, call super.setup()
        // which will determine when it is safe to call childrenAvailableCallback()
        this.setup()
    }

    childrenAvailableCallback() {
    }

    setup() {
        // collect the parentNodes
        let el = this;
        while (el.parentNode) {
            el = el.parentNode
            this.parentNodes.push(el)
        }
        // check if the parser has already passed the end tag of the component
        // in which case this element, or one of its parents, should have a nextSibling
        // if not (no whitespace at all between tags and no nextElementSiblings either)
        // resort to DOMContentLoaded or load having triggered
        if ([this, ...this.parentNodes].some(el => el.nextSibling) || document.readyState !== 'loading') {
            this.parsed = true;
            if (this.mutationObserver) this.mutationObserver.disconnect();
            this.childrenAvailableCallback();
        } else {
            this.mutationObserver = new MutationObserver((mutationList) => {
                if ([this, ...this.parentNodes].some(el => el.nextSibling) || document.readyState !== 'loading') {
                    this.childrenAvailableCallback()
                    this.mutationObserver.disconnect()
                }
            });

            // Wegen Problemen in der Erkennung mehrere aufeinander folgender gleicher CustomHtmlElemente
            // geändert auf this.parentNode. Umd schon funktioniert es.
            if (this.mutationObserver) this.mutationObserver.observe(this.parentNode, { childList: true });
        }
    }
};

/** <geolocation-layerselect> Klammer für <geolocation-layerselect-items>
 * 
 * Das Element verwaltet neben der Liste der Einträge (<geolocation-layerselect-item>)
 * auch einen von YForm benötigtes <select>. Der Popup-Button öffnet ein YForm-Popup
 * zur Auswahl der Layer und legt für den selektierten einen option-Tag im select an.
 * Ein hier initialisierter MutationObserver merkt das und überträgt den Eintrag
 * in ein <geolocation-layerselect-item>.
 * 
 * Der Item-Rohling steckt im Attribut "template". 
 * 
 * Bei Radio-Items wird zudem sichergestellt, dass wenn es nur ein Item gibt, dieses
 * auch selektiert ist.   
 */
customElements.define('geolocation-layerselect',
    class extends Geolocation.Classes.CustomHTMLBaseElement(HTMLElement) {

        __template = '{label}';
        __isRadio = false;
        __observer = null;
        __container = null;

        connectedCallback() {
            // Template abrufen, Choive-Typ ermitteln
            this.__template = this.getAttribute('template') || this.__template;
            this.__isRadio = null !== this.__template.match(/type\s*=\s*"radio"/);

            // alles Weitere wenn die Child-Elemente geladen sind
            super.connectedCallback();
        }

        disconnectedCallback() {
            this.__observer.disconnect();
            this.__observer = null;
        }

        /**
         * Ermittelt die beiden relevanten Sub-Container (select und eigene Items).
         * Aktiviert darauf den EventListener für gelöschte eigene Items und
         * den MutationObserver für neue Einträge.
         * @returns
         */
        childrenAvailableCallback() {
            // Das Warten hat ein Ende
            this.parsed = true;

            // Den eigenen list-group-Container ausfindig machen sowie den hidden Select,
            // in dem die neuen Options aus dem YForm-Popup ankommen
            // Überwachen per MutationObserver
            this.__container = this.querySelector('.list-group');
            this.__select = this.querySelector('select[id^="yform-dataset-view-"]');
            // Fallback für YForm vor 4.2
            let preYform420 = false;
            if (!this.__select) {
                preYform420 = true;
                this.__select = this.querySelector('select[id^="YFORM_DATASETLIST_SELECT_"]');
            }
            if (!this.__select || !this.__container) {
                return;
            }
            this.__observer = new MutationObserver(this._addEntry.bind(this));
            this.__observer.observe(this.__select, { childList: true });

            // Event fordert das Popup-Fenster (be_manager_relation-Style) an,
            // mit dem neue Layer hinzugefügt werden.
            if(preYform420) {
                this.addEventListener('geolocation:layerselect.add', this._openPopupPre420.bind(this));
            } else {
                this.addEventListener('geolocation:layerselect.add', this._openPopup.bind(this));
            }
        }

        /**
         * öffnet das Popup-Fenster zur Auswahl neuer Layer.
         * Event.detail enthält den kompletten Abruf-Link
         * Der letzte Teil des Links ist die Popup-ID, die zusätzlich als id an
         * newWindow() übergeben werden muss
         */
        _openPopup(event) {
            let index = event.detail.lastIndexOf('=');
            let id = event.detail.substr(index + 1);
            return newWindow(id, event.detail, 1200, 800, ',status=yes,resizable=yes');
        }
        // Da in YForm vor 4.2 die Nummer in der Select-ID von YForm-JS verändert
        // wird, tauschen wir hier in diesem Fall die Nummer in der URL gegen die
        // Nummer aus der ID. Die Nummer steht am Ende der URL.
        // 
        // NOTICE: kann zurückgebaut werden wenn die Mindestversion von YForm
        // auf 4.2 oder höher geändert wird.
        _openPopupPre420(event) {
            let url = event.detail;
            let index = this.__select.id.lastIndexOf('_');
            let id = this.__select.id.substr(index + 1);
            index = url.lastIndexOf('=');
            url = url.substr(0, index + 1) + id;
            return newWindow(id, url, 1200, 800, ',status=yes,resizable=yes');
        }

        /**
         * Wenn vom YForm-Popup ein neu ausgwählter Layer im select abgelegt wurde,
         * wird der Eintrag in einen template-konformen Einrag im Ziel-Container
         * umgewandelt. 
         * Doppelte Einträge werden ignoriert.
         * 
         * @param {MutationRecord[]} mutations 
         */
        _addEntry(mutations) {
            mutations.forEach(mutation => mutation.addedNodes.forEach(option => {
                if (!this.__container.querySelector(`[value="${option.value}"]`)) {
                    let checked = this.__isRadio && 0 == this.__container.children.length;
                    let template = this.__template
                        .replaceAll('{label}', option.innerHTML)
                        .replaceAll('{value}', option.value)
                        .replaceAll('{checked}', checked ? 'checked' : '');
                    this.__container.insertAdjacentHTML('beforeEnd', template);
                }
                option.remove();
            }));
        }

    });

/** <geolocation-layerselect-item> Klammer für Einträge in geolocation-layerselect
* 
* Dieses Custom-HTML bündelt Funktionen zur Verwaltung eines Eintrags im Widget.
* Konkretwerden diverse Events aufgenommen und in Aktionen umgesetzt.
* 
* - Klick-Event geolocation:layerselect.up bzw. Key ArrowUp
*      dieses Element eine Zeile höher schieben vor previousSibling
* 
* - Klick-Event geolocation:layerselect.down bzw. Key ArrowDown
*      dieses Element eine Zeile tiefer schieben nach nextSibling
* 
* - Klick-Event geolocation:layerselect.delete bzw. Key Delete
*      dieses Element löschen
* 
* - Klick auf den Container bzw. Key Space
*      den Radio/Checkbox-Input im Element anklicken
*
* Dieses Element schickt keine eigenen Events ab, wenn sich die Feldinhalte ändern!
* Die Original-Events der Inputs werden jedoch abgesetzt (ungetestet).
*/
customElements.define('geolocation-layerselect-item',
    class extends HTMLElement {

        connectedCallback() {
            // Event-handler setzen
            this.addEventListener('geolocation:layerselect.up', this._moveUp.bind(this));
            this.addEventListener('geolocation:layerselect.down', this._moveDown.bind(this));
            this.addEventListener('geolocation:layerselect.delete', this._delete.bind(this));
            this.addEventListener('keydown', this._byKey.bind(this));
            this.addEventListener('click', this._selectChoice.bind(this));
        }

        disconnectedCallback() {
            this.removeEventListener('geolocation:layerselect.up', this._moveUp.bind(this));
            this.removeEventListener('geolocation:layerselect.down', this._moveDown.bind(this));
            this.removeEventListener('geolocation:layerselect.delete', this._delete.bind(this));
            this.removeEventListener('keydown', this._byKey.bind(this));
            this.removeEventListener('click', this._selectChoice.bind(this));
        }

        /** 
         * Schiebt den Eintrag vor den davor stehenden Eintrag,
         * also eine Position nach oben
         * 
         * @param {CustomEvent} event 
         */
        _moveUp(event) {
            let sibling = this.previousElementSibling;
            if (sibling) {
                sibling.before(this);
                this.focus();
            }
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        /** 
         * Schiebt den Eintrag hinter den Folgeeintrag,
         * also eine Position nach unten
         * 
         * @param {CustomEvent} event 
         */
        _moveDown(event) {
            let sibling = this.nextElementSibling;
            if (sibling) {
                sibling.after(this);
                this.focus();
            }
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        /** 
         * Entfernt den Eintrag aus der Liste.
         * 
         * Falls es ein Radio-Element ist und dieser Eintrag der selektierte (checked)
         * wird nach dem Löschen geprüft, ob es noch Elemente im Parent-Container gibt.
         * Wenn ja, wird das erste Element angeklickt, um genau ein selektiertes
         * Element zu haben. 
         * 
         * @param {CustomEvent} event 
         */
        _delete(event) {
            let input = this.querySelector('input:not([type="hidden"])');
            let container = input.type == 'radio' && input.checked ? this.parentNode : null;

            this.remove();

            if (container && 0 < container.childNodes.length) {
                container.firstElementChild.click();
            }
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        /** 
         * Fängt den Event auf der Layer-Zeile ab und wandelt ihn in einen
         * Click auf dem Input um.
         * 
         * Click auf dem Input fängt der Input selbst ab.
         * 
         * @param {Event} event 
         */
        _selectChoice(event) {
            if (event.target == this) {
                let input = this.querySelector('input:not([type="hidden"])');
                if (input) {
                    input.click();
                    this.focus();
                }
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }

        /**
         * Reagiert auf Tasten, die den drei Buttons je Eintrag entsprechen
         * sowie der Auswahl des Choice-Input
         * 
         * @param {Event} event 
         * @returns mixed
         */
        _byKey(event) {
            if ('ArrowUp' == event.key) {
                return this._moveUp(event);
            } else if ('ArrowDown' == event.key) {
                return this._moveDown(event)
            } else if ('Delete' == event.key) {
                return this._delete(event);
            } else if (32 == event.keyCode) {
                return this._selectChoice(event);
            }
        }
    });

/**
* HTMLElement, das einfach einen Custom Event absetzt
*
*  <gelocation-trigger attributes>content</gelocation-trigger>
*
*      [from="«selector»"]                 Node-Qualifier, auf dem der Event abgeschickt werden soll
*                                          default/Fallback: dieser DOM-Node
*                                          Wenn der String mit << beginnt, wird mir dem ersten Element
*                                          ein closest(...) durchgeführt um vom Ziel nach ':scope rest' zu kommen.
*      event="«event-name»"                wie heißt der Event
*      [detail="«data»"]                   Mit dem Event in event.detail verschickte Daten
*      [on="«click»"]                      Auslösender Event auf dem Item (default: click)
*      [call="callback"]                   aufzurufende CallbackFunktion beim auslösenden Event. Nur den Funktionsnamen!
*                                          Beim Aufruf wird Detail als Parameter an die Funktion übergeben
*      [accesskey="«key»"]                 Auslösender Key (Zeichen oder Name), https://www.freecodecamp.org/news/javascript-keycode-list-keypress-event-key-codes/
* 
* Beispiel:
* <gelocation-trigger class="button" from="<<.panel" event="gelocation:add" detail="show"><i class="rex-icon rex-icon-view"></i></gelocation-trigger>
*/
customElements.define('gelocation-trigger',
    class extends HTMLElement {

        __node = this;
        __from = '';
        __event = null;
        __detail = '';
        __callback = null;
        __on = 'click';
        __isActive = false;
        __key = '';

        connectedCallback() {
            this.style.cursor = 'pointer';
            this.addEventListener(this.__on, this._onClick.bind(this));
            this.addEventListener('keydown', this._onKey.bind(this));
            this.__isActive = true;
        }

        disconnectedCallback() {
            this.removeEventListener(this.__on, this._onClick.bind(this));
            this.removeEventListener('keydown', this._onKey.bind(this));
            this.__isActive = false;
        }

        static get observedAttributes() {
            return ['from', 'event', 'detail', 'on', 'call', 'accesskey'];
        }

        attributeChangedCallback(name, oldValue, newValue) {
            if (oldValue == newValue) {
                return;
            }
            if ('from' == name) {
                this.__node = null;
                this.__from = newValue;
                return;
            }
            if ('event' == name) {
                if ('' == newValue) {
                    this.__event = null;
                } else {
                    this.__event = newValue
                }
                return;
            }
            if ('detail' == name) {
                try {
                    this.__detail = JSON.parse(newValue);
                } catch (e) {
                    this.__detail = newValue;
                }
                return;
            }
            if ('on' == name) {
                if (this.__isActive) {
                    this.removeEventListener(this.__on, this._onClick.bind(this));
                }
                this.__on = newValue || 'click';
                if (this.__isActive) {
                    this.addEventListener(this.__on, this._onClick.bind(this));
                }
                return;
            }
            if ('call' == name) {
                this.__callback = newValue;
                if (this.__callback) {
                    this.__callback = this.__callback.split('.').reduce((o, i) => o[i], window);
                    if (typeof this.__callback !== 'function') {
                        this.__callback = null;
                    }
                }
                return;
            }
            if ('accesskey' == name) {
                this.__key = newValue || '';
                return;
            }
        }

        _onClick(event) {
            event.stopImmediatePropagation();
            event.preventDefault();
            this._trigger();
        }

        _onKey(event) {
            if (event.key == this.__key) {
                event.stopImmediatePropagation();
                event.preventDefault();
                if (!event.repeat) {
                    this._trigger();
                }
            }
        }

        _trigger() {
            if (this.__callback) {
                this.__callback(this.__detail);
            }
            if (null === this.__callback && null == this.__event) {
                return console.error(`${this.tagName}: Missing event-name; feature disabled`);
            }
            this._node().dispatchEvent(new CustomEvent(this.__event, { bubbles: true, cancelable: true, detail: this.__detail }));
        }

        _node() {
            if (this.__node) {
                return this.__node;
            }
            if ('' < this.__from) {
                try {
                    let match = this.__from.match(/^(\<\<)?([#.]?[\w-]+)([\s\<\>~].*)?$/);
                    if (!match) {
                        throw `Invalid formed attribute 'from="${this.__from}"'`;
                    }
                    if (match[1]) {
                        this.__node = this.closest(match[2]);
                        if (this.__node && match[3]) {
                            this.__node = this.__node.querySelector(':scope ' + match[3].trim());
                        }
                    } else {
                        this.__node = document.querySelector(this.__from);
                    }
                    if (!this.__node) {
                        throw `Invalid attribute 'from="${this.__from}"', target not found`;
                    }
                } catch (error) {
                    console.warn(`${this.tagName}: ${error}; replaced by this node`)
                    this.__node = this;
                }
            }
            return this.__node;
        }
    });


/**
 * Im HTML-Tag <geolocation-geocoder-search> ist die Adress-Eingabe und die 
 * Suche nach Orten mittels des Geocoders zusammengefasst.
 * 
 * - Eingabefeld für die Suche,
 * - Button zum Befüllen der Suche aus Adressfeldern
 * - eine Liste für die Anzeige der Ergebnisse
 * 
 * Als Input bekommt der Tag drei Attribute
 * - geocoder: die URL des Geocoders
 * - template: das Template für die Anzeige der Adressen in der Auswahlliste
 * - addresslink: eine JSON-Liste mit den IDs der Adressfelder, die als Suchbegriff herangezogen werden können
 */
customElements.define('geolocation-geocoder-search',
    class extends Geolocation.Classes.CustomHTMLBaseElement(HTMLElement)
    {
        geoSearchTimeout = 0;
        geoInput = null;
        geoSelect = null;
        geoUrl = '';
        geoTemplate = null;
        geoAdressNodes = [];

        /**
         * Event-Handler einrichten
         */
        connectedCallback() {
            this.geoAdressNodes = [];
            this.addEventListener('focusin', this.evtInitialFocusIn.bind(this), { once: true });
            this.addEventListener('focusout', this.evtFocusOut.bind(this));
            this.addEventListener('keyup', this.evtEscapeKey.bind(this));
            this.geoUrl = decodeURIComponent(this.getAttribute('geocoder') || this.geoUrl);
            this.geoTemplate = this.getAttribute('template') || '';
        }

        /**
         * Schließt die Select-Box, wenn der Focus außerhalb
         * dieses customElements landet
         */
        evtFocusOut(e) {
            if (!e.relatedTarget || e.relatedTarget.closest(this.tagName) !== this) {
                this.geoListVisibility(false);
            }
        }

        /**
         * Wenn das Element erstmalig den Focus erhält, werden die
         * ausstehenden Verlinkungen der Formularfelder vorgenommen
         * 
         * Wenn der Event vom Adress-Suche-Button ausgelöst wurde muss zusätzlich
         * dessen eigener EventHandler ausgeführt werden. 
         * (den Button gibt es eh nur, wenn es auch die Address-Felder gibt)
         */
        evtInitialFocusIn(e) {
            this.geoInput = this.querySelector('input');
            this.geoSelect = this.querySelector('.list-group');
            this.geoAdressBtn = this.querySelector('button');
            this.geoInput.addEventListener('input', this.evtInput.bind(this));
            this.geoInput.addEventListener('focusin', this.evtFocusIn.bind(this));
            this.geoSelect.addEventListener('click', this.evtSelect.bind(this));
            let adrFields = JSON.parse(this.getAttribute('addresslink') || '[]');
            if (this.geoAdressBtn) {
                this.geoAdressBtn.addEventListener('click', this.evtSearchByAddress.bind(this))
                adrFields.forEach((id) => {
                    let node = document.getElementById(id);
                    if (node) {
                        this.geoAdressNodes.push(node);
                    }
                });
                /* versuchsweise abgeschaltet; es gab Doppelaufrufe
                if (e.target === this.geoAdressBtn) {
                    this.evtSearchByAddress();
                }
                */
            }
        }

        /**
         * Wenn das Eingebefeld den Focus zurückbekommt und in der Liste noch 
         * Elemente stehen, wird die Liste wieder eingeblendet
         */
        evtFocusIn() {
            this.geoListVisibility(0 < this.geoSelect.childElementCount);
        }

        /**
         * Eingaben im Input on the fly in Suchen übersetzen
         * Aber nicht sofoert loslegen, evtl kommt ja noch ein oder
         * zwei Zeichen dazu
         */
        evtInput() {
            clearTimeout(this.geoSearchTimeout);
            this.geoSearchTimeout = setTimeout(this.geoHandleSearch.bind(this), 300);
        }

        /**
         * Übernimmt die Inhalte der Adress-Felder in das Suchfeld
         * und löst die Suche selbst aus.
         */
        evtSearchByAddress(e) {
            let term = [];
            this.geoAdressNodes.forEach((node) => {
                if (node.value) {
                    term.push(node.value);
                }
            });
            this.geoInput.value = term.join(', ');
            this.geoHandleSearch();
        }

        /**
         * Wenn ein Element aus der Liste ausgewählt wurde (Click oder Enter), 
         * werden die wesentlichen Informationen (Längengrad, Breitengrad, Beschreibung)
         * via Event propagiert.
         * Die Liste wird dann ausgeblendet.
         */
        evtSelect(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.geoListVisibility(false);
            let item = e.target;
            this.geoInput.value = item.innerHTML;
            let event = new CustomEvent(
                'geolocation:address.selected',
                {
                    bubbles: true,
                    cancelable: true,
                    detail: {
                        lat: item.getAttribute('lat'),
                        lng: item.getAttribute('lng'),
                        label: item.innerHTML,
                        data: item.geoData,
                    }
                });
            this.dispatchEvent(event);
        }

        /**
         * Escape-Key auf Input oder Select schließt ein evtl offenes Select
         * durch 
         */
        evtEscapeKey(e) {
            if (e.key === 'Escape') {
                this.geoListVisibility(false);
            }
        }

        /**
         * Schaltet die Sichtbarkeit des Listen-Overlays entsprechend
         * des Status um. True=Sichtbar.
         * Umschalten durch Toggeln der hidden-Klasse
         */
        geoListVisibility(status) {
            this.geoSelect.classList.toggle('hidden', !status);
        }

        /**
         * Bei weniger als drei Zeichen keine Suche, Ergebnisliste ausblenden
         * Ansonsten über die Resolver-Url eine Adress-Suche durchführen.
         */
        geoHandleSearch() {
            let value = this.geoInput.value;
            if (value.length < 3) {
                this.geoListVisibility(false);
                return;
            }
            // TODO: Testausgaben löschen
            // TODO: Fehlerbehandlung verbessern 
            fetch(this.geoUrl.replace(/\{value\}/, encodeURIComponent(value)), {
                method: 'get',
            })
                .then(function (response) {
                    console.log(response)
                    return response.json();
                })
                .then((data) => {
                    // TODO: Testausgaben löschen
                    console.log(data)
                    this.geoSelect.innerHTML = '';
                    data.forEach(item => {
                        let entry = this.geoTemplate.replace(/\{ *([\w_-]+) *\}/g, function (t, key) { return item[key] || '{' + key + '}'; });
                        this.geoSelect.insertAdjacentHTML('beforeend', entry);
                        this.geoSelect.lastElementChild.geoData = item;
                    });
                    this.geoListVisibility(true);
                })
                .catch((function (e) {
                    // TODO: Das muss präzieser werden, z.B. NotFound = leere Liste, 
                    // Fehlermeldungen sprachabhängig
                    console.log(e)
                    this.geoSelect.innerHTML = '<p class="list-group-item">Sorry, Übertragungsfehler';
                    this.geoListVisibility(true);
                }).bind(this));
        }
    });

/**
 * Der Html-Tag <geolocation-geopicker> ist der Container für die intzeraktive
 * Ermitlung einer Position. Darin einmal eine Karte, die die Position anzeigt
 * und auf der die Position (Marker/Kreis) per D&D verschoben bzw. Click gesetzt
 * werden kann.
 * 
 * Über Attribute werden die Konfigurationsinformationen übermittelt. Darauf aufbauend 
 * setzt das Element die Verlinkungen zwischen den Eingabefeldern und der Karte.
 * Dazu kommen diverse Event-Handler für die Interaktionen.
 */
customElements.define('geolocation-geopicker',
    class extends Geolocation.Classes.CustomHTMLBaseElement(HTMLElement)
    {
        geoConfig = null;
        geoMap = null;
        geoMarker = null;
        geoLatFld = null;
        geoLngFld = null;

        /**
         * Wird aufgerufen wenn dieses HTML-Element angelegt ist und in dem DOM eingehängt wird.
         * Hauptaktion: die eigene Konfiguration einlesen (Attribut) und den Rest des Setup über
         * einen EvtHandler zu gegebenem Zeitpunkt durchführen
         */
        connectedCallback() {
            /**
             * Die Feld-Attribute einlesen; es wird unterstellt, dass das Objekt korrektes
             * JSON ist und alle benötigten Werte im richtigen Format aufweist.
             * Hier finden keine zu 99,9% überflüssigen in-deep-Analysen mehr statt.
             */
            this.geoConfig = this.getAttribute('config') || null;
            if (!this.geoConfig) {
                return;
            }
            this.geoConfig = JSON.parse(this.geoConfig) || null;
            if (!this.geoConfig) {
                return;
            }
            /**
             * EventHandler einrichten, der auf die Fertigstellung der Karte
             * inkl. der Tools wartet und dann die weitere Initialisierung durchführt
             */
            this.addEventListener('geolocation:map.ready', this.evtCatchMap.bind(this));
            this.addEventListener('geolocation:locationpicker.dragend', this.evtMarkerFromDrag.bind(this));
            this.addEventListener('geolocation:address.selected', this.evtMarkerFromSearch.bind(this));
        }

        /**
         * EvtHandler: wird aufgerufen nachdem in der Karte die Tools instanziert sind.
         * - ermittelt das Position-Tool und die Leaflet-Karte
         * - macht weiter mit childrenAvailableCallback (via super.connectedCallback())
         *   wenn alle DOM-Elemente innerhalb von this geladen sind
         */
        evtCatchMap(e) {
            this.geoMap = e.detail.map;
            this.geoMarker = e.detail.container.__rmMap.tools.get(this.geoConfig.marker);
            this.geoMap.on('locationfound', this.evtMarkerFromLocate.bind(this));
            this.geoMap.doubleClickZoom.disable();
            this.geoMap.on('dblclick', this.evtMarkerFromDblClick.bind(this));
            super.connectedCallback();
        }

        /**
         * Wird von connectedCallback() aufgerufen, nachdem alle zu this gehörenden
         * DOM-Elemente geladen sind und die Karte bekannt ist.
         * Die nötigen Verlinkungen zwischen diveren Eingabefeldern etc kann also
         * hergestellt werden.
         */
        childrenAvailableCallback() {
            // Das Warten hat ein Ende
            this.parsed = true;

            /**
             * Die Eingabefelder verlinken; bei externen Feldern, die erst hinter dem
             * Geopicker stehen, muss in dem Fall (nicht gefunden) das Feld via
             * DOMContentLoaded ermittelt werden.
             */
            this.geoLatFld = document.getElementById(this.geoConfig.coordFld.lat);
            this.geoLngFld = document.getElementById(this.geoConfig.coordFld.lng);
            if (this.geoLatFld && this.geoLngFld) {
                this.geoLatFld.addEventListener('input', this.evtMarkerFromInput.bind(this));
                this.geoLngFld.addEventListener('input', this.evtMarkerFromInput.bind(this));
                //this.evtMarkerFromInput();
            } else {
                document.addEventListener('DOMContentLoaded', (function () {
                    this.geoLatFld = document.getElementById(this.geoConfig.coordFld.lat);
                    this.geoLngFld = document.getElementById(this.geoConfig.coordFld.lng);
                    this.geoLatFld.addEventListener('input', this.evtMarkerFromInput.bind(this));
                    this.geoLngFld.addEventListener('input', this.evtMarkerFromInput.bind(this));
                    //this.evtMarkerFromInput();
                }).bind(this))
            }
        }

        /**
         * EvtHandler:  Wenn der locationfound-Event seitens Leaflet ausgelöst wird,
         * die Karte also an die aktuelle Position des Devices geschoben wird, wird
         * auch der Position-Marker dorthin geschoben und die LatLng-Felder angepasst
         */
        evtMarkerFromLocate(e) {
            this.geoMarker.showValidPosition(e.latlng);
            this.geoSetLatLngFields(e.latlng);
        }

        /**
         * EvtHandler: Der Marker wurde per Drag verschoben und die Karte
         * automatisch angepasst. Die Information über die neue Position muss
         * auch an die Eingebefelder gegeben werden.
         */
        evtMarkerFromDrag(e) {
            this.geoSetLatLngFields(e.detail);
        }

        /**
         * EvtHandler: Aus dem Input der LatLng-Felder wird der Marker gesetzt
         * - Beide Felder leer: 
         *      auf die "Leer"-Karte springen (showVoidPosition)
         * - eine irgendwie ungültige oder teil-leere Eingabe:
         *      einen evtl schon vorhandenen Marker einfach ausblenden (showInvalidPosition)
         * - eine gültige Koordinate
         *      den Marker an die Position verschieben (showValidPosition
         * Marker entsprechend gesetzt.
         * 
         */
        evtMarkerFromInput(e) {
            let lat = this.geoLatFld.value.trim();
            let lng = this.geoLngFld.value.trim();
            let hasLat = lat > '' && !isNaN(lat);
            let hasLng = lng > '' && !isNaN(lng);

            if (!hasLat && !hasLng) {
                this.geoMarker.showVoidPosition();
                return;
            }
            if (hasLat && hasLng) {
                let pos = L.latLng(lat, lng);
                if (pos) {
                    this.geoMarker.showValidPosition(pos);
                    return;
                }
            }
            this.geoMarker.showInvalidPosition();
        }

        /**
         * Nach Doppelklick auf die Karte wird an der Event-Position der Marker gesetzt und die
         * Koordinaten in die Eingabefelder übertragen
         */
        evtMarkerFromDblClick(e) {
            this.geoMarker.showValidPosition(e.latlng);
            this.geoSetLatLngFields(e.latlng);
            return false;
        }

        /**
         * Die seitens die Suche (<geolocation-geocoder-search>) ausgewählte Adresse
         * Adresse wird in die Eingabefelder übertragen und der Marker an die Position
         */
        evtMarkerFromSearch(e) {
            let pos = L.latLng(e.detail.lat, e.detail.lng);
            this.geoMarker.showValidPosition(pos);
            this.geoSetLatLngFields(pos)
        }

        /**
         * Die Lat-Lng-Felder mit Inhalt füllen.
         */
        geoSetLatLngFields(pos) {
            this.geoLatFld.value = pos.lat || pos[0];
            this.geoLngFld.value = pos.lng || pos[1];
        }
    });
