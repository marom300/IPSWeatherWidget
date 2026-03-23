<?php

declare(strict_types=1);

/**
 * WeatherWidget – Wetter-Vorhersage-Widget für IP-Symcon
 *
 * Zeigt eine mehrtägige Wettervorhersage im WaWö-Stil an:
 * Temperaturbalken, Niederschlag, Wind, animierte Icons.
 *
 * Universell einsetzbar mit jedem Wetter-Modul (OpenWeather, WeatherUnderground, etc.)
 * Variablen können manuell oder per Auto-Erkennung zugewiesen werden.
 *
 * Konfigurierbar über die IPS-Modulkonfiguration.
 * Ausgabe als HTMLBox-Variable für WebFront / IPSView.
 */
class WeatherWidget extends IPSModuleStrict
{
    // OWM Icon-Code → Bas Milius Dateiname
    private const ICON_MAP = [
        '01d' => 'clear-day',
        '01n' => 'clear-night',
        '02d' => 'partly-cloudy-day',
        '02n' => 'partly-cloudy-night',
        '03d' => 'cloudy',
        '03n' => 'cloudy',
        '04d' => 'overcast-day',
        '04n' => 'overcast-night',
        '09d' => 'overcast-day-drizzle',
        '09n' => 'overcast-night-drizzle',
        '10d' => 'overcast-day-rain',
        '10n' => 'overcast-night-rain',
        '11d' => 'thunderstorms-day-rain',
        '11n' => 'thunderstorms-night-rain',
        '13d' => 'overcast-day-snow',
        '13n' => 'overcast-night-snow',
        '50d' => 'mist',
        '50n' => 'mist',
    ];

    private const ICON_BASES = [
        'basmilius_fill' => 'https://cdn.jsdelivr.net/gh/basmilius/weather-icons@dev/production/fill/svg',
        'basmilius_line' => 'https://cdn.jsdelivr.net/gh/basmilius/weather-icons@dev/production/line/svg',
    ];
    private const WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    // Keyword→Icon-Mapping für Text-Vorhersagen (Wunderground etc.)
    // Reihenfolge wichtig: spezifischere Keywords zuerst!
    private const FORECAST_TEXT_MAP = [
        // Gewitter
        'gewitter'          => 'thunderstorms-day-rain',
        'thunder'           => 'thunderstorms-day-rain',
        'storm'             => 'thunderstorms-day-rain',
        // Schnee
        'schneeregen'       => 'overcast-day-sleet',
        'sleet'             => 'overcast-day-sleet',
        'schneeschauer'     => 'overcast-day-snow',
        'snow shower'       => 'overcast-day-snow',
        'schnee'            => 'overcast-day-snow',
        'snow'              => 'overcast-day-snow',
        // Regen
        'regenschauer'      => 'overcast-day-rain',
        'rain shower'       => 'overcast-day-rain',
        'schauer'           => 'overcast-day-rain',
        'shower'            => 'overcast-day-rain',
        'starker regen'     => 'overcast-day-rain',
        'heavy rain'        => 'overcast-day-rain',
        'leichter regen'    => 'overcast-day-drizzle',
        'light rain'        => 'overcast-day-drizzle',
        'nieselregen'       => 'overcast-day-drizzle',
        'drizzle'           => 'overcast-day-drizzle',
        'regen'             => 'overcast-day-rain',
        'rain'              => 'overcast-day-rain',
        // Nebel
        'nebel'             => 'mist',
        'neblig'            => 'mist',
        'fog'               => 'mist',
        'mist'              => 'mist',
        'dunst'             => 'mist',
        'haze'              => 'mist',
        // Bewölkung (spezifischer zuerst)
        'überwiegend sonnig'    => 'partly-cloudy-day',
        'mostly sunny'          => 'partly-cloudy-day',
        'teils bewölkt'         => 'partly-cloudy-day',
        'teilweise bewölkt'     => 'partly-cloudy-day',
        'partly cloudy'         => 'partly-cloudy-day',
        'teils sonnig'          => 'partly-cloudy-day',
        'wechselnd bewölkt'     => 'partly-cloudy-day',
        'überwiegend bewölkt'   => 'overcast-day',
        'mostly cloudy'         => 'overcast-day',
        'stark bewölkt'         => 'overcast-day',
        'bedeckt'               => 'overcast-day',
        'overcast'              => 'overcast-day',
        'bewölkt'               => 'cloudy',
        'cloudy'                => 'cloudy',
        'wolkig'                => 'cloudy',
        // Klar / Sonnig
        'sonnig'            => 'clear-day',
        'sunny'             => 'clear-day',
        'klar'              => 'clear-day',
        'clear'             => 'clear-day',
        'heiter'            => 'clear-day',
        'fair'              => 'partly-cloudy-day',
    ];

    // Provider-Typen
    private const PROVIDER_OPENWEATHER = 1;
    private const PROVIDER_WUNDERGROUND = 2;
    private const PROVIDER_FROGGIT = 3;
    private const PROVIDER_NETATMO = 4;
    private const PROVIDER_METNORWAY = 5;

    // OpenWeather Ident-Mapping: Widget-Feld → mögliche Ident-Prefixes (+ Tag-Suffix)
    private const OPENWEATHER_MAP = [
        'Begin'     => ['DailyForecastBegin', 'Daily_Timestamp', 'ForecastBegin', 'Forecast_Begin', 'ForecastTimestamp', 'Forecast_Timestamp'],
        'TempMin'   => ['DailyForecastTemperatureMin', 'Daily_TempMin', 'ForecastTemperatureMin', 'ForecastTempMin', 'Forecast_TempMin', 'Forecast_TemperatureMin'],
        'TempMax'   => ['DailyForecastTemperatureMax', 'Daily_TempMax', 'ForecastTemperatureMax', 'ForecastTempMax', 'Forecast_TempMax', 'Forecast_TemperatureMax'],
        'Icon'      => ['DailyForecastConditionIcon', 'Daily_ConditionIcon', 'ForecastConditionIcon', 'ForecastIcon', 'Forecast_Icon', 'Forecast_ConditionIcon'],
        'RainMM'    => ['DailyForecastRain', 'Daily_Rain', 'ForecastRain', 'Forecast_Rain', 'ForecastPrecipitation', 'Forecast_Precipitation'],
        'RainPct'   => ['DailyForecastRainProbability', 'Daily_RainProbability', 'ForecastRainProbability', 'Forecast_RainProbability', 'ForecastPOP', 'Forecast_POP'],
        'WindSpeed' => ['DailyForecastWindSpeed', 'Daily_WindSpeed', 'ForecastWindSpeed', 'Forecast_WindSpeed'],
    ];

    // WeatherUnderground Ident-Mapping: Widget-Feld → Ident-Suffix (Prefix ist D{N})
    private const WUNDERGROUND_MAP = [
        'TempMin'   => ['TemperatureMin'],
        'TempMax'   => ['TemperatureMax'],
        'Icon'      => ['Forecast'],
        'RainMM'    => ['QPF'],
    ];


