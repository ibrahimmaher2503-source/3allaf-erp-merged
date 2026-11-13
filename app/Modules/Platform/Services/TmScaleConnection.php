<?php

namespace App\Modules\Platform\Services;

use RuntimeException;

final class TmScaleConnection
{
    /** @return array{year: int, month: int, day: int, hour: int, minute: int, second: int} */
    public function readClock(string $address, int $port): array
    {
        $reply = $this->exchange($address, $port, "UPL\tTIM\t\r\n", "END\tTIM\t\r\n");
        if (! preg_match('/\A(?:\r\n)?DWL\tTIM\t\r\nTIM\t([0-9]{1,4})\t([0-9]{1,2})\t([0-9]{1,2})\t([0-9]{1,2})\t([0-9]{1,2})\t([0-9]{1,2})\t\r\nEND\tTIM\t\r\n\z/', $reply, $parts)) {
            throw new RuntimeException('Invalid scale clock reply.');
        }

        return [
            'year' => (int) $parts[1],
            'month' => (int) $parts[2],
            'day' => (int) $parts[3],
            'hour' => (int) $parts[4],
            'minute' => (int) $parts[5],
            'second' => (int) $parts[6],
        ];
    }

    /** @return array{number: int, code: string, name: string, price: string}|null */
    public function readProduct(string $address, int $port, int $number): ?array
    {
        $fields = $this->readProductFields($address, $port, $number);
        if ($fields === null) {
            return null;
        }

        preg_match('/\A([0-9]{1,9}),([0-4])\z/', $fields[5], $price);
        $digits = (int) $price[2];
        $amount = str_pad($price[1], $digits + 1, '0', STR_PAD_LEFT);

        return [
            'number' => $number,
            'code' => $fields[2],
            'name' => mb_check_encoding($fields[15], 'UTF-8') ? $fields[15] : mb_convert_encoding($fields[15], 'UTF-8', 'GB18030'),
            'price' => $digits === 0 ? $amount : substr($amount, 0, -$digits).'.'.substr($amount, -$digits),
        ];
    }

    public function sendWeightedProduct(string $address, int $port, int $number, string $code, string $name, string $price): void
    {
        if (! preg_match('/\A[0-9]{1,7}\z/', $code)
            || ! preg_match('/\A[ -~]{1,20}\z/', $name)
            || trim($name) !== $name
            || ! preg_match('/\A([0-9]{1,4})(?:\.([0-9]{1,2})0*)?\z/', $price, $parts)) {
            throw new RuntimeException('Product fields exceed the verified scale format.');
        }

        $minorUnits = (int) ($parts[1].str_pad($parts[2] ?? '', 2, '0'));
        if ($minorUnits === 0) {
            throw new RuntimeException('The scale price must be positive.');
        }

        $before = $this->readProductFields($address, $port, $number);
        if ($before !== null && ($before[2] !== $code || $before[4] !== '1' || $before[15] !== $name)) {
            throw new RuntimeException('The PLU contains a different product or unit.');
        }

        $fields = $before ?? array_fill(0, 70, '');
        if ($before === null) {
            $fields = array_replace($fields, [0 => 'PLU', 1 => (string) $number, 2 => $code, 3 => (string) $number,
                4 => '1', 10 => (string) $number, 15 => $name]);
        }
        $fields[5] = $minorUnits.',2';
        if ($fields === $before) {
            return;
        }

        $record = implode("\t", $fields);
        $this->exchange($address, $port, "DWL\tPLU\t\r\n\r\n{$record}\r\nEND\tPLU\t\r\n\r\nUPL\tTIM\t\r\n", "END\tTIM\t\r\n");
        $after = $this->readProductFields($address, $port, $number);
        if ($after === null || ($before !== null && $after !== $fields)
            || $after[2] !== $code || $after[4] !== '1' || $after[5] !== $fields[5] || $after[15] !== $name) {
            throw new RuntimeException('The scale write could not be confirmed.');
        }

        // A device was observed returning an older price shortly after a confirmed write, so read once more after a pause.
        usleep(1_500_000);
        $settled = $this->readProductFields($address, $port, $number);
        if ($settled === null || $settled[5] !== $fields[5] || $settled[2] !== $code || $settled[15] !== $name) {
            throw new RuntimeException('The scale value did not stay after the write.');
        }
    }

