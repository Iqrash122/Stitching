<!doctype html>
<html lang="ur" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Order Slip</title>
    <style>
        @page { margin: 8mm 9mm; }
        body {
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #173e48;
            line-height: 1.4;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: middle; }
        .header { margin-bottom: 5mm; }
        .header td { padding: 1.2mm 2mm; }
        .qr { width: 16%; text-align: center; border: 0.3mm solid #a9c9d0; }
        .reference { font-size: 11pt; font-weight: bold; color: #08697a; }
        .quantity { font-size: 9pt; }
        .brand { width: 43%; text-align: center; color: #08697a; font-size: 22pt; font-weight: bold; }
        .slogan { text-align: center; font-size: 8pt; color: #496872; }
        .service { text-align: center; font-size: 17pt; font-weight: bold; color: #08697a; }
        .customer-label { width: 12%; text-align: right; color: #08697a; font-weight: bold; font-size: 9pt; }
        .customer-value { width: 29%; text-align: right; font-size: 10pt; }
        .measurements { margin-bottom: 5mm; }
        .section { background: #08697a; color: #fff; font-size: 10pt; padding: 2.3mm 2mm; text-align: right; }
        .column-heading { background: #e7f0f3; border: 0.2mm solid #b4ced5; padding: 1.8mm 1mm; font-size: 8.5pt; text-align: center; }
        .data-cell { border: 0.2mm solid #b4ced5; padding: 1.3mm 1.2mm; }
        .dense .data-cell { padding: 0.6mm 0.9mm; line-height: 1.15; font-size: 9pt; }
        .dense .value { font-size: 11pt; }
        .dense .choice, .dense .unit, .dense .field-note { font-size: 8pt; }
        .number { text-align: center; color: #08697a; font-size: 9pt; }
        .measurement-name, .style-name { text-align: right; font-weight: bold; }
        .value { text-align: center; font-size: 12pt; font-weight: bold; background: #f4f8fa; }
        .unit { text-align: center; font-size: 8pt; color: #496872; }
        .choice { text-align: right; font-size: 8.5pt; }
        .choice-option { white-space: nowrap; }
        .choice-mark { width: 3mm; height: 3mm; vertical-align: middle; }
        .field-note { text-align: right; font-size: 8.5pt; }
        .gutter, .empty-cell { border: none; padding: 0; }
        .notes-heading { background: #f5ead8; color: #734c19; padding: 2mm; text-align: right; font-size: 10pt; }
        .notes-body { border: 0.2mm solid #d5c4a8; padding: 3mm; vertical-align: top; text-align: right; line-height: 1.8; font-size: 10pt; }
        .detail-label, .detail-value { border-bottom: 0.2mm solid #d5c4a8; padding: 2.5mm 2mm; font-size: 9pt; text-align: right; }
        .detail-label { font-weight: bold; color: #08697a; }
        .footer { margin-top: 3mm; font-size: 8pt; color: #496872; }
        .footer td { border-top: 0.2mm solid #c9dce2; padding-top: 2mm; }
    </style>
</head>
<body>
@php
    // Static vector marks keep checkboxes sharp without depending on font glyphs.
    $checkboxSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><rect x="1" y="1" width="14" height="14" fill="white" stroke="#173e48" stroke-width="1.4"/>%s</svg>';
    $emptyBox = 'data:image/svg+xml;base64,'.base64_encode(sprintf($checkboxSvg, ''));
    $checkedBox = 'data:image/svg+xml;base64,'.base64_encode(sprintf($checkboxSvg, '<path d="M3.5 8 L6.5 11 L12.5 4.5" fill="none" stroke="#173e48" stroke-width="2"/>'));
@endphp
@foreach($pages as $p)
    @php
        $rowCount = max(1, count($p['col1_rows']), count($p['col2_rows']));
        // Size actual table cells: mPDF ignores block dimensions inside table cells.
        $rowHeight = round(min(20, max(6, 158 / $rowCount)), 2);
        $notesHeight = round(max(30, 188 - $rowHeight * $rowCount), 2);
    @endphp
    <table class="header" dir="ltr" autosize="1">
        <tr>
            <td class="qr" rowspan="3">
                <barcode code="{{ $p['order_reference'] }}" type="QR" size="0.8" error="M" disableborder="1" />
                <br><span class="reference">{{ $p['order_reference'] }}</span>
                <br><span class="quantity">Qty: {{ $p['quantity'] }}</span>
            </td>
            <td class="brand" dir="rtl">سلائی اسٹوڈیو</td>
            <td class="customer-label" dir="rtl">نام:</td>
            <td class="customer-value" dir="rtl">{{ $p['customer_name'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="slogan" dir="rtl">معیاری سلائی · بہترین فٹنگ · آپ کا اعتماد</td>
            <td class="customer-label" dir="rtl">فون نمبر:</td>
            <td class="customer-value" dir="ltr">{{ $p['customer_phone'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="service" dir="rtl">{{ $p['service_name'] }}</td>
            <td class="customer-label" dir="rtl">تاریخ:</td>
            <td class="customer-value" dir="ltr">{{ $p['date'] }}</td>
        </tr>
    </table>

    <!-- A single table avoids nested-table shrinking and keeps both columns aligned. -->
    <table class="measurements {{ $rowCount > 18 ? 'dense' : '' }}" dir="ltr" autosize="1">
        <thead>
            <tr>
                <th class="section" colspan="4" dir="rtl">{{ $p['col1_title'] }}</th>
                <th class="gutter"></th>
                <th class="section" colspan="4" dir="rtl">{{ $p['col2_title'] }}</th>
            </tr>
            <tr>
                <th class="column-heading" style="width: 4%;" dir="rtl">نمبر</th>
                <th class="column-heading" style="width: 23%;" dir="rtl">ناپ کا نام</th>
                <th class="column-heading" style="width: 10%;" dir="rtl">قدر</th>
                <th class="column-heading" style="width: 7%;" dir="rtl">اکائی</th>
                <th class="gutter" style="width: 2%;"></th>
                <th class="column-heading" style="width: 4%;" dir="rtl">نمبر</th>
                <th class="column-heading" style="width: 18%;" dir="rtl">اسٹائل کا نام</th>
                <th class="column-heading" style="width: 23%;" dir="rtl">انتخاب</th>
                <th class="column-heading" style="width: 9%;" dir="rtl">نوٹ</th>
            </tr>
        </thead>
        <tbody>
        @for($i = 0; $i < $rowCount; $i++)
            @php
                $measurement = $p['col1_rows'][$i] ?? null;
                $style = $p['col2_rows'][$i] ?? null;
            @endphp
            <tr>
                @if($measurement)
                    <td class="data-cell number" style="height: {{ $rowHeight }}mm;">{{ $measurement['num'] }}</td>
                    <td class="data-cell measurement-name" dir="rtl">{{ $measurement['name'] }}</td>
                    <td class="data-cell value" dir="ltr">{{ $measurement['val'] }}</td>
                    <td class="data-cell unit" dir="rtl">{{ $measurement['unit'] }}</td>
                @else
                    <td class="empty-cell" colspan="4" style="height: {{ $rowHeight }}mm;"></td>
                @endif
                <td class="gutter"></td>
                @if($style)
                    <td class="data-cell number">{{ $style['num'] }}</td>
                    <td class="data-cell style-name" dir="rtl">{{ $style['name'] }}</td>
                    <td class="data-cell choice" dir="rtl">
                        @foreach($style['options'] as $option)
                            <span class="choice-option"><img class="choice-mark" src="{{ $option['selected'] ? $checkedBox : $emptyBox }}" alt="{{ $option['selected'] ? 'Selected' : 'Not selected' }}">&nbsp;{{ str_replace(' ', "\u{00A0}", $option['label']) }}</span>@if(!$loop->last)&nbsp; @endif
                        @endforeach
                    </td>
                    <td class="data-cell field-note" dir="rtl">{{ $style['note'] }}</td>
                @else
                    <td class="empty-cell" colspan="4"></td>
                @endif
            </tr>
        @endfor
        </tbody>
    </table>

    <table dir="ltr" autosize="1" style="page-break-inside: avoid;">
        <tr>
            <th class="notes-heading" style="width: 56%;" dir="rtl">نوٹ / خصوصی ہدایات</th>
            <th class="gutter" style="width: 2%;"></th>
            <th class="notes-heading" colspan="2" dir="rtl">آرڈر کی تفصیلات</th>
        </tr>
        <tr>
            <td class="notes-body" rowspan="3" style="height: {{ $notesHeight }}mm;" dir="rtl">{!! nl2br(e($p['notes'])) !!}</td>
            <td class="gutter" rowspan="3"></td>
            <td class="detail-label" style="width: 20%; height: {{ $notesHeight / 3 }}mm;" dir="rtl">وعدہ شدہ ڈیلیوری:</td>
            <td class="detail-value" style="width: 22%;" dir="ltr">{{ $p['delivery_date'] }}</td>
        </tr>
        <tr>
            <td class="detail-label" style="height: {{ $notesHeight / 3 }}mm;" dir="rtl">حالت:</td>
            <td class="detail-value" dir="ltr">{{ $p['status'] }}</td>
        </tr>
        <tr>
            <td class="detail-label" style="height: {{ $notesHeight / 3 }}mm;" dir="rtl">شعبہ:</td>
            <td class="detail-value" dir="ltr">{{ $p['department'] }}</td>
        </tr>
    </table>
    <table class="footer" dir="ltr" autosize="1">
        <tr>
            <td style="text-align: left; font-weight: bold;">{{ $p['order_reference'] }}</td>
            <td style="text-align: right;">Printed: {{ $p['printed_at'] }}</td>
        </tr>
    </table>
    @if(!$loop->last)<pagebreak />@endif
@endforeach
</body>
</html>
