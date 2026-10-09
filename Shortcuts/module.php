<?php

declare(strict_types=1);

/**
 * Shortcuts (Schnellzugriff)
 * Kachel mit frei konfigurierbaren Knöpfen, die direkt zu einer Variable, Instanz,
 * Kategorie oder einem anderen Objekt der Visualisierung springen (HTML-SDK openObject).
 *
 * Autor: Armin Frohwerk
 * SPDX-License-Identifier: MIT
 */
class Shortcuts extends IPSModuleStrict
{
    // Werte aus Version 1.0 auf die passenden Symcon-Symbole (Font Awesome) abbilden
    private const ALT = ['auto' => '', 'bulb' => 'lightbulb', 'shutter' => 'blinds', 'radiator' => 'heat',
        'thermometer' => 'temperature-half', 'window' => 'window-frame', 'power' => 'power-off', 'garden' => 'seedling',
        'alarm' => 'bell'];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Shortcuts', '[]');
        $this->RegisterPropertyInteger('Layout', 0);            // 0 = Raster, 1 = Liste
        $this->RegisterPropertyInteger('TileTheme', 0);         // 0 = Symcon-Design, 1 = Dunkel, 2 = Hell
        $this->RegisterPropertyInteger('TileBackground', 0);    // Medienobjekt (Bild), 0 = keins
        $this->RegisterPropertyInteger('TileBackgroundDim', 30);

