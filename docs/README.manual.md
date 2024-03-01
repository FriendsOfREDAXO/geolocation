# Hinweise zum Umgang mit dem Handbuch

Die Handbuchseiten sind in der package.yml definiert. 

- Der Einstiegspunkt ist auf der obersten Menü-Ebene des Addons (`page=geolocation/manual`);
- Es gibt zwei Menü-Ebenen im Handbuch
  - Ebene 1 wird im Addon-Menü als zweite Ebene angezeigt.
  - Ebene 2 (nur Entwickler und Installation), also Admin-relevant, haben eine weitere Ebene.
    Diese Seiten werden direkt über dem Text als Tab-Menü angezeigt.
- Alle Handbuch-Seiten werden mit `pages/manual.php` angezeigt. Die Einträge haben daher alle
  `subpath: pages/manual.php`.
- Die Zuordnung der Handbuch-Texte zu den Seiten im Addon erfolgt über den Namen des Eintrags.
  Z.B. ergibt sich aus dem Seitennamen `geolocation/manual/install/install` die Datei `docs/install.md`.

## Vorgegebene Dateistruktur

```
geolocation/docs            Hier liegen die Textdateien
geolocation/docs/assets     Hier liegen alle Assets, insb. die eingebundenen Grafiken
```


## Was muss man beim Schreiben beachten

Es gilt: "GitHub first", Alle Seiten sind so geschrieben und platziert, dass die Referenzen auf Assets
und andere Seiten auf Github korrekt angezeigt werden.

### Links zu anderen Seiten des Handbuchs

Links werden Markdown-konform als `[name](seite.md)` geschrieben. Da alle Seiten im selben Verzeichnis liegen,
lösen sich die Referenzen korrekt auf. Innerhalb der REDAXO-Instanz werden diese Links in Page-Links
der Redaxo-Instanz umgewandelt (`index-php?page=geolocation/manual/seite`).

Anker sind zulässig (`[name](seite.md#anker)`).

### Links zu Assets

Assets meint Bilder! Links werden Markdown-konform als `![name](assets/grafik.jpg)` geschrieben.
Innerhalb der REDAXO-Instanz werden die Bilder-Links in Page-Links mit angehängtem Ressourcen-Namen
umgewandelt (`index-php?page=geolocation/manual/seite&res=grafik.jpg`). 

### URI-Links und andere

Links, die klassisch als URI ausgelegt sind (`irgendwas://der.rest?der=url`) bleiben unverändert. Das
gilt auch für jeden anderen Link.

### Code-Blöcke

Damit der Link-Umbau in der Instanz funktioniert, müssen Code-Blöcke ausgenommen werden.
Als Code-Blöcke werden hierbei nur die Code-Blöcke in Backticks (\`...\` bzw. \`\`\`...\`\`\`)
berücksichtigt. Keine Codeblöcke durch Einrückung!!!

### Hauptmenü für die GitHub Darstellung

Das mehrseitige Handbuch hat auf jeder Seite eine Linkliste auf die anderen Seiten.
Die Zeilen beginnen alle mit `>` und müssen ab der ersten Zeile der Datei lückenlos
sein. Dieser Block (alle fortlaufenden Zeilen am Anfang der Datei, die mit `>` beginnen)
wird in der Redaxo-Instanz entfernt, denn dort gibt es ja ein Menü im Addon.

### Codeblock-Beautifier

Code-Blöcke werden optisch verschönert. Da REDAXO sowas intern nur für PHP unterstützt,
greifen wir auf PrismJS zurück. Damit können verschiedene Sprachen bearbeitet werden.

Sprachcode nur in Kleinbuchstaben!

- \`\`\`php 
- \`\`\`js 
- \`\`\`htm 
- \`\`\`css
- \`\`\`scss
- \`\`\`svg
- \`\`\`sql 