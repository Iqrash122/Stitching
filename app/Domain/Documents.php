<?php

namespace App\Domain;

use App\Models\Allocation;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StitchingOrder;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class Documents
{
    public static function pdf(string $type, int $id, string $scope = 'orders'): Response
    {
        $settings = BusinessSetting::findOrFail(1);
        $rows = [];
        $sections = [];
        $summary = [];
        $contact = [];
        $notes = '';
        if (in_array($type, ['order', 'measurements', 'tailor'])) {
            $o = StitchingOrder::with('lines.snapshots')->findOrFail($id);
            $title = match ($type) {
                'order' => 'Order acknowledgement — not a tax invoice',
                'measurements' => 'Measurement sheet',
                'tailor' => 'Tailor job sheet',
            };
            $ref = $o->reference;
            $contact = $o->contact;
            $notes = $o->notes ?? '';
            foreach ($o->lines as $l) {
                $rows[] = $type === 'tailor'
                    ? ['Garment / stitching item' => $l->description, 'Quantity' => $l->quantity, 'Measurement mode' => $l->measurement_mode === 'per_piece' ? 'Separate per piece' : 'Shared', 'Stitching instructions' => $l->instructions ?: '—']
                    : ['Garment' => $l->description, 'Quantity' => $l->quantity, 'Instructions' => $l->instructions];
                if (in_array($type, ['measurements', 'tailor'], true)) {
                    foreach ($l->snapshots->where('current', true) as $s) {
                        $values = [];
                        foreach ($s->fields as $f) {
                            $values[] = ['Section' => $f['section'], 'Measurement' => $f['label'], 'Value' => $f['is_set'] ? ($f['type'] === 'choice' ? (array_column($f['options'], 'label', 'code')[$f['value']] ?? $f['value']) : $f['value']) : 'Not set', 'Unit' => $f['unit']];
                        }$sections[] = ['title' => $l->description.' · '.($s->piece ? 'Piece '.$s->piece : 'Shared measurements').' · Revision '.$s->revision.($s->frozen_at ? ' · Frozen' : ' · DRAFT'), 'rows' => $values];
                    }
                }
            }
            $summary = [
                'Promised delivery' => $o->promised_at->timezone($settings->timezone)->format('d M Y h:i A'),
                'Status' => $o->confirmed_at ? $o->stage : 'Unconfirmed draft',
            ];
            if ($type === 'order') {
                $summary['Order total'] = self::money($o->totals['total'], $settings->currency);
            }
            if ($type === 'tailor') {
                $summary['Department'] = 'Tailor / Production';
            }
        } elseif ($type === 'invoice') {
            $i = Invoice::findOrFail($id);
            $title = $i->state === 'posted' ? 'Invoice' : 'Draft invoice';
            $ref = $i->reference;
            $contact = $i->contact;
            foreach ($i->lines as $l) {
                $rows[] = ['Garment' => $l['description'], 'Qty' => $l['quantity'], 'Unit price' => self::money($l['unit_price'], $i->currency), 'Discount' => self::money($l['totals']['line_discount'] + $l['totals']['header_discount'], $i->currency), 'Net' => self::money($l['totals']['net'], $i->currency), 'Tax' => self::money($l['totals']['tax'], $i->currency), 'Total' => self::money($l['totals']['total'], $i->currency)];
            }$summary = ['Net' => self::money($i->totals['net'], $i->currency), 'Tax' => self::money($i->totals['tax'], $i->currency), 'Total' => self::money($i->totals['total'], $i->currency), 'Outstanding' => self::money(Billing::balance($i), $i->currency)];
        } elseif ($type === 'receipt') {
            $p = Payment::findOrFail($id);
            $title = 'Payment receipt';
            $ref = $p->reference;
            $contact = $p->contact ?? Customer::findOrFail($p->customer_id)->only(['name', 'phone', 'address']);
            $summary = ['Received amount' => self::money($p->amount, $settings->currency), 'Method' => $p->method_snapshot['name'] ?? '', 'Settlement state' => $p->settlement, 'Effective date' => $p->effective_at->timezone($settings->timezone)->format('d M Y h:i A'), 'Reference' => $p->external_reference ?? ''];
            foreach (Allocation::where('payment_id', $p->id)->get() as $a) {
                $rows[] = ['Invoice' => Invoice::findOrFail($a->invoice_id)->reference, 'Allocation' => self::money($a->amount, $settings->currency), 'Date' => $a->effective_at->timezone($settings->timezone)->format('d M Y h:i A')];
            }
        } elseif ($type === 'customer') {
            abort_unless(in_array($scope, ['orders', 'billing', 'measurements']), 422);
            $c = Customer::findOrFail($id);
            $title = 'Customer history · '.$scope;
            $ref = 'CUSTOMER-'.$c->id;
            $contact = $c->only(['name', 'phone', 'address']);
            if ($scope === 'orders') {
                foreach ($c->orders()->latest('id')->get() as $o) {
                    $rows[] = ['Order' => $o->reference, 'Status' => $o->stage, 'Date' => $o->ordered_at->timezone($settings->timezone)->format('d M Y'), 'Total' => self::money($o->totals['total'], $settings->currency)];
                }
            } elseif ($scope === 'billing') {
                foreach (Invoice::where('customer_id', $c->id)->get() as $i) {
                    $rows[] = ['Invoice' => $i->reference, 'Status' => $i->state, 'Total' => self::money($i->totals['total'], $i->currency), 'Due' => self::money(Billing::balance($i), $i->currency)];
                }
            } else {
                foreach ($c->orders()->whereNotNull('confirmed_at')->with('lines.snapshots')->get() as $o) {
                    foreach ($o->lines as $l) {
                        foreach ($l->snapshots->where('current', true) as $s) {
                            $values = [];
                            foreach ($s->fields as $f) {
                                $values[] = ['Measurement' => $f['section'].' / '.$f['label'], 'Value' => $f['is_set'] ? $f['value'] : 'Not set', 'Unit' => $f['unit']];
                            }$sections[] = ['title' => $o->reference.' · '.$l->description.' · Piece '.$s->piece.' · Revision '.$s->revision, 'rows' => $values];
                        }
                    }
                }
            }
        } else {
            abort(404);
        }
        $logo = $settings->logo && Storage::disk('local')->exists($settings->logo) ? 'data:'.Storage::disk('local')->mimeType($settings->logo).';base64,'.base64_encode(Storage::disk('local')->get($settings->logo)) : null;

        $html = view('studio.document', compact('title', 'ref', 'settings', 'contact', 'rows', 'sections', 'summary', 'notes', 'logo'))->render();

        return self::renderPdf($html, $ref.'-'.$type.'.pdf', 'P');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function reportPdf(string $type, array $filters): Response
    {
        $settings = BusinessSetting::findOrFail(1);
        $source = Reports::rows($type, $filters);
        $rows = array_map(function (array $row) use ($settings) {
            $out = [];
            foreach ($row as $key => $value) {
                $label = Str::headline(str_replace('_minor', '', (string) $key));
                if ((str_ends_with((string) $key, '_minor') || in_array($key, ['net_spending', 'allocated', 'due'], true)) && is_int($value)) {
                    $value = self::money($value, $settings->currency);
                } elseif ($value instanceof CarbonInterface) {
                    $value = $value->timezone($settings->timezone)->format('d M Y h:i A');
                } elseif (is_array($value)) {
                    $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } elseif (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }
                $out[$label] = $value;
            }

            return $out;
        }, $source);
        $title = 'Owner internal report · '.Str::headline($type);
        $ref = strtoupper($type).'-REPORT';
        $contact = [];
        $sections = [];
        $notes = '';
        $summary = ['Matching rows' => count($source)];
        if (! empty($filters['cutoff'])) {
            $summary['Historical cutoff'] = Orders::date($filters['cutoff'])->timezone($settings->timezone)->format('d M Y h:i A');
        }
        $activeFilters = array_filter($filters, fn ($value, $key) => $key !== 'type' && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH);
        if ($activeFilters) {
            $summary['Filters'] = collect($activeFilters)->map(fn ($value, $key) => Str::headline((string) $key).': '.$value)->implode(' · ');
        }
        $logo = $settings->logo && Storage::disk('local')->exists($settings->logo)
            ? 'data:'.Storage::disk('local')->mimeType($settings->logo).';base64,'.base64_encode(Storage::disk('local')->get($settings->logo))
            : null;

        $html = view('studio.document', compact('title', 'ref', 'settings', 'contact', 'rows', 'sections', 'summary', 'notes', 'logo'))->render();

        return self::renderPdf($html, strtolower($type).'-report.pdf', 'L');
    }

    private static function renderPdf(string $html, string $filename, string $orientation = 'P'): Response
    {
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->WriteHTML($html);
        $content = $mpdf->Output('', Destination::STRING_RETURN);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public static function money(int $amount, string $currency): string
    {
        return $currency.' '.bcdiv((string) $amount, '100', 2);
    }

    private static function safe(mixed $v): string
    {
        $v = is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_THROW_ON_ERROR);

        return preg_match('/^[\s]*[=+@\-\t\r]/u', $v) ? "'".$v : $v;
    }

    /**
     * @param  array<int, array<string|int, mixed>>  $rows
     */
    public static function writeExport(array $rows, string $name, string $format): string
    {
        $headers = $rows ? array_keys($rows[0]) : ['No matching records'];
        $path = 'exports/'.Str::uuid().'.'.$format;
        Storage::disk('local')->makeDirectory('exports');
        $full = Storage::disk('local')->path($path);
        if ($format === 'csv') {
            $fp = fopen($full, 'w');
            if ($fp === false) {
                throw new \RuntimeException('Cannot create export file.');
            }
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($fp, array_map(self::safe(...), array_values($row)), ',', '"', '');
            }fclose($fp);
        } else {
            $zip = new \ZipArchive;
            $zip->open($full, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ([$headers, ...array_map('array_values', $rows)] as $row) {
                $xml .= '<row>';
                foreach ($row as $v) {
                    $xml .= '<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars(self::safe($v), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }$xml .= '</row>';
            }$xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();
        }

        return $path;
    }
}
