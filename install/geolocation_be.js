/* Geolocation Backend-JS */

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
        this._setActiveState( false )

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
        if( '' === modal ) {
            return;
        }

        // https://stackoverflow.com/questions/1912501/unescape-html-entities-in-javascript
        let elem = document.createElement('textarea');
        elem.innerHTML = modal;
        modal = elem.value;   

        // Modal vorne im Body einhängen (verhindert "seltsame" CSS-Effekte).
        document.body.insertAdjacentHTML('afterbegin',modal);
        this.__modalNode = document.body.firstElementChild;

        // Plausibility-Test des Modals durch Abruf des Content-Nodes
        this.__modalContentNode = this.__modalNode.querySelector('.modal-body') || null;
        if(!this.__modalContentNode ) {
            // Aufräumen vor dem Abbruch
            this.__modalNode.remove();
            this.__modalNode = null;
            this.__api = null;
            return;
        }

        // Alles Übrige initialisieren
        if( 'loading' === document.readyState ) {
            document.addEventListener('DOMContentLoaded',this._initialize.bind(this));
        } else {
            this._initialize();
        }
    }

    disconnectedCallback() {
        this._turnOff();
        $(this.__modalNode).off('shown.bs.modal',this._validate.bind(this));
        this.removeEventListener('click',this._onClick.bind(this));
        if( this.__urlNode) {
            this.__urlNode.removeEventListener('input',this._checkActiveState.bind(this));
        }
        document.removeEventListener('DOMContentLoaded',this._DOMContentLoaded.bind(this));
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
        if(this.__urlNode && this._turnOn()) {
            // Event-Handler das Modal und auf sich selbst
            $(this.__modalNode).on('shown.bs.modal',this._validate.bind(this));
            this.addEventListener('click',this._onClick.bind(this));
            this.__urlNode.addEventListener('input',this._checkActiveState.bind(this));
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
    _checkActiveState () {
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
    _setActiveState( state ) {
        if( state ) {
            this.removeAttribute('disabled');
        } else {
            this.setAttribute('disabled','');
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
        if( this.hasAttribute('disabled') && 'false' !== this.getAttribute('disabled') ) {
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
        this.__api.set('url',this.__urlNode.value);
        this._setValidationParams(this.__api);
        fetch( window.location.origin + window.location.pathname, {
                method: 'POST',
                body: this.__api,
            })
            .then( (response ) => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.text();
            })
            .then( (HTML) => { this.__modalContentNode.innerHTML=HTML;})
            .catch( (error) => {
                this.__modalContentNode.innerHTML = '<p>'+Geolocation.i18n('There has been a problem with your fetch operation: {error}', {'error': error}) + '</p>';
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
    _setValidationParams( formData){
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

/**
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
customElements.define('geolocation-test-tile-url', class extends Geolocation.Classes.TestUrl
{
    __subdomainNode = null;

    /**
     * Überprüft subdomain und setzt ggf. die nötigen Event-Handler.
     */
    _turnOn() {
        // Id des SubDomain-Eingabefeldes angegeben, Feld existent?
        this.__subdomainNode = document.getElementById(this.getAttribute('subdomain')) || null;
        if(!this.__subdomainNode ) {
            return false;
        }

        // Event-Handler aktivieren
        this.__subdomainNode.addEventListener('input',this._checkActiveState.bind(this));
        return true;
    }

    /**
     * wird von disconnectedCallback aufgerufen und entfernt die 
     * EventListener, die mit _turnOn aktiviert wurden.
     */
    _turnOff() {
        if( this.__subdomainNode) {
            this.__subdomainNode.removeEventListener('input',this._checkActiveState.bind(this));
        }
    }

    /**
     * fügt die für dieses CustomFeld relevanten Parameter in das
     * FormData-Object "formData" ein, mit dem die Validierung per Ajax erfolgt
     */
    _setValidationParams( formData ){
        formData.set('subdomain',this.__subdomainNode.value);
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
        if( -1 === this.__urlNode.value.indexOf('{s}')) {
            return true;
        }
        // erforderliche SubDomain angegeben?
        if( 0 < this.__subdomainNode.value.trim().length ) {
            return true;
        }
        // Fehler, nötige Subdomain fehlt.
        return false;
    }

});
