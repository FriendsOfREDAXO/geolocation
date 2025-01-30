/**
 * Sonder-JS nur für Tablemanager-Seiten zur Feld-Konfiguration
 * 
 * Konkret geht es um das Ein- und Ausblenden von elementen Abhängig von einem Choice
 * 
 * Nachdem der DOM geladen wurde werden alle Elemente mit dem Attribut "geolocation-yvc"
 * herausgesucht. Der Attribut-Wert ist so strukturiert
 *  [
 *      'self' => '-type'                 // Die ID-Endung des Auswahlfeldes
 *      'group' => ['-xyz', '-abc'],      // Endungen der Feldnamen in der Gruppe
 *      'choice1' => ['-xyz'],            // zu aktivierende Felder wenn Choice-Value = choice1
 *      'choice2' => [-abc],              // zu aktivierende Felder wenn Choice-Value = choice2
 *      'choice3' => [],                  // Beispiel für alle ausblenden wenn Choice-Value = choice3
 *  ]
 * Beispielanwendung: YForm-Value geolocation_geocode
 */

/** */
Geolocation.func.initYTMToggle = function (node) {

    // Das Context-Attribut muss auch gefüllt sein
    let context = node.getAttribute('geolocation-yvc');
    if (!context) {
        return;
    }
    context = JSON.parse(context) || null;
    if (!context || !context.group || !context.self) {
        return;
    }

    /** 
     * Diverse nicht veränderliche Daten zusammensuchen und im Node speichern
     * container:   grenzt das Suchfeld ein; nur im Formular;
     * position:    Länge des vorderen, immer gleichen Teils der FeldID
     * baseId:      der vordere, immer gleiche Teil der Feld-IDs
     * fields:      Alle Felder im Formular, die mit der baseID beginnen 
    */
    node.ycv = {};
    node.ycv.context = context;
    node.ycv.container = node.closest('form');
    let baseId = node.id;
    node.ycv.position = baseId.lastIndexOf(context.self);
    node.ycv.baseId = baseId.substring(0, node.ycv.position);
    node.ycv.fields = node.ycv.container.querySelectorAll(`div[id^="${node.ycv.baseId}"]`);

    /**
     * Der Event-Listener reagiert bei Änderungen des Auswahlfeldes und
     * schaltet die zum selektierten Wert gehörenden Formularelemente ein
     * (node.ycv.context[value])
     */
    node.addEventListener('change', (e) => {
        let value = e.target.value;
        if (!node.ycv.context[value]) {
            return;
        }

        node.ycv.fields.forEach((field) => {
            let marker = field.id.substr(node.ycv.position);
            if (node.ycv.context.group.includes(marker)) {
                field.classList.toggle('hidden', !node.ycv.context[value].includes(marker))
            }
        });
    });

    /**
     * Einmalig triggern, um die Werte passend zu bekommen
     */
    node.dispatchEvent(new Event('change', { 'bubbles': true }));
}

document.addEventListener('DOMContentLoaded', (e) => {
    let nodes = document.querySelectorAll('[geolocation-yvc]');
    nodes.forEach(node => Geolocation.func.initYTMToggle(node));
})