    public function Create(): void
    {
        parent::Create();

        // Quell-Instanz für Auto-Erkennung
        $this->RegisterPropertyInteger('SourceType', 0); // 0=nicht gewählt, 1=OpenWeather, 2=Wunderground, 3=Froggit, 4=Netatmo, 5=MET Norway
        $this->RegisterPropertyInteger('SourceInstance', 0);
        $this->RegisterPropertyInteger('StartDay', 0); // 0=D0 (heute), 1=D1 (morgen)

        // MET Norway Einstellungen (kein IPS-Modul nötig, direkte API-Anbindung)
        $this->RegisterPropertyString('METLatitude', '48.2082');
        $this->RegisterPropertyString('METLongitude', '16.3738');
        $this->RegisterPropertyInteger('METAltitude', 171);

        // Allgemeine Einstellungen
        $this->RegisterPropertyInteger('DayCount', 6);
        $this->RegisterPropertyInteger('UpdateInterval', 5);
        $this->RegisterPropertyBoolean('ShowRain', true);
        $this->RegisterPropertyBoolean('ShowWind', true);
        $this->RegisterPropertyBoolean('ShowIconHeader', false);
        $this->RegisterPropertyBoolean('IconHeaderShowDay', true);
        $this->RegisterPropertyBoolean('IconHeaderShowTemp', true);
        $this->RegisterPropertyInteger('IconHeaderIconSize', 40);
        $this->RegisterPropertyInteger('IconHeaderBgColor', -1); // -1 = transparent
        $this->RegisterPropertyInteger('IconHeaderBgOpacity', 0); // 0-100%

        // Icon-Style
        $this->RegisterPropertyString('IconStyle', 'basmilius_fill');
        $this->RegisterPropertyString('CustomIconBaseURL', '');

        // Zeilen-Reihenfolge (von oben nach unten)
        $this->RegisterPropertyString('RowPos1', 'temp');
        $this->RegisterPropertyString('RowPos2', 'rain');
        $this->RegisterPropertyString('RowPos3', 'wind');
        $this->RegisterPropertyString('RowPos4', 'icons');
        $this->RegisterPropertyString('RowPos5', 'days');

        // Balken-Darstellung
        $this->RegisterPropertyInteger('RainBarHeight', 30);
        $this->RegisterPropertyInteger('RainBarWidth', 70);
        $this->RegisterPropertyInteger('WindBarHeight', 30);
        $this->RegisterPropertyInteger('WindBarWidth', 70);
        $this->RegisterPropertyInteger('IconSize', 50);

        // Farben
        $this->RegisterPropertyInteger('ColorTempMax', 0xFFFFFF);
        $this->RegisterPropertyInteger('ColorTempMin', 0xFFFFFF);
        $this->RegisterPropertyInteger('ColorToday', 0xF5C842);
        $this->RegisterPropertyInteger('ColorRainLabel', 0xFFFFFF);
        $this->RegisterPropertyInteger('ColorRainLabelZero', 0x6E7681);
        $this->RegisterPropertyInteger('ColorRainChance', 0x9EA7B1);
        $this->RegisterPropertyInteger('ColorRainBar', 0x2F81F7);
        $this->RegisterPropertyInteger('ColorWindLabel', 0xFFFFFF);
        $this->RegisterPropertyInteger('ColorWindBar', 0x8B949E);
        $this->RegisterPropertyInteger('ColorDayLabel', 0xFFFFFF);
        $this->RegisterPropertyInteger('ColorTempBar', 0xD4A017);
        $this->RegisterPropertyInteger('WidgetBgColor', -1); // -1 = transparent/Standard-Gradient
        $this->RegisterPropertyInteger('WidgetBgOpacity', 0); // 0-100%

        // Tag 1-7 Variablen-IDs
        for ($i = 1; $i <= 7; $i++) {
            $this->RegisterPropertyInteger("Day{$i}_Begin", 0);
            $this->RegisterPropertyInteger("Day{$i}_TempMin", 0);
            $this->RegisterPropertyInteger("Day{$i}_TempMax", 0);
            $this->RegisterPropertyInteger("Day{$i}_Icon", 0);
            $this->RegisterPropertyInteger("Day{$i}_RainMM", 0);
            $this->RegisterPropertyInteger("Day{$i}_RainPct", 0);
            $this->RegisterPropertyInteger("Day{$i}_WindSpeed", 0);
        }

        // Timer für automatisches Update
        $this->RegisterTimer('WTR_UpdateTimer', 0, 'WTR_Update($_IPS[\'TARGET\']);');
    }

    /**
     * Konfigurationsformular dynamisch anpassen
     * Setzt initiale Sichtbarkeit der MET/Instanz-Felder je nach SourceType
     */
    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $isMET = $this->ReadPropertyInteger('SourceType') === self::PROVIDER_METNORWAY;

        // Elemente im Form durchgehen und visible setzen
        foreach ($form['elements'] as &$panel) {
            if (!isset($panel['items'])) {
                continue;
            }
            foreach ($panel['items'] as &$item) {
                if (isset($item['name'])) {
                    if ($item['name'] === 'InstanceRow') {
                        $item['visible'] = !$isMET;
                    }
                    if ($item['name'] === 'METConfigRow') {
                        $item['visible'] = $isMET;
                    }
                }
            }
        }