        $this->RegisterAttributeString('Watched', '[]');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->Watch();
        $this->SetBuffer('Values', '');
        $this->SetStatus(IS_ACTIVE);
        $this->UpdateVisualizationValue($this->Json($this->TileData()));
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                $this->ApplyChanges();
                break;
            case VM_UPDATE:
                $this->SendValues();
                break;
        }
    }

    public function GetVisualizationTile(): string
    {
        $Data = $this->Json($this->TileData());
        return file_get_contents(__DIR__ . '/tile.html') . '<script>handleMessage(' . json_encode($Data, JSON_HEX_TAG) . ');</script>';
    }

    // ------------------------------------------------------------------
    // Intern
    // ------------------------------------------------------------------

    /**
     * Konfigurierte Knöpfe, deren Objekt noch existiert.
     * Verknüpfungen werden auf ihr Ziel aufgelöst.
     */
    private function Shortcuts(): array
    {
        $List = json_decode($this->ReadPropertyString('Shortcuts'), true);
        $Result = [];
        foreach (is_array($List) ? $List : [] as $Row) {
            $ID = (int) ($Row['ObjectID'] ?? 0);
            if ($ID > 0 && IPS_ObjectExists($ID) && IPS_GetObject($ID)['ObjectType'] == 6 /* Link */) {
                $ID = (int) IPS_GetLink($ID)['TargetID'];
            }
            if ($ID <= 0 || !IPS_ObjectExists($ID)) {
                $this->SendDebug('Schnellzugriff', 'Objekt ' . ($Row['ObjectID'] ?? 0) . ' existiert nicht – übersprungen', 0);
                continue;
            }
            $Icon = trim((string) ($Row['Icon'] ?? ''));
            $Icon = self::ALT[$Icon] ?? $Icon;
            $Result[] = [
                'id'        => $ID,
                'caption'   => trim((string) ($Row['Caption'] ?? '')),
                'icon'      => preg_match('/^[a-z0-9-]{1,64}$/i', $Icon) ? $Icon : '',
                'image'     => (int) ($Row['Image'] ?? 0),
                'color'     => (int) ($Row['Color'] ?? -1),
                'showValue' => (bool) ($Row['ShowValue'] ?? true)
            ];
        }
        return $Result;
    }

    /**
     * Meldet die Variablen der Knöpfe an, deren Wert in der Kachel steht.
     */
    private function Watch(): void
    {
        $Old = json_decode($this->ReadAttributeString('Watched'), true) ?: [];
        $New = [];
        foreach ($this->Shortcuts() as $S) {
            if ($S['showValue'] && IPS_VariableExists($S['id'])) {
                $New[] = $S['id'];
            }
        }
        $New = array_values(array_unique($New));
        foreach (array_diff($Old, $New) as $ID) {
            $this->UnregisterMessage((int) $ID, VM_UPDATE);
        }
        foreach ($New as $ID) {
            $this->RegisterMessage($ID, VM_UPDATE);
        }
        $this->WriteAttributeString('Watched', json_encode($New));
    }

    private function TileData(): array
    {
        $Buttons = [];
        foreach ($this->Shortcuts() as $S) {
            $Object = IPS_GetObject($S['id']);
            $Buttons[] = [
                'id'    => $S['id'],
                'name'  => $S['caption'] !== '' ? $S['caption'] : $Object['ObjectName'],
                'icon'  => $S['icon'] !== '' ? $S['icon'] : $this->ObjectIcon($S['id'], $Object),
                'svg'   => $this->AutoIcon($S['id'], $Object['ObjectType']),
                'image' => $S['image'] > 0 ? $this->MediaDataUri($S['image'], 128, true) : null,
                'color' => $S['color'] >= 0 ? sprintf('#%06X', $S['color'] & 0xFFFFFF) : '',
                'value' => $S['showValue'] ? $this->Value($S['id']) : null
            ];
        }
        return [
            'theme'   => $this->ReadPropertyInteger('TileTheme'),
            'layout'  => $this->ReadPropertyInteger('Layout'),
            'buttons' => $Buttons,
            'background' => $this->MediaDataUri($this->ReadPropertyInteger('TileBackground'), 900, false),
            'dim'     => max(0, min(90, $this->ReadPropertyInteger('TileBackgroundDim'))),
            'text'    => [
                'empty'       => $this->Translate('No shortcuts yet. Add them in the instance configuration.'),
                'unsupported' => $this->Translate('Jumping to objects requires Symcon 8.2 or newer.')
            ]
        ];
    }

    /**
     * Schickt nur geänderte Werte an die Kachel.
     */
    private function SendValues(): void
    {
        $Values = [];
        foreach ($this->Shortcuts() as $S) {
            if ($S['showValue'] && IPS_VariableExists($S['id'])) {
                $Values[(string) $S['id']] = $this->Value($S['id']);
            }
        }
        $Json = $this->Json($Values);
        if ($Json === $this->GetBuffer('Values')) {
            return;
        }
        $this->SetBuffer('Values', $Json);
        $this->UpdateVisualizationValue($this->Json(['values' => $Values]));
    }

    private function Value(int $ID): ?string
    {
        if (!IPS_VariableExists($ID)) {
            return null;
        }
        try {
            return (string) GetValueFormatted($ID);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Bild aus einem Medienobjekt als data-URI, mit GD auf $Max Pixel verkleinert
     * (Symbole als PNG mit Transparenz, Hintergrund als JPEG). Zwischengespeichert, bis sich das Medium ändert.
     */
    private function MediaDataUri(int $MediaID, int $Max, bool $Png): ?string
    {
        if ($MediaID <= 0 || !IPS_MediaExists($MediaID)) {
            return null;
        }
        $Media = IPS_GetMedia($MediaID);
        $Key = $MediaID . ':' . $Max . ':' . ($Media['MediaUpdated'] ?? 0) . ':' . ($Media['MediaSize'] ?? 0);
        $Cache = json_decode($this->GetBuffer('Media'), true);
        $Cache = is_array($Cache) ? $Cache : [];
        if (array_key_exists($Key, $Cache)) {
            return $Cache[$Key];
        }
        $Uri = $this->BuildDataUri($MediaID, $Max, $Png);
        // nur die gerade genutzten Bilder behalten
        $Cache = array_filter($Cache, fn (string $K): bool => !str_starts_with($K, $MediaID . ':' . $Max . ':'), ARRAY_FILTER_USE_KEY);
        $Cache[$Key] = $Uri;
        $this->SetBuffer('Media', (string) json_encode($Cache));
        return $Uri;
    }

    private function BuildDataUri(int $MediaID, int $Max, bool $Png): ?string
    {
        $Raw = base64_decode((string) IPS_GetMediaContent($MediaID), true);
        if ($Raw === false || $Raw === '') {
            return null;
        }
        // SVG unverändert übernehmen (klein, skaliert selbst); als <img> ausgeführt laufen darin keine Skripte
        if (str_contains(substr($Raw, 0, 512), '<svg')) {
            return strlen($Raw) <= 200000 ? 'data:image/svg+xml;base64,' . base64_encode($Raw) : null;
        }
        // Riesige Bilder nicht dekodieren (Speicherschutz): höchstens 40 Megapixel
        $Info = @getimagesizefromstring($Raw);
        if ($Info === false || $Info[0] * $Info[1] > 40000000) {
            $this->SendDebug('Bild', 'Medium ' . $MediaID . ': kein Bild oder zu groß (max. 40 Megapixel).', 0);
            return null;
        }
        if (function_exists('imagecreatefromstring')) {
            $Img = @imagecreatefromstring($Raw);
            if ($Img !== false) {
                $W = imagesx($Img);
                $H = imagesy($Img);
                $Scale = min(1.0, $Max / max($W, $H));
                if ($Scale < 1.0) {
                    $Small = imagecreatetruecolor(max(1, (int) round($W * $Scale)), max(1, (int) round($H * $Scale)));
                    if ($Png) {
                        imagealphablending($Small, false);
                        imagesavealpha($Small, true);
                    }
                    imagecopyresampled($Small, $Img, 0, 0, 0, 0, imagesx($Small), imagesy($Small), $W, $H);
                    $Img = $Small;
                } elseif ($Png) {
                    imagesavealpha($Img, true);
                }
                ob_start();
                $Png ? imagepng($Img, null, 9) : imagejpeg($Img, null, 80);
                return 'data:image/' . ($Png ? 'png' : 'jpeg') . ';base64,' . base64_encode((string) ob_get_clean());
            }
        }
        // ohne GD: Original nur, wenn es klein genug ist
        if (strlen($Raw) > ($Png ? 200000 : 1500000)) {
            $this->SendDebug('Bild', 'Medium ' . $MediaID . ': zu groß und GD nicht verfügbar.', 0);
            return null;
        }
        return 'data:' . ($Info['mime'] ?? 'image/png') . ';base64,' . base64_encode($Raw);
    }

    /**
     * Symbol des Objekts selbst: Objekt-Symbol, sonst Symbol der Darstellung der Variable.
     */
    private function ObjectIcon(int $ID, array $Object): string
    {
        $Icon = (string) ($Object['ObjectIcon'] ?? '');
        if ($Icon === '' && IPS_VariableExists($ID)) {
            $Presentation = IPS_GetVariable($ID)['VariableCustomPresentation'] ?? [];
            $Icon = is_array($Presentation) ? (string) ($Presentation['ICON'] ?? '') : '';
        }
        return preg_match('/^[a-z0-9-]{1,64}$/i', $Icon) ? $Icon : '';
    }

    /**
     * Eingebautes Ersatzsymbol nach Objekttyp.
     */
    private function AutoIcon(int $ID, int $Type): string
    {
        switch ($Type) {
            case 0:     // Kategorie
                return 'folder';
            case 2:     // Variable
                return IPS_GetVariable($ID)['VariableType'] == 0 ? 'power' : 'star';
            default:
                return 'house';
        }
    }

    private function Json(array $Data): string
    {
        return (string) json_encode($Data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
