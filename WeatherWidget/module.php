<?php

declare(strict_types=1);

/**
 * WeatherWidget – Wetter-Vorhersage-Widget für IP-Symcon
 *
 * Zeigt eine mehrtägige Wettervorhersage im WaWö-Stil an:
 * Temperaturbalken, Niederschlag, Wind, animierte Icons.
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

    // OpenWeatherOneCall Ident-Mapping: Widget-Feld → OWM-Ident-Prefix
    private const OWM_IDENT_MAP = [
        'Begin'     => 'Daily_Timestamp',
        'TempMin'   => 'Daily_TempMin',
        'TempMax'   => 'Daily_TempMax',
        'Icon'      => 'Daily_ConditionIcon',
        'RainMM'    => 'Daily_Rain',
        'RainPct'   => 'Daily_RainProbability',
        'WindSpeed'  => 'Daily_WindSpeed',
    ];

    public function Create(): void
    {
        parent::Create();

        // OpenWeather Quell-Instanz für Auto-Erkennung
        $this->RegisterPropertyInteger('SourceInstance', 0);
        $this->RegisterPropertyInteger('StartDay', 0); // 0=D0 (heute), 1=D1 (morgen)

        // Allgemeine Einstellungen
        $this->RegisterPropertyInteger('DayCount', 6);
        $this->RegisterPropertyInteger('UpdateInterval', 5);
        $this->RegisterPropertyBoolean('ShowRain', true);
        $this->RegisterPropertyBoolean('ShowWind', true);

        // Balken-Darstellung
        $this->RegisterPropertyInteger('RainBarHeight', 30);
        $this->RegisterPropertyInteger('RainBarWidth', 70);
        $this->RegisterPropertyInteger('WindBarHeight', 30);
        $this->RegisterPropertyInteger('WindBarWidth', 70);

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

        // Prüfen ob mindestens Tag 1 konfiguriert ist
        $day1Begin = $this->ReadPropertyInteger('Day1_Begin');
        if ($day1Begin === 0) {
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
     * Auto-Erkennung: Variablen aus OpenWeather-Instanz ermitteln
     * Aufrufbar via WTR_DetectVariables($InstanceID)
     */
    public function DetectVariables(): string
    {
        $sourceID = $this->ReadPropertyInteger('SourceInstance');
        if ($sourceID === 0 || !IPS_ObjectExists($sourceID)) {
            return json_encode(['error' => 'Bitte zuerst eine OpenWeather-Instanz auswählen und Konfiguration speichern.']);
        }

        $dayCount = $this->ReadPropertyInteger('DayCount');
        $startDay = $this->ReadPropertyInteger('StartDay');
        $found = [];
        $missing = [];

        for ($i = 1; $i <= $dayCount; $i++) {
            $owmDay = $startDay + ($i - 1); // D0, D1, D2, ... oder D1, D2, D3, ...

            foreach (self::OWM_IDENT_MAP as $field => $identPrefix) {
                $ident = "{$identPrefix}_D{$owmDay}";
                $propName = "Day{$i}_{$field}";

                $varID = @IPS_GetObjectIDByIdent($ident, $sourceID);
                if ($varID !== false) {
                    $found[$propName] = $varID;
                } else {
                    $missing[] = "Tag {$i}: {$field} (Ident: {$ident})";
                }
            }
        }

        // Ergebnis zurückgeben
        $result = [
            'found'   => $found,
            'missing' => $missing,
            'count'   => count($found),
            'total'   => $dayCount * count(self::OWM_IDENT_MAP),
        ];

        $this->SendDebug('DetectVariables', json_encode($result), 0);
        return json_encode($result);
    }

    /**
     * Auto-Erkennung durchführen und Variablen direkt setzen
     * Aufrufbar via WTR_AutoConfigure($InstanceID)
     */
    public function AutoConfigure(): string
    {
        $sourceID = $this->ReadPropertyInteger('SourceInstance');
        if ($sourceID === 0 || !IPS_ObjectExists($sourceID)) {
            return 'Bitte zuerst eine OpenWeather-Instanz auswählen und Konfiguration speichern.';
        }

        $dayCount = $this->ReadPropertyInteger('DayCount');
        $startDay = $this->ReadPropertyInteger('StartDay');
        $foundCount = 0;
        $missingList = [];

        for ($i = 1; $i <= $dayCount; $i++) {
            $owmDay = $startDay + ($i - 1);

            foreach (self::OWM_IDENT_MAP as $field => $identPrefix) {
                $ident = "{$identPrefix}_D{$owmDay}";
                $propName = "Day{$i}_{$field}";

                $varID = @IPS_GetObjectIDByIdent($ident, $sourceID);
                if ($varID !== false) {
                    IPS_SetProperty($this->InstanceID, $propName, $varID);
                    $foundCount++;
                } else {
                    $missingList[] = "Tag {$i}: {$field} ({$ident})";
                }
            }
        }

        // Änderungen anwenden
        IPS_ApplyChanges($this->InstanceID);

        $total = $dayCount * count(self::OWM_IDENT_MAP);
        $msg = "✅ {$foundCount} von {$total} Variablen automatisch zugewiesen.";
        if (!empty($missingList)) {
            $msg .= "\n⚠️ Nicht gefunden:\n• " . implode("\n• ", $missingList);
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

        // Mindestens Begin + TempMin + TempMax + Icon müssen konfiguriert sein
        if ($beginID === 0 || $tempMinID === 0 || $tempMaxID === 0 || $iconID === 0) {
            return null;
        }

        // Variablen prüfen
        if (!IPS_VariableExists($beginID) || !IPS_VariableExists($tempMinID)
            || !IPS_VariableExists($tempMaxID) || !IPS_VariableExists($iconID)) {
            $this->SendDebug("Tag {$dayNum}", 'Eine oder mehrere Variablen existieren nicht', 0);
            return null;
        }

        $data = [
            'begin'     => (int) GetValue($beginID),
            'tMin'      => (float) GetValue($tempMinID),
            'tMax'      => (float) GetValue($tempMaxID),
            'icon'      => (string) GetValue($iconID),
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
     * Icon-URL aus OWM-Code generieren
     */
    private function GetIconUrl(string $owmCode): string
    {
        if (isset(self::ICON_MAP[$owmCode])) {
            return self::ICON_BASE . '/' . self::ICON_MAP[$owmCode] . '.svg';
        }
        return "https://openweathermap.org/img/wn/{$owmCode}@2x.png";
    }

    /**
     * Prüft ob ein Unix-Timestamp heute ist
     */
    private function IsToday(int $timestamp): bool
    {
        return date('Y-m-d', $timestamp) === date('Y-m-d');
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

        // HTML zusammenbauen
        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1.0">';
        $html .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
        $html .= '<style>' . $this->BuildCSS($dayCount, $rainBarH, $rainBarW, $windBarH, $windBarW) . '</style>';
        $html .= '</head><body>';

        $html .= "<div class=\"header\"><span class=\"last-update\">{$updateTime}</span></div>";
        $html .= '<div class="widget"><div class="weather-grid">';

        // === Temperaturbalken ===
        $html .= '<div class="bars-area">';
        foreach ($days as $day) {
            $today = $this->IsToday($day['begin']);
            $topPct = round(($globalMax - $day['tMax']) / $globalRange * 100, 2);
            $heightPct = round(($day['tMax'] - $day['tMin']) / $globalRange * 100, 2);
            $cls = $today ? ' today' : '';
            $maxC = $today ? ' style="color:#f5c842"' : '';

            $html .= "<div class=\"bar-col{$cls}\">";
            $html .= "<div class=\"bar-unit\" style=\"top:{$topPct}%;height:{$heightPct}%\">";
            $html .= "<div class=\"temp-label-max\"{$maxC}>" . round($day['tMax']) . '°</div>';
            $html .= '<div class="bar-track"><div class="bar-fill"></div></div>';
            $html .= '<div class="temp-label-min">' . round($day['tMin']) . '°</div>';
            $html .= '</div></div>';
        }
        $html .= '</div>';

        // === Regen ===
        if ($showRain) {
            $html .= '<div class="rain-row">';
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
        }

        // === Wind ===
        if ($showWind) {
            $html .= '<div class="wind-row">';
            foreach ($days as $day) {
                $pct = min(100, round($day['windSpeed'] / $maxWind * 100, 1));
                $html .= '<div class="wind-cell">';
                $html .= "<div class=\"wind-bar-track\"><div class=\"wind-bar-fill\" style=\"height:{$pct}%\"></div></div>";
                $html .= '<div class="wind-label">' . round($day['windSpeed']) . 'kmh</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // === Icons ===
        $html .= '<div class="icon-row">';
        foreach ($days as $day) {
            $url = $this->GetIconUrl($day['icon']);
            $html .= "<div class=\"icon-cell\"><img class=\"weather-icon\" src=\"{$url}\" alt=\"Wetter\" loading=\"lazy\"></div>";
        }
        $html .= '</div>';

        // === Wochentage ===
        $html .= '<div class="day-row">';
        foreach ($days as $day) {
            $today = $this->IsToday($day['begin']);
            $wd = self::WEEKDAYS[(int) date('w', $day['begin'])];
            $cls = $today ? ' today' : '';
            $html .= "<div class=\"day-cell{$cls}\">{$wd}</div>";
        }
        $html .= '</div>';

        $html .= '</div></div></body></html>';
        return $html;
    }

    /**
     * CSS mit konfigurierbaren Balken-Dimensionen
     */
    private function BuildCSS(int $cols, int $rH, int $rW, int $wH, int $wW): string
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
.temp-label-max{font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:#fff;line-height:1;text-align:center}
.bar-track{width:clamp(5px,min(1.2vw,1.5vh),10px);flex:1;min-height:4px;background:rgba(255,255,255,0.08);border-radius:100px;position:relative;overflow:hidden}
.bar-fill{position:absolute;inset:0;border-radius:100px;background:#d4a017}
.bar-col.today .bar-fill{background:#f5c842;box-shadow:0 0 10px rgba(245,200,66,0.4)}
.temp-label-min{font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:#fff;line-height:1;text-align:center}
.rain-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(4px,0.8vh,8px) 0 clamp(2px,0.3vh,4px);gap:clamp(2px,0.5vw,6px)}
.rain-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(2px,0.3vh,4px)}
.rain-label{font-size:clamp(9px,min(1.8vw,2.2vh),15px);font-weight:600;color:#fff;line-height:1}
.rain-label.zero{color:#6e7681}
.rain-chance{font-size:clamp(7px,min(1.4vw,1.8vh),12px);font-weight:400;color:#9ea7b1;line-height:1}
.rain-bar-track{width:{$rW}%;height:{$rH}px;background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.rain-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:#2f81f7}
.wind-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.3vh,4px) 0;gap:clamp(2px,0.5vw,6px)}
.wind-cell{display:flex;flex-direction:column;align-items:center;gap:clamp(2px,0.3vh,4px)}
.wind-bar-track{width:{$wW}%;height:{$wH}px;background:rgba(255,255,255,0.06);position:relative;overflow:hidden}
.wind-bar-fill{position:absolute;bottom:0;left:0;width:100%;background:#8b949e}
.wind-label{font-size:clamp(9px,min(1.8vw,2.2vh),15px);font-weight:600;color:#fff;line-height:1;white-space:nowrap}
.icon-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.4vh,4px) 0}
.icon-cell{display:flex;justify-content:center}
.weather-icon{width:clamp(28px,min(7vw,7vh),60px);height:clamp(28px,min(7vw,7vh),60px);object-fit:contain}
.day-row{display:grid;grid-template-columns:repeat({$cols},1fr);flex-shrink:0;padding:clamp(2px,0.3vh,4px) 0 0}
.day-cell{text-align:center;font-size:clamp(10px,min(2.2vw,2.8vh),18px);font-weight:600;color:#fff;text-transform:uppercase;letter-spacing:.5px}
.day-cell.today{color:#f5c842}
CSS;
    }
}
