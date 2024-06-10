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
         if( !this.__select || !this.__container ) {
             return;
         }
         this.__observer = new MutationObserver(this._addEntry.bind(this));
         this.__observer.observe(this.__select,{childList:true});
     }

     /**
      * Wenn vom YForm-Popup ein neu ausgwählter Layer im select abgelegt wurde,
      * wird der Eintrag in einen template-konformen Einrag im Ziel-Container
      * umgewandelt. 
      * Doppelte Einträge werden ignoriert.
      * 
      * @param {MutationRecord[]} mutations 
      */
     _addEntry( mutations ) {
         mutations.forEach(mutation => mutation.addedNodes.forEach( option => {
             if (!this.__container.querySelector(`[value="${option.value}"]`) ) {
                 let checked = this.__isRadio && 0 == this.__container.children.length;
                 let template = this.__template
                     .replaceAll('{label}',option.innerHTML)
                     .replaceAll('{value}',option.value)
                     .replaceAll('{checked}',checked?'checked':'');
                 this.__container.insertAdjacentHTML('beforeEnd',template);
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

         if( container && 0 < container.childNodes.length) {
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
                if( !event.repeat ) {
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
