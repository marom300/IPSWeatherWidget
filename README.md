# Weather Widget für IP-Symcon

Konfigurierbares Wetter-Vorhersage-Widget für IP-Symcon im modernen WaWö-Stil. Optimiert für WebFront und IPSView mit voller Transparenz-Unterstützung.

![Widget Preview](docs/preview.png)

## Features

### Anzeige & Design
- **1–7 Tage** Vorhersage (ab heute oder ab morgen konfigurierbar)
- **Temperaturbalken** mit Min/Max-Werten und globaler Skalierung
- **Niederschlag** mit mm-Angabe, Wahrscheinlichkeit in % und vertikalem Balken
- **Windgeschwindigkeit** mit vertikalem Balken
- **Animierte Wetter-Icons** (Bas Milius Weather Icons, SVG-basiert)
- **Optionaler Icon-Header** – kompakte Ansicht als separate HTMLBox-Variable
- **Frei konfigurierbare Zeilen-Reihenfolge** (Temperatur, Regen, Wind, Icons, Wochentage)
- **12 individuell einstellbare Farben** + Hintergrundfarbe mit Deckkraft-Slider (0–100%)
- **Volle Transparenz-Unterstützung** für IPSView
- **Vollständig responsive** – skaliert automatisch auf jede Bildschirmgröße
- Regen und Wind einzeln ein-/ausblendbar

### Datenquellen
- **OpenWeatherMap OneCall** – automatische Variablen-Erkennung
- **Weather Underground** – automatische Variablen-Erkennung
- **MET Norway** – direkte API-Anbindung (Latitude, Longitude, Altitude)
- **Manuelle Zuordnung** – funktioniert mit jedem beliebigen Wettermodul
- Froggit & Netatmo (in Planung)

### Technisches
- **Auto-Konfiguration**: Variablen werden automatisch aus dem Quell-Modul erkannt und zugeordnet
- **Diagnose-Tool**: Alle verfügbaren Identifiers im Quell-Modul anzeigen
- **Responsive CSS** mit `clamp()`-Funktionen für flüssige Skalierung
- **Google Fonts** (Inter) für moderne Typografie
- **Lazy Loading** für Icons

## Voraussetzungen

- IP-Symcon **5.0** oder höher
- Ein Wetter-Modul das Tages-Vorhersagedaten liefert (z.B. OpenWeatherMap, Weather Underground, MET Norway) – oder manuelle Variablen-Zuordnung

## Installation

1. In IP-Symcon die **Verwaltungskonsole** öffnen
2. Unter **Kern Instanzen → Module** auf **+** klicken
3. Folgende URL eingeben:
   ```
   https://github.com/DEIN-USER/IPSWeatherWidget
   ```
4. Instanz hinzufügen: **Gerät hinzufügen → WeatherWidget**
5. **Wetter-Modul-Typ** und **Quell-Instanz** auswählen
6. Button **„Variablen automatisch zuordnen"** klicken
7. Fertig – die HTMLBox-Variable im WebFront oder IPSView platzieren

## Konfiguration

### Auto-Erkennung

| Eigenschaft | Beschreibung |
|---|---|
| Wetter-Modul-Typ | OpenWeather, Weather Underground, MET Norway, etc. |
| Quell-Instanz | Die Wetter-Modul-Instanz in IP-Symcon |
| Starttag | Ab heute (D0) oder ab morgen (D1) |
| MET Norway | Latitude, Longitude, Altitude (nur bei MET Norway) |

### Allgemein

| Eigenschaft | Beschreibung | Standard |
|---|---|---|
| Anzahl Tage | Wie viele Vorhersage-Tage angezeigt werden | 6 |
| Update-Intervall | Automatische Aktualisierung in Minuten | 5 |
| Niederschlag anzeigen | Regen-Zeile ein-/ausblenden | ✓ |
| Wind anzeigen | Wind-Zeile ein-/ausblenden | ✓ |
| Icon-Header anzeigen | Kompakten Icon-Header als separate Variable erstellen | ✗ |

### Icon-Header

| Eigenschaft | Beschreibung | Standard |
|---|---|---|
| Icon-Größe | Größe der Wetter-Icons | 40px |
| Wochentag anzeigen | Tagesbezeichnung ein-/ausblenden | ✓ |
| Temperatur anzeigen | Min/Max-Temperatur ein-/ausblenden | ✓ |
| Hintergrundfarbe | Farbe des Headers | transparent |
| Deckkraft | Transparenz 0–100% | 0% |

### Darstellung

| Eigenschaft | Beschreibung | Standard |
|---|---|---|
| Zeilen-Reihenfolge | Reihenfolge von Temp, Regen, Wind, Icons, Tage | Temp → Regen → Wind → Icons → Tage |
| Regenbalken Höhe | Höhe in Pixeln | 30px |
| Regenbalken Breite | Breite in % der Spalte | 70% |
| Windbalken Höhe | Höhe in Pixeln | 30px |
| Windbalken Breite | Breite in % der Spalte | 70% |
| Icon-Größe | Größe der Wetter-Icons | 50px |
| Hintergrundfarbe | Farbe der Widget-Box | transparent |
| Deckkraft | Transparenz 0–100% | 0% |

