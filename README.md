# Weather Widget für IP-Symcon

Zeigt eine mehrtägige Wettervorhersage als elegantes Widget im WaWö-Stil an. Perfekt für WebFront und IPSView.

![Widget Preview](docs/preview.png)

## Features

- **1-7 Tage** Vorhersage konfigurierbar
- **Temperaturbalken** im WaWö-Stil (vertikal versetzt nach Temperatur)
- **Niederschlag** mit mm-Angabe, Wahrscheinlichkeit in % und vertikalem Balken
- **Windgeschwindigkeit** mit vertikalem Balken
- **Animierte Wetter-Icons** (Bas Milius Weather Icons)
- **Responsive** – skaliert automatisch mit der Widget-Größe
- **Halbtransparenter Hintergrund** – passt sich jedem IPSView-Design an
- **Konfigurierbare Balken-Dimensionen** (Höhe und Breite)
- Regen und Wind einzeln ein-/ausblendbar
- Auto-Update im konfigurierbaren Intervall

## Voraussetzungen

- IP-Symcon **7.0** oder höher
- Ein Wetter-Modul das Tages-Vorhersagedaten liefert (z.B. OpenWeatherMap)

## Installation

1. In IP-Symcon die **Verwaltungskonsole** öffnen
2. Unter **Kern Instanzen → Module** auf **+** klicken
3. Folgende URL eingeben:
   ```
   https://github.com/DEIN-USER/IPSWeatherWidget
   ```
4. Instanz hinzufügen: **Gerät hinzufügen → WeatherWidget**

## Konfiguration

### Allgemein
| Eigenschaft | Beschreibung | Standard |
|---|---|---|
| Anzahl Tage | Wie viele Vorhersage-Tage angezeigt werden | 6 |
| Update-Intervall | Automatische Aktualisierung in Minuten | 5 |
| Niederschlag anzeigen | Regen-Zeile ein-/ausblenden | ✓ |
| Wind anzeigen | Wind-Zeile ein-/ausblenden | ✓ |

### Balken-Darstellung
| Eigenschaft | Beschreibung | Standard |
|---|---|---|
| Regenbalken Höhe | Höhe in Pixeln | 30px |
| Regenbalken Breite | Breite in % der Spalte | 70% |
| Windbalken Höhe | Höhe in Pixeln | 30px |
| Windbalken Breite | Breite in % der Spalte | 70% |

### Pro Tag (1-7)
| Variable | Beschreibung | Pflicht |
|---|---|---|
| Beginn (Timestamp) | Unix-Timestamp des Tagesbeginns | ✓ |
| Temp Min | Minimale Temperatur °C | ✓ |
| Temp Max | Maximale Temperatur °C | ✓ |
| Icon-Code (OWM) | OpenWeatherMap Icon-Code (z.B. "10d") | ✓ |
| Regen mm | Niederschlagsmenge in mm | Optional |
| Regen % | Regenwahrscheinlichkeit in % | Optional |
| Wind km/h | Windgeschwindigkeit in km/h | Optional |

## Unterstützte Icon-Codes

Das Widget mappt automatisch OpenWeatherMap Icon-Codes auf animierte [Bas Milius Weather Icons](https://github.com/basmilius/weather-icons):

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

## Verwendung in IPSView

1. **WebView-Element** hinzufügen
2. Die vom Modul erstellte **HTMLBox-Variable** auswählen
3. Fertig – das Widget skaliert automatisch

## Changelog

### 1.0.0
- Erstveröffentlichung
- Temperatur-, Regen- und Wind-Darstellung
- Animierte Bas Milius Icons
- Responsive Design
- Konfigurierbare Balken-Dimensionen

## Lizenz

MIT License

## Credits

- Wetter-Icons: [Bas Milius Weather Icons](https://github.com/basmilius/weather-icons)
- Inspiriert von der [WaWö Wetter-App](https://play.google.com/store/apps/details?id=de.wetterwolke.wetterwolke)
