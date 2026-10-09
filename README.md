# Shortcuts für IP-Symcon

[![IP-Symcon ab 8.2](https://img.shields.io/badge/IP--Symcon-ab_8.2-0b6fb3.svg)](https://www.symcon.de)
[![Optimiert für Symcon 9.0](https://img.shields.io/badge/optimiert_f%C3%BCr-Symcon_9.0-0b6fb3.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v81-v90-q1-2026/)
[![Modul-Version 1.1 (Build 2)](https://img.shields.io/badge/Modul--Version-1.1_(Build_2)-informational.svg)](library.json)
[![Tests](https://github.com/cfaf2002/Shortcuts_Symcon/actions/workflows/tests.yml/badge.svg)](https://github.com/cfaf2002/Shortcuts_Symcon/actions/workflows/tests.yml)
[![PHP 8.3 und 8.5](https://img.shields.io/badge/PHP-8.3_%7C_8.5-777bb4.svg?logo=php&logoColor=white)](https://www.php.net)
[![SDK: IPSModuleStrict](https://img.shields.io/badge/SDK-IPSModuleStrict-success.svg)](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/)
[![Variablen: Darstellungen](https://img.shields.io/badge/Variablen-Darstellungen-success.svg)](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/)
[![Kachel-Visualisierung: HTML-SDK](https://img.shields.io/badge/Kachel--Visualisierung-HTML--SDK-orange.svg)](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/)
[![Farbschema: Symcon-Design, Dunkel, Hell](https://img.shields.io/badge/Farbschema-Symcon--Design_%7C_Dunkel_%7C_Hell-blueviolet.svg)](STYLEGUIDE.md)
![Sprachen: Deutsch, Englisch](https://img.shields.io/badge/Sprachen-Deutsch_%7C_Englisch-blueviolet.svg)
[![Lizenz: MIT](https://img.shields.io/badge/Lizenz-MIT-green.svg)](LICENSE)

Eine Kachel mit frei konfigurierbaren Knöpfen, die direkt zu einer Variable, Instanz oder Kategorie der Kachel-Visualisierung springen – auf Wunsch mit dem aktuellen Wert der Variable im Knopf.

## Inhalt

| Modul | Typ | Aufgabe |
| :---- | :-- | :------ |
| Shortcuts | Gerät | Kachel mit Sprung-Knöpfen zu beliebigen Objekten |

## Funktionsumfang

- Beliebig viele Knöpfe, Reihenfolge im Formular verschiebbar.
- Je Knopf: Objekt, Name (leer = Objektname), Symbol aus der Symbolauswahl von Symcon (leer = Symbol des Objekts), Farbe, Wert anzeigen.
- Lädt ein Symbol in der Kachel nicht, erscheint ein eingebautes Ersatzsymbol nach Objekttyp.
- Antippen öffnet das Objekt in der Visualisierung: Variablen und Instanzen als Vollbild-Kachel, Kategorien als Seite. Verknüpfungen werden auf ihr Ziel aufgelöst.
- Werte von Variablen erscheinen formatiert im Knopf und werden bei jeder Änderung aktualisiert (nur geänderte Werte werden gesendet).
- Darstellung als Raster oder Liste, optionale Überschrift.

## Voraussetzungen und Technik

- IP-Symcon ab 8.2 (die Funktion `openObject` des HTML-SDK gibt es erst seit 8.2), optimiert für 9.0.
- Kachel-Visualisierung.
- `IPSModuleStrict`, keine eigenen Variablen oder Profile.

## Installation

1. Im Objektbaum unter *Kern Instanzen → Modules* die URL `https://github.com/cfaf2002/Shortcuts_Symcon` hinzufügen.
2. Instanz **Shortcuts** anlegen.

## Einrichtung

1. In der Liste „Schnellzugriffe“ mit *Hinzufügen* je Knopf ein Objekt wählen, bei Bedarf Name, Symbol und Farbe setzen.
2. Unter „Kachel“ Farbschema, Darstellung und Überschrift wählen.
3. Die Instanz (oder eine Verknüpfung darauf) in der Kachel-Visualisierung einblenden.

Tipp: Bei Variablen, die in einer Instanz liegen, heißt das Objekt oft nur „Status“ oder „Zustand“ – dann lohnt sich ein eigener Name wie „Wohnzimmer Licht“.

## Kachel

| Einstellung | Werte |
| :-- | :-- |
| Farbschema der Kachel | Symcon-Design (Farben der Visualisierung), Dunkel, Hell |
| Darstellung | Raster (Knöpfe nebeneinander) oder Liste (eine Zeile je Knopf) |
| Überschrift | frei, leer = keine |

Ohne gewählte Farbe nimmt ein Knopf die Akzentfarbe (Symcon-Design) bzw. die Markenfarbe des Moduls (Dunkel/Hell).

## Variablen und Darstellungen

Das Modul legt keine Variablen an.

## PHP-Befehle

Keine.

## Sicherheit und Geschwindigkeit

- Die Kachel bekommt nur ID, Name, Symbol, Farbe und formatierten Wert der konfigurierten Objekte; alle Texte werden per `textContent` gesetzt.
- Das Springen übernimmt die Visualisierung selbst (`openObject`) – das Modul schaltet nichts.
- Werte werden ereignisgesteuert (`VM_UPDATE`) und nur bei Änderung an die Kachel geschickt.

## Entwicklung und Tests

```bash
php tests/structure.php
git clone --depth 1 https://github.com/symcon/SymconStubs.git ../SymconStubs
php tests/stubs.php ../SymconStubs
```

## Changelog

| Version | Build | Datum | Beschreibung |
| :-- | --: | :-- | :-- |
| 1.1 | 2 | 09.10.2026 | Symbole aus der Symbolauswahl von Symcon (leer = Symbol des Objekts); Knöpfe beginnen unter dem Kacheltitel statt ihn zu überdecken |
| 1.0 | 1 | 09.10.2026 | Erste Version: Sprung-Knöpfe mit Symbol, Farbe und Wert, Raster oder Liste |

## Lizenz

MIT – siehe [LICENSE](LICENSE). Copyright (c) 2026 Armin Frohwerk.