        return json_encode($form);
    }

    /**
     * RequestAction: Reagiert auf onChange-Events aus dem Formular
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        switch ($ident) {
            case 'SourceTypeChanged':
                $isMET = ((int) $value === self::PROVIDER_METNORWAY);
                $this->UpdateFormField('InstanceRow', 'visible', !$isMET);
                $this->UpdateFormField('METConfigRow', 'visible', $isMET);
                break;
            default:
                parent::RequestAction($ident, $value);
                break;
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // HTMLBox-Variablen registrieren
        $this->RegisterVariableString('WeatherHTML', 'Weather Widget', '~HTMLBox', 0);

        // Icon-Header (kompakte Ansicht nur mit Icons + Wochentagen)
        if ($this->ReadPropertyBoolean('ShowIconHeader')) {
            $this->RegisterVariableString('WeatherIconHeader', 'Weather Icon Header', '~HTMLBox', 1);
        } else {
            // Variable entfernen wenn deaktiviert
            $vid = @$this->GetIDForIdent('WeatherIconHeader');
            if ($vid !== false) {
                $this->UnregisterVariable('WeatherIconHeader');
            }
        }

        // Timer setzen
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('WTR_UpdateTimer', $interval * 60 * 1000);

        // Prüfen ob mindestens Tag 1 TempMin konfiguriert ist
        $day1TempMin = $this->ReadPropertyInteger('Day1_TempMin');
        if ($day1TempMin === 0) {
            $this->SetStatus(104); // Inaktiv
            return;
        }

        $this->SetStatus(102); // Aktiv
        $this->Update();
    }

    /**
     * Öffentliche Funktion: Widget aktualisieren
     * Aufrufbar via WTR_Update($InstanceID)
     */
    public function Update(): void
    {
        $dayCount = $this->ReadPropertyInteger('DayCount');
        $showRain = $this->ReadPropertyBoolean('ShowRain');
        $showWind = $this->ReadPropertyBoolean('ShowWind');

        // Bei MET Norway: Daten von API abrufen bevor wir rendern
        $sourceType = $this->ReadPropertyInteger('SourceType');
        if ($sourceType === self::PROVIDER_METNORWAY) {
            $this->FetchMETNorway();
        }

        // Tages-Daten sammeln
        $days = [];
        for ($i = 1; $i <= $dayCount; $i++) {
            $day = $this->ReadDayData($i);
            if ($day === null) {
                continue;
            }
            $days[] = $day;
        }

        if (empty($days)) {
            $this->SetStatus(200);
            $this->SetValue('WeatherHTML', '<div style="color:#f85149;padding:20px;text-align:center;">Keine gültigen Variablen konfiguriert</div>');
            return;
        }

        $this->SetStatus(102);

        // HTML generieren
        $html = $this->GenerateHTML($days, $showRain, $showWind);
        $this->SetValue('WeatherHTML', $html);

        // Icon-Header generieren (falls aktiviert)
        if ($this->ReadPropertyBoolean('ShowIconHeader')) {
            $iconHeaderHtml = $this->GenerateIconHeaderHTML($days);
            $this->SetValue('WeatherIconHeader', $iconHeaderHtml);
        }

        $this->SendDebug('Update', 'Widget aktualisiert mit ' . count($days) . ' Tagen', 0);
    }

    /**
     * Alle Idents der Quell-Instanz auflisten (Diagnose)
     * Aufrufbar via WTR_ScanIdents($InstanceID)
     */
    public function ScanIdents(): string
    {
        $sourceID = $this->ReadPropertyInteger('SourceInstance');
        if ($sourceID === 0 || !IPS_ObjectExists($sourceID)) {
            return 'Bitte zuerst eine Wetter-Instanz auswählen und Konfiguration speichern.';
        }

        $childIDs = IPS_GetChildrenIDs($sourceID);
        $idents = [];
        foreach ($childIDs as $childID) {
            $obj = IPS_GetObject($childID);
            $idents[] = $obj['ObjectIdent'] . ' -> ' . $obj['ObjectName'] . ' (ID: ' . $childID . ')';
        }
        sort($idents);

        $msg = "Gefundene Idents in Instanz #{$sourceID}:\n\n";
        $msg .= implode("\n", $idents);

        $this->SendDebug('ScanIdents', $msg, 0);
        return $msg;
    }

    /**
     * Auto-Erkennung durchführen und Variablen direkt setzen
     * Aufrufbar via WTR_AutoConfigure($InstanceID)
     */
    public function AutoConfigure(): string
    {
        $sourceType = $this->ReadPropertyInteger('SourceType');
        if ($sourceType === 0) {
            return 'Bitte zuerst einen Wetter-Modul Typ auswählen und Konfiguration speichern.';
        }

        $dayCount = $this->ReadPropertyInteger('DayCount');
        $startDay = $this->ReadPropertyInteger('StartDay');

        // MET Norway braucht keine Quell-Instanz
        if ($sourceType === self::PROVIDER_METNORWAY) {
            $identMap = [];
        } else {
            $sourceID = $this->ReadPropertyInteger('SourceInstance');
            if ($sourceID === 0 || !IPS_ObjectExists($sourceID)) {
                return 'Bitte zuerst eine Wetter-Instanz auswählen und Konfiguration speichern.';
            }

            // Alle Kind-Variablen mit Idents einlesen
            $childIDs = IPS_GetChildrenIDs($sourceID);
            $identMap = []; // ident (lowercase) → varID
            foreach ($childIDs as $childID) {
                $obj = IPS_GetObject($childID);
                if ($obj['ObjectIdent'] !== '') {
                    $identMap[strtolower($obj['ObjectIdent'])] = $childID;
                }
            }
        }

        switch ($sourceType) {
            case self::PROVIDER_METNORWAY:
                return $this->AutoConfigureMETNorway($dayCount);
            case self::PROVIDER_WUNDERGROUND:
                return $this->AutoConfigureWunderground($identMap, $dayCount, $startDay);
            case self::PROVIDER_OPENWEATHER:
            default:
                return $this->AutoConfigureOpenWeather($identMap, $dayCount, $startDay);
        }
    }

    /**
     * Auto-Erkennung für OpenWeather OneCall
     * Ident-Format: {Prefix}{Suffix} z.B. DailyForecastTemperatureMin_D0
     */
    private function AutoConfigureOpenWeather(array $identMap, int $dayCount, int $startDay): string
    {
        $foundCount = 0;
        $missingList = [];
        $allFields = array_keys(self::OPENWEATHER_MAP);

        for ($i = 1; $i <= $dayCount; $i++) {
            $owmDay = $startDay + ($i - 1);

            $suffixes = [
                '_' . str_pad((string) $owmDay, 2, '0', STR_PAD_LEFT),
                '_D' . $owmDay,
                '_' . $owmDay,
                '_Day' . $owmDay,
            ];

            foreach ($allFields as $field) {
                $propName = "Day{$i}_{$field}";
                $varID = false;

                foreach (self::OPENWEATHER_MAP[$field] as $pattern) {
                    foreach ($suffixes as $suffix) {
                        $search = strtolower($pattern . $suffix);
                        if (isset($identMap[$search])) {
                            $varID = $identMap[$search];
                            break 2;
                        }
                    }
                }

                if ($varID !== false) {
                    IPS_SetProperty($this->InstanceID, $propName, $varID);
                    $foundCount++;
                } else {
                    $missingList[] = "Tag {$i}: {$field}";
                }
            }
        }

        IPS_ApplyChanges($this->InstanceID);

        $total = $dayCount * count($allFields);
        $msg = "{$foundCount} von {$total} Variablen automatisch zugewiesen (OpenWeather).";
        if (!empty($missingList)) {
            $msg .= "\nNicht gefunden:\n- " . implode("\n- ", $missingList);
        }
        if ($foundCount < $total) {
            $msg .= "\n\nTipp: Mit 'Idents anzeigen' die verfügbaren Idents prüfen und ggf. manuell zuweisen.";
        }

        $this->SendDebug('AutoConfigure', $msg, 0);
        return $msg;
    }

    /**
     * Auto-Erkennung für WeatherUnderground
     * Ident-Format: D{N}{FieldName} z.B. D1TemperatureMax, D2QPF
     * Besonderheiten: Kein Begin-Timestamp, kein RainPct, kein WindSpeed in Vorhersage
     */
    private function AutoConfigureWunderground(array $identMap, int $dayCount, int $startDay): string
    {
        $foundCount = 0;
        $missingList = [];
        $notes = [];

        // Wunderground hat max 5 Vorhersage-Tage (D1-D5)
        $maxWuDays = 5;
        if ($dayCount > $maxWuDays) {
            $notes[] = "Wunderground bietet max. {$maxWuDays} Vorhersage-Tage. Tage {$maxWuDays}+ werden nicht zugewiesen.";
        }

        for ($i = 1; $i <= $dayCount; $i++) {
            // Wunderground D-Nummern: D1=Tag 1, D2=Tag 2, ...
            $wuDay = $i;
            if ($wuDay > $maxWuDays) {
                $missingList[] = "Tag {$i}: Keine Daten (D{$wuDay} > D{$maxWuDays})";
                continue;
            }

            foreach (self::WUNDERGROUND_MAP as $field => $wuIdents) {
                $propName = "Day{$i}_{$field}";
                $varID = false;

                foreach ($wuIdents as $wuIdent) {
                    $search = strtolower('D' . $wuDay . $wuIdent);
                    if (isset($identMap[$search])) {
                        $varID = $identMap[$search];
                        break;
                    }
                }

                if ($varID !== false) {
                    IPS_SetProperty($this->InstanceID, $propName, $varID);
                    $foundCount++;
                } else {
                    $missingList[] = "Tag {$i}: {$field}";
                }
            }
        }

        IPS_ApplyChanges($this->InstanceID);

        $totalFields = count(self::WUNDERGROUND_MAP);
        $total = min($dayCount, $maxWuDays) * $totalFields;
        $msg = "{$foundCount} von {$total} Variablen automatisch zugewiesen (Wunderground).";

        $msg .= "\n\nHinweise:";
        $msg .= "\n- Begin (Timestamp): Nicht nötig bei Wunderground – wird automatisch berechnet.";
        $msg .= "\n- Regen-%: Nicht verfügbar bei Wunderground.";
        $msg .= "\n- Wind: Nicht als Vorhersage verfügbar bei Wunderground.";
        $msg .= "\n- Icon: Wunderground liefert Text-Vorhersagen, keine OWM-Icon-Codes.";

        if (!empty($notes)) {
            $msg .= "\n\n" . implode("\n", $notes);
        }
        if (!empty($missingList)) {
            $msg .= "\n\nNicht gefunden:\n- " . implode("\n- ", $missingList);
        }

        $this->SendDebug('AutoConfigure', $msg, 0);
        return $msg;
    }

    /**
     * Auto-Erkennung für MET Norway
     * Erstellt eigene Kind-Variablen und füllt sie mit API-Daten
     */
    private function AutoConfigureMETNorway(int $dayCount): string
    {
        $lat = $this->ReadPropertyString('METLatitude');
        $lon = $this->ReadPropertyString('METLongitude');
        $alt = $this->ReadPropertyInteger('METAltitude');

        if ($lat === '' || $lon === '') {
            return 'Bitte Breitengrad und Längengrad eingeben.';
        }

        // Kind-Variablen erstellen (idempotent via RegisterVariable)
        $fields = [
            'Begin'     => ['Int',    'Beginn',       0],
            'TempMin'   => ['Float',  'Temp Min °C',  0],
            'TempMax'   => ['Float',  'Temp Max °C',  0],
            'Icon'      => ['String', 'Icon-Code',    0],
            'RainMM'    => ['Float',  'Regen mm',     0],
            'RainPct'   => ['Float',  'Regen %',      0],
            'WindSpeed' => ['Float',  'Wind km/h',    0],
        ];

        $assignCount = 0;
        for ($i = 1; $i <= $dayCount; $i++) {
            foreach ($fields as $field => [$type, $label, $profile]) {
                $ident = "MET_D{$i}_{$field}";
                $name = "Tag {$i} {$label}";

                switch ($type) {
                    case 'Int':
                        $this->RegisterVariableInteger($ident, $name, '', 100 + ($i * 10));
                        break;
                    case 'Float':
                        $this->RegisterVariableFloat($ident, $name, '', 100 + ($i * 10));
                        break;
                    case 'String':
                        $this->RegisterVariableString($ident, $name, '', 100 + ($i * 10));
                        break;
                }

                $varID = $this->GetIDForIdent($ident);
                IPS_SetProperty($this->InstanceID, "Day{$i}_{$field}", $varID);
                $assignCount++;
            }
        }

        IPS_ApplyChanges($this->InstanceID);

        // Initiale Daten abrufen
        $fetchResult = $this->FetchMETNorway();

        $total = $dayCount * count($fields);
        $msg = "{$assignCount} von {$total} Variablen erstellt und zugewiesen (MET Norway).";
        $msg .= "\n\nStandort: Lat={$lat}, Lon={$lon}, Alt={$alt}m";
        $msg .= "\nAPI: api.met.no/weatherapi/locationforecast/2.0/complete";
        $msg .= "\n\nDatenabruf: {$fetchResult}";
        $msg .= "\n\nHinweis: Daten werden bei jedem Widget-Update automatisch von MET Norway abgerufen.";

        $this->SendDebug('AutoConfigure', $msg, 0);
        return $msg;
    }

    /**
     * MET Norway API abrufen und Kind-Variablen füllen
     */
    public function FetchMETNorway(): string
    {
        $lat = $this->ReadPropertyString('METLatitude');
        $lon = $this->ReadPropertyString('METLongitude');
        $alt = $this->ReadPropertyInteger('METAltitude');
        $dayCount = $this->ReadPropertyInteger('DayCount');

        if ($lat === '' || $lon === '') {
            return 'Keine MET Norway Koordinaten konfiguriert.';
        }

        // API-URL zusammenbauen (complete-Endpoint für Temp-Min/Max und Rain-%)
        $url = "https://api.met.no/weatherapi/locationforecast/2.0/complete"
             . "?lat={$lat}&lon={$lon}&altitude={$alt}";

        // User-Agent ist PFLICHT bei MET Norway, sonst 403!
        $opts = [
            'http' => [
                'header'  => "User-Agent: IPSymcon-WeatherWidget/1.0 github.com/IPSWeatherWidget\r\n",
                'timeout' => 15,
            ],
        ];
        $ctx = stream_context_create($opts);

        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            $this->SendDebug('MET Norway', 'API-Abruf fehlgeschlagen', 0);
            return 'API-Abruf fehlgeschlagen. Prüfe Koordinaten und Internetverbindung.';
        }

        $data = json_decode($json, true);
        if (!isset($data['properties']['timeseries'])) {
            $this->SendDebug('MET Norway', 'Ungültige API-Antwort', 0);
            return 'Ungültige API-Antwort.';
        }

        $timeseries = $data['properties']['timeseries'];

        // Timeseries nach Tagen gruppieren (lokale Zeitzone)
        $dailyData = [];
        foreach ($timeseries as $entry) {
            $utcTime = strtotime($entry['time']);
            $dayKey = date('Y-m-d', $utcTime);
            $hour = (int) date('H', $utcTime);

            if (!isset($dailyData[$dayKey])) {
                $dailyData[$dayKey] = [
                    'date'        => $dayKey,
                    'begin'       => strtotime($dayKey . ' 00:00:00'),
                    'temps'       => [],
                    'tempMin'     => PHP_FLOAT_MAX,
                    'tempMax'     => PHP_FLOAT_MIN,
                    'rainTotal'   => 0.0,
                    'rainPct'     => 0.0,
                    'hasProbData' => false,  // Ob API Wahrscheinlichkeitsdaten liefert
                    'rainHours'   => 0,      // Stunden mit Niederschlag > 0
                    'totalHours'  => 0,      // Gesamte Stunden mit Daten
                    'windMax'     => 0.0,
                    'icon'        => '',
                ];
            }

            $instant = $entry['data']['instant']['details'] ?? [];

            // Temperatur sammeln
            if (isset($instant['air_temperature'])) {
                $dailyData[$dayKey]['temps'][] = $instant['air_temperature'];
                $dailyData[$dayKey]['tempMin'] = min($dailyData[$dayKey]['tempMin'], $instant['air_temperature']);
                $dailyData[$dayKey]['tempMax'] = max($dailyData[$dayKey]['tempMax'], $instant['air_temperature']);
            }

            // Temp Min/Max aus next_6_hours bevorzugen (genauer)
            if (isset($entry['data']['next_6_hours']['details'])) {
                $n6 = $entry['data']['next_6_hours']['details'];
                if (isset($n6['air_temperature_min'])) {
                    $dailyData[$dayKey]['tempMin'] = min($dailyData[$dayKey]['tempMin'], $n6['air_temperature_min']);
                }
                if (isset($n6['air_temperature_max'])) {
                    $dailyData[$dayKey]['tempMax'] = max($dailyData[$dayKey]['tempMax'], $n6['air_temperature_max']);
                }
            }

            // Niederschlag: aus next_1_hours summieren, Fallback auf next_6_hours
            // MET Norway liefert next_1_hours nur für ~2-3 Tage, danach nur next_6_hours
            if (isset($entry['data']['next_1_hours']['details']['precipitation_amount'])) {
                $precip = $entry['data']['next_1_hours']['details']['precipitation_amount'];
                $dailyData[$dayKey]['rainTotal'] += $precip;
                $dailyData[$dayKey]['totalHours']++;
                if ($precip > 0) {
                    $dailyData[$dayKey]['rainHours']++;
                }
            } elseif (isset($entry['data']['next_6_hours']['details']['precipitation_amount'])) {
                $precip = $entry['data']['next_6_hours']['details']['precipitation_amount'];
                $dailyData[$dayKey]['rainTotal'] += $precip;
                $dailyData[$dayKey]['totalHours'] += 6;
                if ($precip > 0) {
                    $dailyData[$dayKey]['rainHours'] += 6;
                }
            }

            // Regenwahrscheinlichkeit: Maximum des Tages (nur in nordischer Region verfügbar)
            if (isset($entry['data']['next_1_hours']['details']['probability_of_precipitation'])) {
                $dailyData[$dayKey]['hasProbData'] = true;
                $dailyData[$dayKey]['rainPct'] = max(
                    $dailyData[$dayKey]['rainPct'],
                    $entry['data']['next_1_hours']['details']['probability_of_precipitation']
                );
            }
            if (isset($entry['data']['next_6_hours']['details']['probability_of_precipitation'])) {
                $dailyData[$dayKey]['hasProbData'] = true;
                $dailyData[$dayKey]['rainPct'] = max(
                    $dailyData[$dayKey]['rainPct'],
                    $entry['data']['next_6_hours']['details']['probability_of_precipitation']
                );
            }

            // Wind: Maximum (m/s → km/h)
            if (isset($instant['wind_speed'])) {
                $windKmh = $instant['wind_speed'] * 3.6;
                $dailyData[$dayKey]['windMax'] = max($dailyData[$dayKey]['windMax'], $windKmh);
            }

            // Icon: Symbol von ~12:00 Uhr bevorzugen (repräsentativste Tageszeit)
            if ($hour >= 11 && $hour <= 13 && $dailyData[$dayKey]['icon'] === '') {
                $symbol = $entry['data']['next_6_hours']['summary']['symbol_code']
                       ?? $entry['data']['next_1_hours']['summary']['symbol_code']
                       ?? '';
                if ($symbol !== '') {
                    $dailyData[$dayKey]['icon'] = $symbol;
                }
            }
        }

        // Fallback-Icon: Falls kein 12-Uhr-Symbol, erstes verfügbares nehmen
        foreach ($dailyData as $dayKey => &$day) {
            if ($day['icon'] === '') {
                // Nochmal durch Timeseries für diesen Tag
                foreach ($timeseries as $entry) {
                    $entryDay = date('Y-m-d', strtotime($entry['time']));
                    if ($entryDay !== $dayKey) {
                        continue;
                    }
                    $symbol = $entry['data']['next_6_hours']['summary']['symbol_code']
                           ?? $entry['data']['next_1_hours']['summary']['symbol_code']
                           ?? '';
                    if ($symbol !== '') {
                        $day['icon'] = $symbol;
                        break;
                    }
                }
            }
        }
        unset($day);

        // Regenwahrscheinlichkeit ableiten, wenn API keine Daten liefert (außerhalb Skandinavien)
        foreach ($dailyData as $dayKey => &$day) {
            if (!$day['hasProbData'] && $day['totalHours'] > 0) {
                // Anteil der Stunden mit Niederschlag als Wahrscheinlichkeit
                // z.B. 6 von 24 Stunden Regen → 25% Wahrscheinlichkeit
                $day['rainPct'] = round(($day['rainHours'] / $day['totalHours']) * 100, 0);
            }
        }
        unset($day);

        // Tage sortieren und in Kind-Variablen schreiben
        ksort($dailyData);
        $dayIndex = 0;
        $today = date('Y-m-d');

        foreach ($dailyData as $dayKey => $day) {
            // Nur ab heute zählen
            if ($dayKey < $today) {
                continue;
            }

            $dayIndex++;
            if ($dayIndex > $dayCount) {
                break;
            }

            // Werte in registrierte Variablen schreiben
            $this->SetValueIfExists("MET_D{$dayIndex}_Begin", $day['begin']);
            $this->SetValueIfExists("MET_D{$dayIndex}_TempMin", round($day['tempMin'], 1));
            $this->SetValueIfExists("MET_D{$dayIndex}_TempMax", round($day['tempMax'], 1));
            $this->SetValueIfExists("MET_D{$dayIndex}_Icon", $day['icon']);
            $this->SetValueIfExists("MET_D{$dayIndex}_RainMM", round($day['rainTotal'], 1));
            $this->SetValueIfExists("MET_D{$dayIndex}_RainPct", round($day['rainPct'], 1));
            $this->SetValueIfExists("MET_D{$dayIndex}_WindSpeed", round($day['windMax'], 1));
        }

        $this->SendDebug('MET Norway', "API-Daten abgerufen: {$dayIndex} Tage verarbeitet", 0);
        return "{$dayIndex} Tage erfolgreich abgerufen.";
    }

    /**
     * Hilfsfunktion: Wert in Variable schreiben, wenn Ident existiert
     */
    private function SetValueIfExists(string $ident, $value): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id !== false && $id > 0) {
            $this->SetValue($ident, $value);
        }
    }

    /**
     * MET Norway Symbol-Code → Bas Milius Icon-Name
     * Erkennt Codes wie "clearsky_day", "rain", "partlycloudy_night" etc.
     */
    private function MapMETNorwayIcon(string $symbolCode): ?string
    {
        // Suffix abtrennen (_day, _night, _polartwilight)
        $isNight = str_contains($symbolCode, '_night');
        $dn = $isNight ? 'night' : 'day';
        $base = preg_replace('/_(?:day|night|polartwilight)$/', '', $symbolCode);

        // Klar / Bewölkung
        if ($base === 'clearsky') {
            return "clear-{$dn}";
        }
        if ($base === 'fair' || $base === 'partlycloudy') {
            return "partly-cloudy-{$dn}";
        }
        if ($base === 'cloudy') {
            return 'cloudy';
        }
        if ($base === 'fog') {
            return 'mist';
        }

        // Gewitter-Varianten
        if (str_contains($base, 'thunder')) {
            $precip = (str_contains($base, 'snow') || str_contains($base, 'sleet')) ? 'snow' : 'rain';
            return "thunderstorms-{$dn}-{$precip}";
        }

        // Schnee
        if (str_contains($base, 'snow')) {
            return "overcast-{$dn}-snow";
        }

        // Schneeregen
        if (str_contains($base, 'sleet')) {
            return "overcast-{$dn}-sleet";
        }

        // Leichter Regen / Niesel
        if (str_contains($base, 'light') && str_contains($base, 'rain')) {
            return "overcast-{$dn}-drizzle";
        }

        // Regen
        if (str_contains($base, 'rain')) {
            return "overcast-{$dn}-rain";
        }

        // Unbekannt
        return null;
    }

    /**
     * Daten eines einzelnen Tages auslesen
     */
    private function ReadDayData(int $dayNum): ?array
    {
        $beginID = $this->ReadPropertyInteger("Day{$dayNum}_Begin");
        $tempMinID = $this->ReadPropertyInteger("Day{$dayNum}_TempMin");
        $tempMaxID = $this->ReadPropertyInteger("Day{$dayNum}_TempMax");
        $iconID = $this->ReadPropertyInteger("Day{$dayNum}_Icon");

        // Mindestens TempMin + TempMax müssen konfiguriert sein
        if ($tempMinID === 0 || $tempMaxID === 0) {
            return null;
        }

        // Variablen prüfen
        if (!IPS_VariableExists($tempMinID) || !IPS_VariableExists($tempMaxID)) {
            $this->SendDebug("Tag {$dayNum}", 'TempMin oder TempMax Variable existiert nicht', 0);
            return null;
        }

        // Begin: aus Variable lesen oder automatisch berechnen
        if ($beginID > 0 && IPS_VariableExists($beginID)) {
            $begin = (int) GetValue($beginID);
        } else {
            $startDay = $this->ReadPropertyInteger('StartDay');
            $begin = (int) strtotime('today') + ($startDay + $dayNum - 1) * 86400;
        }

        // Icon: aus Variable lesen oder leer lassen
        $icon = '';
        if ($iconID > 0 && IPS_VariableExists($iconID)) {
            $icon = (string) GetValue($iconID);
        }

        $data = [
            'begin'     => $begin,
            'tMin'      => (float) GetValue($tempMinID),
            'tMax'      => (float) GetValue($tempMaxID),
            'icon'      => $icon,
            'rainMM'    => 0.0,
            'rainPct'   => 0.0,
            'windSpeed' => 0.0,
        ];

        // Optionale Felder
        $rainMM_ID = $this->ReadPropertyInteger("Day{$dayNum}_RainMM");
        if ($rainMM_ID > 0 && IPS_VariableExists($rainMM_ID)) {
            $data['rainMM'] = (float) GetValue($rainMM_ID);
        }

        $rainPct_ID = $this->ReadPropertyInteger("Day{$dayNum}_RainPct");
        if ($rainPct_ID > 0 && IPS_VariableExists($rainPct_ID)) {
            $data['rainPct'] = (float) GetValue($rainPct_ID);
        }

        $windSpeed_ID = $this->ReadPropertyInteger("Day{$dayNum}_WindSpeed");
        if ($windSpeed_ID > 0 && IPS_VariableExists($windSpeed_ID)) {
            $data['windSpeed'] = (float) GetValue($windSpeed_ID);
        }

        return $data;
    }

    /**
     * Icon-URL aus OWM-Code oder Vorhersage-Text generieren
     */
    private function GetIconUrl(string $iconValue): string
    {
        $style = $this->ReadPropertyString('IconStyle');

        // === OWM Standard-Icons oder Benutzerdefiniert ===
        // Beide nutzen OWM-Codes als Dateinamen (01d, 10n, etc.)
        if ($style === 'owm' || $style === 'custom') {
            // OWM-Code ermitteln (falls nicht schon einer)
            if (preg_match('/^\d{2}[dn]$/', $iconValue)) {
                $owmCode = $iconValue;
            } else {
                $owmCode = $this->MapToOWMCode($iconValue);
            }

            if ($style === 'owm') {
                return "https://openweathermap.org/img/wn/{$owmCode}@2x.png";
            }

            // Custom: Base-URL + OWM-Code + .svg
            $base = rtrim(trim($this->ReadPropertyString('CustomIconBaseURL')), '/');
            return $base . '/' . $owmCode . '.svg';
        }

        // === Bas Milius Icon-Sets (Fill / Line) ===
        $base = self::ICON_BASES[$style] ?? self::ICON_BASES['basmilius_fill'];

        // 1. Exakter OWM-Code (z.B. "01d", "10n")
        if (isset(self::ICON_MAP[$iconValue])) {
            return $base . '/' . self::ICON_MAP[$iconValue] . '.svg';
        }

        // 2. MET Norway Symbol-Code (z.B. "clearsky_day", "rain", "partlycloudy_night")
        $metIcon = $this->MapMETNorwayIcon($iconValue);
        if ($metIcon !== null) {
            return $base . '/' . $metIcon . '.svg';
        }

        // 3. Text-Vorhersage (z.B. "Bewölkt", "Partly Cloudy") → Keyword-Matching
        $lower = mb_strtolower($iconValue, 'UTF-8');
        foreach (self::FORECAST_TEXT_MAP as $keyword => $iconName) {
            if (mb_strpos($lower, $keyword) !== false) {
                return $base . '/' . $iconName . '.svg';
            }
        }

        // 4. Fallback: OWM-URL (falls doch ein unbekannter Code)
        if (preg_match('/^\d{2}[dn]$/', $iconValue)) {
            return "https://openweathermap.org/img/wn/{$iconValue}@2x.png";
        }

        // 5. Unbekannter Text → generisches Wolken-Icon
        return $base . '/not-available.svg';
    }

    /**
     * Hilfsfunktion: Beliebigen Icon-Wert auf OWM-Code mappen (für OWM Standard-Icons)
     */
    private function MapToOWMCode(string $iconValue): string
    {
        // MET Norway Symbol-Codes → OWM-Code
        $metToOwm = [
            'clearsky'       => '01d', 'fair'             => '02d',
            'partlycloudy'   => '03d', 'cloudy'           => '04d',
            'fog'            => '50d', 'lightrainshowers' => '09d',
            'rainshowers'    => '10d', 'heavyrainshowers' => '10d',
            'rain'           => '10d', 'lightrain'        => '09d',
            'heavyrain'      => '10d', 'drizzle'          => '09d',
            'sleet'          => '13d', 'snow'             => '13d',
            'lightsnow'      => '13d', 'heavysnow'        => '13d',
            'snowshowers'    => '13d', 'thunder'           => '11d',
            'rainandthunder' => '11d', 'snowandthunder'   => '11d',
        ];

        // Tag/Nacht-Suffix entfernen
        $base = preg_replace('/_(day|night|polartwilight)$/', '', $iconValue);
        $isDaylight = !str_contains($iconValue, '_night');
        $suffix = $isDaylight ? 'd' : 'n';

        if (isset($metToOwm[$base])) {
            $code = $metToOwm[$base];
            return substr($code, 0, 2) . $suffix;
        }

        // Text-basiertes Matching
        $lower = mb_strtolower($iconValue, 'UTF-8');
        $textToOwm = [
            'gewitter' => '11d', 'thunder' => '11d',
            'schnee'   => '13d', 'snow'    => '13d',
            'regen'    => '10d', 'rain'    => '10d',
            'schauer'  => '09d', 'drizzle' => '09d',
            'nebel'    => '50d', 'fog'     => '50d', 'mist' => '50d',
            'wolkig'   => '04d', 'cloudy'  => '04d', 'overcast' => '04d',
            'bewölkt'  => '03d', 'partly'  => '02d',
            'sonnig'   => '01d', 'clear'   => '01d', 'sunny' => '01d',
        ];

        foreach ($textToOwm as $keyword => $code) {
            if (mb_strpos($lower, $keyword) !== false) {
                return $code;
            }
        }

        return '03d'; // Fallback: bewölkt
    }

    /**
     * Prüft ob ein Unix-Timestamp heute ist
     */
    private function IsToday(int $timestamp): bool
    {
        return date('Y-m-d', $timestamp) === date('Y-m-d');
    }

    /**
     * Hex-Integer zu CSS-Farbcode
     */
    private function IntToHex(int $color): string
    {
        return '#' . str_pad(dechex($color), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Farb-Integer + Deckkraft (0-100) zu CSS rgba() String
     * Opacity 0 = transparent, 100 = voll deckend
     */
    private function IntToRgba(int $color, int $opacity): string
    {
        if ($opacity <= 0) {
            return 'transparent';
        }
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;
        $a = round($opacity / 100, 2);
        return "rgba({$r},{$g},{$b},{$a})";
    }

    /**
     * Zeilen-Reihenfolge aus Properties lesen
     */
    private function GetRowOrder(): array
    {
        $order = [];
        for ($i = 1; $i <= 5; $i++) {
            $row = $this->ReadPropertyString("RowPos{$i}");
            if ($row !== '' && !in_array($row, $order, true)) {
                $order[] = $row;
            }
        }
        // Falls Einträge fehlen, Defaults anhängen
        foreach (['temp', 'rain', 'wind', 'icons', 'days'] as $default) {
            if (!in_array($default, $order, true)) {
                $order[] = $default;
            }
        }
        return $order;
    }

    /**
     * Komplettes HTML generieren
     */
    /**
     * Kompakter Icon-Header: Nur Wochentag + Icon + Temperatur
     * Ideal für schmale Dashboard-Leisten oder als Übersichtszeile
     */
    private function GenerateIconHeaderHTML(array $days): string
    {
        $iconSize = $this->ReadPropertyInteger('IconHeaderIconSize');
        $cToday = $this->IntToHex($this->ReadPropertyInteger('ColorToday'));
        $cDayLabel = $this->IntToHex($this->ReadPropertyInteger('ColorDayLabel'));
        $cTempMax = $this->IntToHex($this->ReadPropertyInteger('ColorTempMax'));
        $cTempMin = $this->IntToHex($this->ReadPropertyInteger('ColorTempMin'));
        $showDay = $this->ReadPropertyBoolean('IconHeaderShowDay');
        $showTemp = $this->ReadPropertyBoolean('IconHeaderShowTemp');
        $bgColorInt = $this->ReadPropertyInteger('IconHeaderBgColor');
        $bgOpacity = $this->ReadPropertyInteger('IconHeaderBgOpacity');
        $bgCss = ($bgColorInt === -1 || $bgOpacity <= 0) ? 'transparent' : $this->IntToRgba($bgColorInt, $bgOpacity);
        $cols = count($days);

        $css = <<<CSS
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{background:transparent;--s:1}
body{font-family:'Inter',sans-serif;background:transparent;color:#e6edf3;width:100%;height:100%;display:flex;overflow:hidden}
.icon-header{display:grid;grid-template-columns:repeat({$cols},1fr);width:100%;padding:clamp(calc(4px*var(--s)),1vh,calc(10px*var(--s))) clamp(calc(4px*var(--s)),1vw,calc(12px*var(--s)));gap:clamp(calc(2px*var(--s)),0.5vw,calc(6px*var(--s)));background:{$bgCss};border-radius:clamp(calc(4px*var(--s)),1vmin,calc(10px*var(--s)))}
.ih-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(calc(1px*var(--s)),0.3vh,calc(4px*var(--s)))}
.ih-day{font-size:clamp(calc(9px*var(--s)),min(1.6vw,2vh),calc(13px*var(--s)));font-weight:500;color:{$cDayLabel};line-height:1}
.ih-day.today{color:{$cToday};font-weight:700}
.ih-icon{width:clamp(calc(24px*var(--s)),min(calc({$iconSize}px*var(--s)),8vw),calc({$iconSize}px*var(--s)));height:clamp(calc(24px*var(--s)),min(calc({$iconSize}px*var(--s)),8vw),calc({$iconSize}px*var(--s)))}
.ih-temp{display:flex;gap:clamp(calc(2px*var(--s)),0.4vw,calc(6px*var(--s)));align-items:baseline;line-height:1}
.ih-tmax{font-size:clamp(calc(10px*var(--s)),min(2vw,2.4vh),calc(16px*var(--s)));font-weight:600;color:{$cTempMax}}
.ih-tmin{font-size:clamp(calc(8px*var(--s)),min(1.6vw,2vh),calc(13px*var(--s)));font-weight:400;color:{$cTempMin}}
.ih-cell.today .ih-tmax{color:{$cToday}}
CSS;

        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1.0">';
        $html .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
        $html .= '<style>' . $css . '</style>';
        $html .= '</head><body>';
        $html .= '<div class="icon-header">';

        foreach ($days as $day) {
            $today = $this->IsToday($day['begin']);
            $wd = self::WEEKDAYS[(int) date('w', $day['begin'])];
            $cls = $today ? ' today' : '';

            $html .= "<div class=\"ih-cell{$cls}\">";

            if ($showDay) {
                $html .= "<div class=\"ih-day{$cls}\">{$wd}</div>";
            }

            if ($day['icon'] !== '') {
                $url = $this->GetIconUrl($day['icon']);
                $html .= "<img class=\"ih-icon\" src=\"{$url}\" alt=\"{$wd}\" loading=\"lazy\">";
            }

            if ($showTemp) {
                $html .= '<div class="ih-temp">';
                $html .= '<span class="ih-tmax">' . round($day['tMax']) . '°</span>';
                $html .= '<span class="ih-tmin">' . round($day['tMin']) . '°</span>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<script>(function(){function s(){var w=document.documentElement.clientWidth;document.documentElement.style.setProperty("--s",w/800)}s();window.addEventListener("resize",s)})()</script>';
        $html .= '</body></html>';
        return $html;
    }

    private function GenerateHTML(array $days, bool $showRain, bool $showWind): string
    {
        $dayCount = count($days);
        $rainBarH = $this->ReadPropertyInteger('RainBarHeight');
        $rainBarW = $this->ReadPropertyInteger('RainBarWidth');
        $windBarH = $this->ReadPropertyInteger('WindBarHeight');
        $windBarW = $this->ReadPropertyInteger('WindBarWidth');
        $iconSize = $this->ReadPropertyInteger('IconSize');

        // Farben lesen
        $cTempMax       = $this->IntToHex($this->ReadPropertyInteger('ColorTempMax'));
        $cTempMin       = $this->IntToHex($this->ReadPropertyInteger('ColorTempMin'));
        $cToday         = $this->IntToHex($this->ReadPropertyInteger('ColorToday'));
        $cRainLabel     = $this->IntToHex($this->ReadPropertyInteger('ColorRainLabel'));
        $cRainLabelZero = $this->IntToHex($this->ReadPropertyInteger('ColorRainLabelZero'));
        $cRainChance    = $this->IntToHex($this->ReadPropertyInteger('ColorRainChance'));
        $cRainBar       = $this->IntToHex($this->ReadPropertyInteger('ColorRainBar'));
        $cWindLabel     = $this->IntToHex($this->ReadPropertyInteger('ColorWindLabel'));
        $cWindBar       = $this->IntToHex($this->ReadPropertyInteger('ColorWindBar'));
        $cDayLabel      = $this->IntToHex($this->ReadPropertyInteger('ColorDayLabel'));
        $cTempBar       = $this->IntToHex($this->ReadPropertyInteger('ColorTempBar'));

        // Globale Min/Max berechnen
        $globalMin = PHP_FLOAT_MAX;
        $globalMax = PHP_FLOAT_MIN;
        $maxRain = 0.1;
        $maxWind = 1.0;

        foreach ($days as $day) {
            $globalMin = min($globalMin, $day['tMin']);
            $globalMax = max($globalMax, $day['tMax']);
            $maxRain = max($maxRain, $day['rainMM']);
            $maxWind = max($maxWind, $day['windSpeed']);
        }
        $globalRange = $globalMax - $globalMin ?: 1;

        $updateTime = date('d.m. H:i');

        // Hintergrundfarbe + Deckkraft
        $bgColorInt = $this->ReadPropertyInteger('WidgetBgColor');
        $bgOpacity = $this->ReadPropertyInteger('WidgetBgOpacity');
        $widgetBg = ($bgColorInt === -1 || $bgOpacity <= 0) ? 'transparent' : $this->IntToRgba($bgColorInt, $bgOpacity);

        // CSS bauen
        $css = $this->BuildCSS($dayCount, $rainBarH, $rainBarW, $windBarH, $windBarW, $iconSize, $cTempMax, $cTempMin, $cToday, $cRainLabel, $cRainLabelZero, $cRainChance, $cRainBar, $cWindLabel, $cWindBar, $cDayLabel, $cTempBar, $widgetBg);

        // HTML zusammenbauen
        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1.0">';
        $html .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
        $html .= '<style>' . $css . '</style>';
        $html .= '</head><body>';

        $html .= "<div class=\"header\"><span class=\"last-update\">{$updateTime}</span></div>";
        $html .= '<div class="widget"><div class="weather-grid">';

        // === Zeilen in konfigurierter Reihenfolge rendern ===
        $rowOrder = $this->GetRowOrder();
        foreach ($rowOrder as $row) {
            switch ($row) {
                case 'temp':
                    $html .= $this->RenderTempBars($days, $globalMax, $globalRange);
                    break;
                case 'rain':
                    if ($showRain) {
                        $html .= $this->RenderRainRow($days, $maxRain);
                    }
                    break;
                case 'wind':
                    if ($showWind) {
                        $html .= $this->RenderWindRow($days, $maxWind);
                    }
                    break;
                case 'icons':
                    $html .= $this->RenderIconRow($days);
                    break;
                case 'days':
                    $html .= $this->RenderDayRow($days);
                    break;
            }
        }

        $html .= '</div></div>';
        $html .= '<script>(function(){function s(){var w=document.documentElement.clientWidth,h=document.documentElement.clientHeight;document.documentElement.style.setProperty("--s",Math.min(w/800,h/480))}s();window.addEventListener("resize",s)})()</script>';
        $html .= '</body></html>';
        return $html;
    }

    /**
     * Temperaturbalken rendern
     */
    private function RenderTempBars(array $days, float $globalMax, float $globalRange): string
    {
        $html = '<div class="bars-area">';
        foreach ($days as $day) {
            $today = $this->IsToday($day['begin']);
            $topPct = round(($globalMax - $day['tMax']) / $globalRange * 100, 2);
            $heightPct = round(($day['tMax'] - $day['tMin']) / $globalRange * 100, 2);
            $cls = $today ? ' today' : '';

            $html .= "<div class=\"bar-col{$cls}\">";
            $html .= "<div class=\"bar-unit\" style=\"top:{$topPct}%;height:{$heightPct}%\">";
            $html .= '<div class="temp-label-max">' . round($day['tMax']) . '°</div>';
            $html .= '<div class="bar-track"><div class="bar-fill"></div></div>';
            $html .= '<div class="temp-label-min">' . round($day['tMin']) . '°</div>';
            $html .= '</div></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Regen-Zeile rendern
     */
    private function RenderRainRow(array $days, float $maxRain): string
    {
        $html = '<div class="rain-row">';
        foreach ($days as $day) {
            $pct = min(100, round($day['rainMM'] / $maxRain * 100, 1));
            $zCls = $day['rainMM'] == 0 ? ' zero' : '';
            $html .= '<div class="rain-cell">';
            $html .= "<div class=\"rain-label{$zCls}\">" . round($day['rainMM'], 1) . 'mm</div>';
            $html .= '<div class="rain-chance">' . round($day['rainPct']) . '%</div>';
            $html .= "<div class=\"rain-bar-track\"><div class=\"rain-bar-fill\" style=\"height:{$pct}%\"></div></div>";
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Wind-Zeile rendern
     */
    private function RenderWindRow(array $days, float $maxWind): string
    {
        $html = '<div class="wind-row">';
        foreach ($days as $day) {
            $pct = min(100, round($day['windSpeed'] / $maxWind * 100, 1));
            $html .= '<div class="wind-cell">';
            $html .= '<div class="wind-label">' . round($day['windSpeed']) . ' km/h</div>';
            $html .= "<div class=\"wind-bar-track\"><div class=\"wind-bar-fill\" style=\"height:{$pct}%\"></div></div>";
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Icon-Zeile rendern
     */
    private function RenderIconRow(array $days): string
    {
        $html = '<div class="icon-row">';
        foreach ($days as $day) {
            if ($day['icon'] === '') {
                $html .= '<div class="icon-cell"></div>';
            } else {
                $url = $this->GetIconUrl($day['icon']);
                $html .= "<div class=\"icon-cell\"><img class=\"weather-icon\" src=\"{$url}\" alt=\"Wetter\" loading=\"lazy\"></div>";
            }
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Wochentag-Zeile rendern
     */
    private function RenderDayRow(array $days): string
    {
        $html = '<div class="day-row">';
        foreach ($days as $day) {
            $today = $this->IsToday($day['begin']);
            $wd = self::WEEKDAYS[(int) date('w', $day['begin'])];
            $cls = $today ? ' today' : '';
            $html .= "<div class=\"day-cell{$cls}\">{$wd}</div>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * CSS mit konfigurierbaren Dimensionen und Farben
     */
    private function BuildCSS(int $cols, int $rH, int $rW, int $wH, int $wW, int $iconPx, string $cTMax, string $cTMin, string $cToday, string $cRainL, string $cRainLZ, string $cRainC, string $cRainB, string $cWindL, string $cWindB, string $cDayL, string $cTempB, string $widgetBg = 'transparent'): string
    {
        $widgetBorder = ($widgetBg === 'transparent') ? 'none' : '1px solid rgba(255,255,255,0.06)';

        return <<<CSS
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{background:transparent;--s:1}
body{font-family:'Inter',sans-serif;background:transparent;color:#e6edf3;width:100%;height:100%;display:flex;flex-direction:column;overflow:hidden}
.header{display:flex;justify-content:flex-end;align-items:center;padding:clamp(calc(4px*var(--s)),1vh,calc(10px*var(--s))) clamp(calc(8px*var(--s)),2vw,calc(16px*var(--s)));flex-shrink:0}
.last-update{font-size:clamp(calc(8px*var(--s)),min(1.6vw,2vh),calc(12px*var(--s)));color:#8b949e;font-weight:400}
.widget{flex:1;display:flex;flex-direction:column;margin:0 clamp(calc(4px*var(--s)),1.5vw,calc(16px*var(--s))) clamp(calc(4px*var(--s)),1vh,calc(12px*var(--s)));background:{$widgetBg};border-radius:clamp(calc(8px*var(--s)),2vmin,calc(18px*var(--s)));border:{$widgetBorder};position:relative;padding:clamp(calc(6px*var(--s)),1.5vh,calc(16px*var(--s))) clamp(calc(4px*var(--s)),1vw,calc(12px*var(--s)));overflow:hidden}
.weather-grid{flex:1;display:flex;flex-direction:column;min-height:0}
.bars-area{flex:1;display:grid;grid-template-columns:repeat({$cols},1fr);position:relative;min-height:0}
.bar-col{display:flex;flex-direction:column;align-items:center;position:relative}
.bar-unit{position:absolute;display:flex;flex-direction:column;align-items:center;gap:clamp(calc(2px*var(--s)),0.4vh,calc(4px*var(--s)));width:100%;min-height:clamp(calc(40px*var(--s)),8vh,calc(60px*var(--s)))}
.temp-label-max{font-size:clamp(calc(10px*var(--s)),min(2.2vw,2.8vh),calc(18px*var(--s)));font-weight:600;color:{$cTMax};line-height:1;text-align:center}
.bar-col.today .temp-label-max{color:{$cToday}}
.bar-track{width:clamp(calc(5px*var(--s)),min(1.2vw,1.5vh),calc(10px*var(--s)));flex:1;min-height:calc(4px*var(--s));background:rgba(255,255,255,0.08);border-radius:100px;position:relative;overflow:hidden}
.bar-fill{position:absolute;inset:0;border-radius:100px;background:{$cTempB}}
.bar-col.today .bar-fill{background:{$cToday};box-shadow:0 0 calc(10px*var(--s)) {$cToday}66}
.temp-label-min{font-size:clamp(calc(10px*var(--s)),min(2.2vw,2.8vh),calc(18px*var(--s)));font-weight:600;color:{$cTMin};line-height:1;text-align:center}
.rain-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(calc(4px*var(--s)),0.8vh,calc(8px*var(--s))) 0 clamp(calc(2px*var(--s)),0.3vh,calc(4px*var(--s)));gap:clamp(calc(2px*var(--s)),0.5vw,calc(6px*var(--s)))}
.rain-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(calc(2px*var(--s)),0.3vh,calc(4px*var(--s)))}
.rain-label{font-size:clamp(calc(9px*var(--s)),min(1.8vw,2.2vh),calc(15px*var(--s)));font-weight:600;color:{$cRainL};line-height:1}
.rain-label.zero{color:{$cRainLZ}}
.rain-chance{font-size:clamp(calc(7px*var(--s)),min(1.4vw,1.8vh),calc(12px*var(--s)));font-weight:400;color:{$cRainC};line-height:1}
.rain-bar-track{width:{$rW}%;height:calc({$rH}px*var(--s));background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.rain-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:{$cRainB}}
.wind-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(calc(2px*var(--s)),0.3vh,calc(4px*var(--s))) 0;gap:clamp(calc(2px*var(--s)),0.5vw,calc(6px*var(--s)))}
.wind-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(calc(2px*var(--s)),0.3vh,calc(4px*var(--s)))}
.wind-label{font-size:clamp(calc(9px*var(--s)),min(1.8vw,2.2vh),calc(15px*var(--s)));font-weight:600;color:{$cWindL};line-height:1;white-space:nowrap}
.wind-bar-track{width:{$wW}%;height:calc({$wH}px*var(--s));background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.wind-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:{$cWindB}}
.icon-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(calc(2px*var(--s)),0.4vh,calc(4px*var(--s))) 0}
.icon-cell{display:flex;justify-content:center}
.weather-icon{width:calc({$iconPx}px*var(--s));height:calc({$iconPx}px*var(--s));object-fit:contain}
.day-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(calc(2px*var(--s)),0.3vh,calc(4px*var(--s))) 0 0}
.day-cell{text-align:center;font-size:clamp(calc(10px*var(--s)),min(2.2vw,2.8vh),calc(18px*var(--s)));font-weight:600;color:{$cDayL};text-transform:uppercase;letter-spacing:.5px}
.day-cell.today{color:{$cToday}}
CSS;
    }
}