    /**
     * Sale-log records. Field layout (checked on the device against 37 records: total equals unit price times weight in all):
     * REP header: 1-6 = year (2 digits), month, day, hour, minute, second; 7 = sequence number (unique per record).
     * RES row: 1 = line number in the sale, 2 = total price, 3 = item code, 7 = weight (3 decimals, kg), 15 = unit price, last non-empty = name.
     * `consistent` is false when total differs from unit price times weight, so callers must not trust that record.
     *
     * @return list<array{sequence: int, recorded_at: string, line: int, plu: int, code: string, name: string, weight: string, unit_price: string, total: string, consistent: bool, raw: list<string>}>
     */
    public function readSalesReport(string $address, int $port): array
    {
        $reply = $this->exchange($address, $port, "UPL\tREP\t-16\t\r\n", "END\tREP\t\r\n", 4096);
        $records = [];
        $header = null;
        foreach (explode("\r\n", trim($reply, "\r\n")) as $line) {
            $fields = explode("\t", $line);
            if ($fields[0] === 'REP' && count($fields) >= 8) {
                $header = $fields;

                continue;
            }
            if ($fields[0] !== 'RES' || count($fields) < 16 || $header === null) {
                continue;
            }

            $total = $this->decodeDecimal($fields[2]);
            $weight = $this->decodeDecimal($fields[7]);
            $unitPrice = $this->decodeDecimal($fields[15]);
            $name = '';
            for ($index = count($fields) - 1; $index >= 0; $index--) {
                if ($fields[$index] !== '') {
                    $name = $this->decodeDeviceText($fields[$index]);
                    break;
                }
            }
            $records[] = [
                'sequence' => (int) $header[7],
                'recorded_at' => sprintf('20%02d-%02d-%02d %02d:%02d:%02d', (int) $header[1], (int) $header[2], (int) $header[3], (int) $header[4], (int) $header[5], (int) $header[6]),
                // Field 1 is the line number inside the sale, not the PLU; ERP stores the PLU number as the item code.
                'line' => (int) $fields[1],
                'plu' => (int) $fields[3],
                'code' => $fields[3],
                'name' => $name,
                'weight' => $weight,
                'unit_price' => $unitPrice,
                'total' => $total,
                'consistent' => abs((float) $total - round((float) $unitPrice * (float) $weight, 2)) < 0.011,
                'raw' => $fields,
            ];
            $header = null;
        }

        return $records;
    }

    public function probeAsset(string $address, int $port, string $asset): string
    {
        if (! in_array($asset, ['KEY', 'D0', 'D1'], true)) {
            throw new RuntimeException('Unsupported scale asset probe.');
        }

        $end = in_array($asset, ['D0', 'D1'], true) ? "END\t\t\r\n" : "END\tKEY\t\r\n";

        return $this->exchange($address, $port, "UPL\t{$asset}\t\r\n", $end, 512);
    }

    /**
     * Read-only: the raw BAR section (for example `B201E500K500A000A000A000`). Its column meaning is not verified,
     * so callers must display it as-is and never derive a barcode layout from it. Nothing is written to the device.
     *
     * @return list<string>
     */
    public function readBarcodeFormats(string $address, int $port): array
    {
        $reply = $this->exchange($address, $port, "UPL\tBAR\t\r\n", "END\tBAR\t\r\n", 64);
        preg_match_all('/^BAR\t([^\r\n]*)\r\n/m', $reply, $rows);

        return array_values(array_filter(array_map(fn (string $row): string => trim(str_replace("\t", ' ', $row)), $rows[1])));
    }

    /** @return array<int, array<int, int>> value per layer (0-2) and key (1-63); 0 means an empty key */
    public function readShortcutKeys(string $address, int $port): array
    {
        $keys = [];
        preg_match_all('/^SCP\t([0-9]+)\t([0-9]+)\t([0-9]+)\t\r\n/m', $this->readShortcutSection($address, $port), $rows, PREG_SET_ORDER);
        foreach ($rows as $row) {
            $keys[(int) $row[1]][(int) $row[2]] = (int) $row[3];
        }

        return $keys;
    }