### Farben

| Farbe | Beschreibung | Standard |
|---|---|---|
| Temp Max | Farbe für Maximaltemperatur | Weiß |
| Temp Min | Farbe für Minimaltemperatur | Weiß |
| Temp Balken | Farbe der Temperaturbalken | Gold |
| Heute | Akzentfarbe für den aktuellen Tag | Gold |
| Regen Label | Beschriftung Niederschlag (> 0mm) | Weiß |
| Regen Label (0) | Beschriftung Niederschlag (0mm) | Grau |
| Regen Chance | Regenwahrscheinlichkeit in % | Grau |
| Regen Balken | Farbe der Regenbalken | Blau |
| Wind Label | Beschriftung Windgeschwindigkeit | Weiß |
| Wind Balken | Farbe der Windbalken | Grau |
| Wochentag | Farbe der Tagesbezeichnung | Weiß |

### Variablen-Zuordnung (pro Tag 1–7)

| Variable | Beschreibung | Pflicht |
|---|---|---|
| Beginn (Timestamp) | Unix-Timestamp des Tagesbeginns | ✓ |
| Temp Min | Minimale Temperatur °C | ✓ |
| Temp Max | Maximale Temperatur °C | ✓ |
| Icon-Code | OWM-Code, MET Norway Symbol oder Text | ✓ |
| Regen mm | Niederschlagsmenge in mm | Optional |
| Regen % | Regenwahrscheinlichkeit in % | Optional |
| Wind km/h | Windgeschwindigkeit in km/h | Optional |

## Transparenz in IPSView

Das Widget unterstützt volle Transparenz für die Einbettung in IPSView:

- `html` und `body` sind **immer transparent** – der IPSView-Hintergrund scheint durch
- Die Widget-Box hat eine eigene **Hintergrundfarbe mit einstellbarer Deckkraft** (0–100%)
- **0% Deckkraft**: Vollständig transparent (nur Inhalt sichtbar, Wallpaper scheint durch)
- **50% Deckkraft**: Halbtransparent (Farbe + Wallpaper mischen sich)
- **100% Deckkraft**: Voll deckend in der gewählten Farbe

## Unterstützte Icon-Codes

Das Widget mappt automatisch verschiedene Wetter-Codes auf animierte [Bas Milius Weather Icons](https://github.com/basmilius/weather-icons):

### OpenWeatherMap

| OWM Code | Beschreibung |
|---|---|
| 01d/01n | Klarer Himmel |
| 02d/02n | Leicht bewölkt |
| 03d/03n | Bewölkt |
| 04d/04n | Stark bewölkt |
| 09d/09n | Schauer |
| 10d/10n | Regen |
| 11d/11n | Gewitter |
| 13d/13n | Schnee |
| 50d/50n | Nebel |

### MET Norway

MET Norway Symbol-Codes (z.B. `clearsky_day`, `rain`, `heavysnow`) werden automatisch auf die passenden Icons gemappt.

### Text-basiert

Für Module die Text-Vorhersagen liefern (z.B. Weather Underground) werden 60+ Schlüsselwörter in Deutsch und Englisch erkannt (z.B. „sonnig", „Gewitter", „cloudy", „snow").

## Ausgabe-Variablen

| Variable | Typ | Beschreibung |
|---|---|---|
| WeatherHTML | ~HTMLBox | Vollständiges Wetter-Widget |
| WeatherIconHeader | ~HTMLBox | Kompakter Icon-Header (optional) |

## Öffentliche Funktionen

| Funktion | Beschreibung |
|---|---|
| `WTR_Update($id)` | Widget manuell aktualisieren |
| `WTR_AutoConfigure($id)` | Variablen automatisch zuordnen |
| `WTR_ScanIdents($id)` | Alle Identifiers im Quell-Modul anzeigen |
| `WTR_FetchMETNorway($id)` | MET Norway API manuell abrufen |

## Changelog

### 1.0.0
- Erstveröffentlichung
- Temperatur-, Regen- und Wind-Darstellung
- Animierte Bas Milius Icons
- Responsive Design
- Konfigurierbare Balken-Dimensionen
- Auto-Konfiguration für OpenWeather, Weather Underground, MET Norway
- Kompakter Icon-Header (optional)
- Frei konfigurierbare Zeilen-Reihenfolge
- 12 konfigurierbare Farben
- Hintergrundfarbe mit Deckkraft-Slider (0–100%)
- Volle Transparenz-Unterstützung für IPSView

## Lizenz

MIT License

## Credits

- Wetter-Icons: [Bas Milius Weather Icons](https://github.com/basmilius/weather-icons)
- Schriftart: [Inter](https://fonts.google.com/specimen/Inter) (Google Fonts)
- Inspiriert von der [WaWö Wetter-App](https://play.google.com/store/apps/details?id=de.wetterwolke.wetterwolke)
