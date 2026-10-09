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
    // Symbole der Kachel (Schlüssel = Name in tile.html)
    private const SYMBOLE = ['auto', 'bulb', 'shutter', 'radiator', 'thermometer', 'window', 'door', 'plug', 'power',
        'water', 'camera', 'music', 'car', 'garden', 'alarm', 'house', 'sun', 'wind', 'star', 'folder'];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Shortcuts', '[]');
        $this->RegisterPropertyString('Title', '');
        $this->RegisterPropertyInteger('Layout', 0);            // 0 = Raster, 1 = Liste
        $this->RegisterPropertyInteger('TileTheme', 0);         // 0 = Symcon-Design, 1 = Dunkel, 2 = Hell

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
            $Icon = (string) ($Row['Icon'] ?? 'auto');
            $Result[] = [
                'id'        => $ID,
                'caption'   => trim((string) ($Row['Caption'] ?? '')),
                'icon'      => in_array($Icon, self::SYMBOLE, true) ? $Icon : 'auto',
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
                'icon'  => $S['icon'] !== 'auto' ? $S['icon'] : $this->AutoIcon($S['id'], $Object['ObjectType']),
                'color' => $S['color'] >= 0 ? sprintf('#%06X', $S['color'] & 0xFFFFFF) : '',
                'value' => $S['showValue'] ? $this->Value($S['id']) : null
            ];
        }
        return [
            'theme'   => $this->ReadPropertyInteger('TileTheme'),
            'layout'  => $this->ReadPropertyInteger('Layout'),
            'title'   => $this->ReadPropertyString('Title'),
            'buttons' => $Buttons,
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
