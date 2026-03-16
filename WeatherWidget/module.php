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

    private const ICON_BASE = 'https://cdn.jsdelivr.net/gh/basmilius/weather-icons@dev/production/fill/svg';
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
        $this->RegisterPropertyInteger('SourceType', 0); // 0=nicht gewählt, 1=OpenWeather, 2=Wunderground, 3=Froggit, 4=Netatmo
        $this->RegisterPropertyInteger('SourceInstance', 0);
        $this->RegisterPropertyInteger('StartDay', 0); // 0=D0 (heute), 1=D1 (morgen)

        // Allgemeine Einstellungen
        $this->RegisterPropertyInteger('DayCount', 6);
        $this->RegisterPropertyInteger('UpdateInterval', 5);
        $this->RegisterPropertyBoolean('ShowRain', true);
        $this->RegisterPropertyBoolean('ShowWind', true);

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

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // HTMLBox-Variable registrieren
        $this->RegisterVariableString('WeatherHTML', 'Weather Widget', '~HTMLBox', 0);

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

        $sourceID = $this->ReadPropertyInteger('SourceInstance');
        if ($sourceID === 0 || !IPS_ObjectExists($sourceID)) {
            return 'Bitte zuerst eine Wetter-Instanz auswählen und Konfiguration speichern.';
        }

        $dayCount = $this->ReadPropertyInteger('DayCount');
        $startDay = $this->ReadPropertyInteger('StartDay');

        // Alle Kind-Variablen mit Idents einlesen
        $childIDs = IPS_GetChildrenIDs($sourceID);
        $identMap = []; // ident (lowercase) → varID
        foreach ($childIDs as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectIdent'] !== '') {
                $identMap[strtolower($obj['ObjectIdent'])] = $childID;
            }
        }

        switch ($sourceType) {
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
        // 1. Exakter OWM-Code (z.B. "01d", "10n")
        if (isset(self::ICON_MAP[$iconValue])) {
            return self::ICON_BASE . '/' . self::ICON_MAP[$iconValue] . '.svg';
        }

        // 2. Text-Vorhersage (z.B. "Bewölkt", "Partly Cloudy") → Keyword-Matching
        $lower = mb_strtolower($iconValue, 'UTF-8');
        foreach (self::FORECAST_TEXT_MAP as $keyword => $iconName) {
            if (mb_strpos($lower, $keyword) !== false) {
                return self::ICON_BASE . '/' . $iconName . '.svg';
            }
        }

        // 3. Fallback: OWM-URL (falls doch ein unbekannter Code)
        if (preg_match('/^\d{2}[dn]$/', $iconValue)) {
            return "https://openweathermap.org/img/wn/{$iconValue}@2x.png";
        }

        // 4. Unbekannter Text → generisches Wolken-Icon
        return self::ICON_BASE . '/not-available.svg';
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

        // CSS bauen
        $css = $this->BuildCSS($dayCount, $rainBarH, $rainBarW, $windBarH, $windBarW, $iconSize, $cTempMax, $cTempMin, $cToday, $cRainLabel, $cRainLabelZero, $cRainChance, $cRainBar, $cWindLabel, $cWindBar, $cDayLabel, $cTempBar);

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

        $html .= '</div></div></body></html>';
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
    private function BuildCSS(int $cols, int $rH, int $rW, int $wH, int $wW, int $iconPx, string $cTMax, string $cTMin, string $cToday, string $cRainL, string $cRainLZ, string $cRainC, string $cRainB, string $cWindL, string $cWindB, string $cDayL, string $cTempB): string
    {
        return <<<CSS
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:transparent;color:#e6edf3;width:100%;height:100%;display:flex;flex-direction:column;overflow:hidden}
.header{display:flex;justify-content:flex-end;align-items:center;padding:clamp(4px,1vh,10px) clamp(8px,2vw,16px);flex-shrink:0}
.last-update{font-size:clamp(8px,min(1.6vw,2vh),12px);color:#8b949e;font-weight:400}
.widget{flex:1;display:flex;flex-direction:column;margin:0 clamp(4px,1.5vw,16px) clamp(4px,1vh,12px);background:linear-gradient(to bottom,rgba(86,86,86,0.5),rgba(54,54,54,0.5));border-radius:clamp(8px,2vmin,18px);border:1px solid rgba(255,255,255,0.06);position:relative;padding:clamp(6px,1.5vh,16px) clamp(4px,1vw,12px);overflow:hidden}
.weather-grid{flex:1;display:flex;flex-direction:column;min-height:0}
.bars-area{flex:1;display:grid;grid-template-columns:repeat({$cols},1fr);position:relative;min-height:0}
.bar-col{display:flex;flex-direction:column;align-items:center;position:relative}
.bar-unit{position:absolute;display:flex;flex-direction:column;align-items:center;gap:clamp(2px,0.4vh,4px);width:100%}
.temp-label-max{font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:{$cTMax};line-height:1;text-align:center}
.bar-col.today .temp-label-max{color:{$cToday}}
.bar-track{width:clamp(5px,min(1.2vw,1.5vh),10px);flex:1;min-height:4px;background:rgba(255,255,255,0.08);border-radius:100px;position:relative;overflow:hidden}
.bar-fill{position:absolute;inset:0;border-radius:100px;background:{$cTempB}}
.bar-col.today .bar-fill{background:{$cToday};box-shadow:0 0 10px {$cToday}66}
.temp-label-min{font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:{$cTMin};line-height:1;text-align:center}
.rain-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(4px,0.8vh,8px) 0 clamp(2px,0.3vh,4px);gap:clamp(2px,0.5vw,6px)}
.rain-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(2px,0.3vh,4px)}
.rain-label{font-size:clamp(9px,min(1.8vw,2.2vh),15px);font-weight:600;color:{$cRainL};line-height:1}
.rain-label.zero{color:{$cRainLZ}}
.rain-chance{font-size:clamp(7px,min(1.4vw,1.8vh),12px);font-weight:400;color:{$cRainC};line-height:1}
.rain-bar-track{width:{$rW}%;height:{$rH}px;background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.rain-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:{$cRainB}}
.wind-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.3vh,4px) 0;gap:clamp(2px,0.5vw,6px)}
.wind-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(2px,0.3vh,4px)}
.wind-label{font-size:clamp(9px,min(1.8vw,2.2vh),15px);font-weight:600;color:{$cWindL};line-height:1;white-space:nowrap}
.wind-bar-track{width:{$wW}%;height:{$wH}px;background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.wind-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:{$cWindB}}
.icon-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.4vh,4px) 0}
.icon-cell{display:flex;justify-content:center}
.weather-icon{width:{$iconPx}px;height:{$iconPx}px;object-fit:contain}
.day-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.3vh,4px) 0 0}
.day-cell{text-align:center;font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:{$cDayL};text-transform:uppercase;letter-spacing:.5px}
.day-cell.today{color:{$cToday}}
CSS;
    }
}
