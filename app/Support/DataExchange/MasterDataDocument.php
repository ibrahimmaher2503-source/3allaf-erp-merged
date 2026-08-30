<?php

declare(strict_types=1);

namespace App\Support\DataExchange;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MasterDataDocument
{
    /** @param list<string> $headers @param iterable<array<int, mixed>> $rows @param list<array<int, mixed>> $instructions */
    public function xlsx(string $filename, array $headers, iterable $rows, array $instructions = []): BinaryFileResponse
    {
        $base = tempnam(sys_get_temp_dir(), 'rajeh-xlsx-');
        $path = $base.'.xlsx';
        @unlink($base);
        $writer = WriterFactory::createFromFile($path);
        $writer->openToFile($path);
        if ($instructions !== []) {
            $writer->getCurrentSheet()->setName('Instructions');
            foreach ($instructions as $instruction) {
                $writer->addRow(Row::fromValues(array_map([$this, 'safeCell'], $instruction)));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Data');
        }
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues(array_map([$this, 'safeCell'], $values)));
        }
        $writer->close();

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    /** @param list<string> $headers @param iterable<array<int, mixed>> $rows */
    public function pdf(string $filename, string $title, array $headers, iterable $rows): Response
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(base_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pages.exports.master-data-pdf', ['title' => $title, 'headers' => $headers, 'rows' => collect($rows)])->render(), 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }

    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value))) {
            return "'".$value;
        }

        return $value;
    }
}