    /**
     * Sets one shortcut key (SCP section) and confirms it by reading the whole section back: only that row may differ.
     * A value of 0 clears the key. Verified on the device with layer 0 key 2 (see docs/scale-device-protocol.md).
     */
    public function sendShortcutKey(string $address, int $port, int $layer, int $key, int $value): void
    {
        if ($layer < 0 || $layer > 2 || $key < 1 || $key > 63 || $value < 0 || $value > 99999) {
            throw new RuntimeException('Shortcut key is outside the verified scale range.');
        }

        $before = $this->readShortcutSection($address, $port);
        $pattern = '/^SCP\t'.$layer.'\t'.$key.'\t[0-9]+\t\r\n/m';
        if (preg_match_all($pattern, $before) !== 1) {
            throw new RuntimeException('The shortcut key was not found on the scale.');
        }
        $row = "SCP\t{$layer}\t{$key}\t{$value}\t\r\n";
        $expected = preg_replace($pattern, $row, $before, 1);
        if ($expected === $before) {
            return;
        }

        $this->exchange($address, $port, "DWL\tSCP\t\r\n\r\n{$row}END\tSCP\t\r\n\r\nUPL\tTIM\t\r\n", "END\tTIM\t\r\n");
        usleep(1_500_000);
        if ($this->readShortcutSection($address, $port) !== $expected) {
            throw new RuntimeException('The scale shortcut key could not be confirmed.');
        }
    }

    private function readShortcutSection(string $address, int $port): string
    {
        $reply = $this->exchange($address, $port, "UPL\tSCP\t\r\n", "END\tSCP\t\r\n", 400);
        if (! str_starts_with(ltrim($reply, "\r\n"), "DWL\tSCP\t\r\n")) {
            throw new RuntimeException('Invalid scale shortcut key reply.');
        }

        return $reply;
    }

    /** @return list<string>|null */
    private function readProductFields(string $address, int $port, int $number): ?array
    {
        if ($number < 1 || $number > 4000) {
            throw new RuntimeException('Invalid scale product number.');
        }

        $reply = $this->exchange($address, $port, "UPL\tPLU\t{$number}\t\r\n", "END\tPLU\t\r\n");
        $lines = explode("\r\n", trim($reply, "\r\n"));
        if (array_shift($lines) !== "DWL\tPLU\t" || array_pop($lines) !== "END\tPLU\t") {
            throw new RuntimeException('Invalid scale product reply.');
        }
        if ($lines === []) {
            return null;
        }

        $fields = explode("\t", $lines[0]);
        if (count($lines) !== 1 || count($fields) < 17 || $fields[0] !== 'PLU'
            || $fields[1] !== (string) $number || ! preg_match('/\A[0-9]{1,9},[0-4]\z/', $fields[5])) {
            throw new RuntimeException('Invalid scale product data.');
        }

        return $fields;
    }

    private function exchange(string $address, int $port, string $request, string $end, int $maxLines = 4): string
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $address)
            || $port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid scale endpoint.');
        }

        $socket = @stream_socket_client("tcp://{$address}:{$port}", $errorCode, $errorMessage, 5);
        if ($socket === false) {
            throw new RuntimeException('Scale connection failed.');
        }

        try {
            stream_set_timeout($socket, 3);
            if (@fwrite($socket, $request) !== strlen($request)) {
                throw new RuntimeException('Scale request failed.');
            }

            $reply = '';
            for ($line = 0; $line < $maxLines && ($part = @fgets($socket, 8192)) !== false; $line++) {
                $reply .= $part;
                if ($part === $end) {
                    return $reply;
                }
            }

            throw new RuntimeException('Scale reply was incomplete.');
        } finally {
            fclose($socket);
        }
    }

    private function decodeDecimal(string $value): string
    {
        if (! preg_match('/\A([0-9]+),([0-9])\z/', $value, $parts)) {
            return '0.00';
        }

        $precision = min((int) $parts[2], 4);

        return number_format(((int) $parts[1]) / (10 ** $precision), $precision, '.', '');
    }

    private function decodeDeviceText(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'GB18030');
    }
}
