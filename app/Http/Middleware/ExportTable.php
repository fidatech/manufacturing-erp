<?php

namespace App\Http\Middleware;

use App\Support\Xlsx;
use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Any page that shows a table can be downloaded as an Excel file: add ?export=xlsx (or csv)
 * to its address. The page is made as usual, with the same filters, then its tables are read
 * and written to the file. So every list and report gets Excel without code of its own.
 *
 * Left out: columns of buttons ("Actions"), and tables that are forms (rows you type in).
 */
class ExportTable
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $format = $request->query('export');

        if (! in_array($format, ['xlsx', 'csv'], true) || ! $request->isMethod('GET') || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        [$title, $rows] = $this->read((string) $response->getContent());
        if (! $rows) {
            return $response;
        }
        $name = (Str::slug($title) ?: 'export').'-'.now()->format('Y-m-d');

        if ($format === 'xlsx' && class_exists(\ZipArchive::class)) {
            return response(Xlsx::build($rows, $title, text_dir() === 'rtl'), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
            ]);
        }

        // CSV with a mark at the start, so Excel reads Dari and Pashto correctly
        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($cell) => is_array($cell) ? $cell['n'] : $cell, $row['cells']));
        }
        rewind($out);

        return response("\xEF\xBB\xBF".stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'.csv"',
        ]);
    }

    /** @return array{0: string, 1: list<array{cells: list<string|array{n: string}>, bold?: bool}>} */
    private function read(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $heading = $xpath->query('//h1')->item(0);
        $title = $this->text($heading) ?: 'Export';
        $rows = [['cells' => [$title], 'bold' => true]];
        $company = setting('company_name') ?: config('app.name');
        $rows[] = ['cells' => [$company.' · '.fmt_date(today_iso())]];

        $found = 0;
        foreach ($xpath->query('//main//table | //div[contains(@class,"paper-body")]//table') as $table) {
            /** @var DOMElement $table */
            if ($table->hasAttribute('data-rows') || $this->has($table, 'dash-table') || $xpath->query('ancestor::form[translate(@method,"POST","post")="post"]', $table)->length) {
                continue;
            }
            $body = [];
            $skip = [];
            foreach ($xpath->query('.//tr', $table) as $tr) {
                $cells = [];
                $isHead = false;
                $column = 0;
                foreach ($tr->childNodes as $cell) {
                    if (! $cell instanceof DOMElement || ! in_array($cell->tagName, ['td', 'th'], true)) {
                        continue;
                    }
                    $span = max(1, (int) $cell->getAttribute('colspan'));
                    $isHead = $isHead || ($cell->tagName === 'th' && $tr->parentNode instanceof DOMElement && $tr->parentNode->tagName === 'thead');
                    $buttons = $this->has($cell, 'no-print') || $xpath->query('.//form | .//*[contains(concat(" ",normalize-space(@class)," ")," btn ")]', $cell)->length > 0;
                    if ($isHead && $buttons) {
                        $skip[$column] = true;
                    }
                    if (! $buttons && empty($skip[$column])) {
                        $cells[] = $this->value($this->text($cell));
                        for ($i = 1; $i < $span; $i++) {
                            $cells[] = '';
                        }
                    }
                    $column += $span;
                }
                if (array_filter($cells, fn ($cell) => $cell !== '')) {
                    $body[] = ['cells' => $cells] + ($isHead ? ['bold' => true] : []);
                }
            }
            if ($body) {
                $rows[] = ['cells' => []];
                array_push($rows, ...$body);
                $found++;
            }
        }

        return [$title, $found ? $rows : []];
    }

    private function has(DOMElement $node, string $class): bool
    {
        return str_contains(' '.$node->getAttribute('class').' ', ' '.$class.' ');
    }

    private function text(?\DOMNode $node): string
    {
        return $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '') : '';
    }

    /** "7,000.00" becomes a number for Excel; everything else stays text. */
    private function value(string $text): string|array
    {
        if (preg_match('/^-?\d{1,3}(,\d{3})*(\.\d+)?$|^-?\d+\.\d+$/', $text) && strlen($text) < 20) {
            return ['n' => str_replace(',', '', $text)];
        }

        return $text;
    }
}
